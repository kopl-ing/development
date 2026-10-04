<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Kopling\Core\People\Person;
use Kopling\SportsManagement\RefereeDuty;
use Kopling\SportsManagement\StaffRole;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamInvitation;
use Kopling\SportsManagement\TeamMatch;

function referee(Team $team, string $email = 'ref@example.test'): Person
{
    $person = coach('Ref', $email, ['access-sports-management', 'manage-teams', 'manage-matches', 'track-matches']);
    $team->staff()->attach($person, ['role' => StaffRole::Referee->value]);

    return $person;
}

/**
 * @param array<int, RefereeDuty> $duties
 */
function assignReferee(TeamMatch $match, Person $referee, array $duties = [RefereeDuty::Timing, RefereeDuty::Scoring]): TeamMatch
{
    $match->update(['referee_person_id' => $referee->id, 'referee_duties' => $duties]);

    return $match->fresh();
}

afterEach(fn () => Carbon::setTestNow());

it('invites someone as referee, who joins with that role and cannot become owner', function () {
    $owner = coach('Owner', 'owner@example.test');
    $team = staffedTeam($owner);
    $person = coach('Ref', 'ref@example.test', ['access-sports-management']);

    $this->actingAs($owner)->post("/sports-management/{$team->id}/invitations", ['email' => 'ref@example.test', 'role' => 'referee'])->assertSessionHasNoErrors();
    $this->actingAs($person)->post('/sports-management/invitations/'.TeamInvitation::firstOrFail()->id.'/accept');

    expect($team->roleOf($person))->toBe(StaffRole::Referee)
        ->and($team->isCoachedBy($person))->toBeFalse();
    $this->actingAs($owner)->post("/sports-management/{$team->id}/staff/{$person->id}/owner")->assertNotFound();
});

it('shows a referee only the matches assigned to them, without roster or staff', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    rosterMember($team, 'Jip Jansen');
    $ref = referee($team);
    $assigned = assignReferee(plannedMatch($team, ['opponent_name' => 'Assigned FC']), $ref);
    $other = plannedMatch($team, ['opponent_name' => 'Other FC']);

    $this->actingAs($ref)->get("/sports-management/{$team->id}")->assertOk()
        ->assertSee('Assigned FC')
        ->assertDontSee('Other FC')
        ->assertDontSee('Jip Jansen')
        ->assertDontSee('coach@example.test');
    $this->actingAs($ref)->get('/sports-management')->assertSee('Assigned FC')->assertDontSee('Other FC');

    $this->actingAs($ref)->get("/sports-management/{$team->id}/matches/{$assigned->id}")->assertOk()->assertDontSee('Jip Jansen');
    $this->actingAs($ref)->get("/sports-management/{$team->id}/matches/{$other->id}")->assertForbidden();
    $this->actingAs($ref)->get(trackUrl($other))->assertForbidden();
});

it('keeps team, roster and match management with coaches, whatever the referee\'s permissions', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $jip = rosterMember($team, 'Jip');
    $ref = referee($team);
    $match = assignReferee(plannedMatch($team), $ref);

    $this->actingAs($ref)->post("/sports-management/{$team->id}", ['name' => 'Renamed', 'season' => '2026/2027'])->assertForbidden();
    $this->actingAs($ref)->post("/sports-management/{$team->id}/members", ['name' => 'Kid'])->assertForbidden();
    $this->actingAs($ref)->post("/sports-management/{$team->id}/invitations", ['email' => 'x@example.test'])->assertForbidden();
    $this->actingAs($ref)->post("/sports-management/{$team->id}/matches", ['opponent_name' => 'X', 'home_away' => 'home', 'scheduled_at' => '2026-10-11 10:00'])->assertForbidden();
    $this->actingAs($ref)->post("/sports-management/{$team->id}/matches/{$match->id}/lineup", ['team_member_id' => $jip->id, 'zone' => 'F'])->assertForbidden();
    $this->actingAs($ref)->post("/sports-management/{$team->id}/matches/{$match->id}/availability", [])->assertForbidden();
});

it('assigns a referee and their duties from the match form', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $ref = referee($team);
    $match = plannedMatch($team);
    $form = ['opponent_name' => 'FC Rivals', 'home_away' => 'home', 'scheduled_at' => '2026-10-10 09:30'];

    $this->actingAs($coach)->post("/sports-management/{$team->id}/matches/{$match->id}", $form + ['referee_person_id' => $coach->id, 'referee_duties' => ['timing']])
        ->assertSessionHasErrors('referee_person_id');
    $this->actingAs($coach)->post("/sports-management/{$team->id}/matches/{$match->id}", $form + ['referee_person_id' => $ref->id])
        ->assertSessionHasErrors('referee_duties');
    $this->actingAs($coach)->post("/sports-management/{$team->id}/matches/{$match->id}", $form + ['referee_person_id' => $ref->id, 'referee_duties' => ['sanctions']])
        ->assertSessionHasErrors('referee_duties.0');

    $this->actingAs($coach)->post("/sports-management/{$team->id}/matches/{$match->id}", $form + ['referee_person_id' => $ref->id, 'referee_duties' => ['scoring']])
        ->assertSessionHasNoErrors();
    expect($match->fresh()->referee_duties->all())->toBe([RefereeDuty::Scoring]);

    $this->actingAs($coach)->get("/sports-management/{$team->id}/matches/{$match->id}")->assertSee('Referee: Ref (score)');

    $this->actingAs($coach)->post("/sports-management/{$team->id}/matches/{$match->id}", $form + ['referee_duties' => ['scoring']])
        ->assertSessionHasNoErrors();
    expect($match->fresh()->referee_person_id)->toBeNull()->and($match->fresh()->referee_duties)->toBeNull();
});

it('hands delegated duties to the referee alone, and keeps the field with the coach', function () {
    Carbon::setTestNow('2026-10-10 09:30:00');
    $coach = coach();
    $team = staffedTeam($coach);
    $jip = rosterMember($team, 'Jip');
    $sam = rosterMember($team, 'Sam');
    $ref = referee($team);
    $match = assignReferee(plannedMatch($team), $ref);
    lineup($this, $coach, $match, [$jip->id => 'F', $sam->id => 'M']);

    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play'])->assertForbidden();
    $this->actingAs($ref)->post(trackUrl($match, '/periods/start'), ['type' => 'play'])->assertSessionHasNoErrors();

    $this->actingAs($coach)->post(trackUrl($match, '/goals'), ['scorer_team_member_id' => $jip->id])->assertForbidden();
    $this->actingAs($ref)->post(trackUrl($match, '/goals'), ['scorer_team_member_id' => $jip->id, 'assist_team_member_id' => $sam->id])->assertSessionHasNoErrors();

    $this->actingAs($ref)->post(trackUrl($match, '/field'), ['team_member_id' => $jip->id, 'zone' => 'M'])->assertForbidden();
    $this->actingAs($ref)->post(trackUrl($match, '/substitutions'), ['off_team_member_id' => $jip->id])->assertForbidden();
    $this->actingAs($coach)->post(trackUrl($match, '/field'), ['team_member_id' => $jip->id, 'zone' => 'M'])->assertSessionHasNoErrors();

    expect($match->fresh()->timeline()->score())->toBe(['us' => 1, 'them' => 0]);
});

it('leaves undelegated duties with the coach and keeps the referee out of them', function () {
    Carbon::setTestNow('2026-10-10 09:30:00');
    $coach = coach();
    $team = staffedTeam($coach);
    $team->update(['sport' => 'hockey']);
    $jip = rosterMember($team, 'Jip');
    $ref = referee($team);
    $match = assignReferee(plannedMatch($team), $ref, [RefereeDuty::Timing]);
    lineup($this, $coach, $match, [$jip->id => 'F']);
    $this->actingAs($ref)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);

    $this->actingAs($ref)->post(trackUrl($match, '/goals'), [])->assertForbidden();
    $this->actingAs($ref)->post(trackUrl($match, '/sanctions'), ['team_member_id' => $jip->id, 'kind' => 'green_card'])->assertForbidden();
    $this->actingAs($coach)->post(trackUrl($match, '/goals'), [])->assertSessionHasNoErrors();
    $this->actingAs($coach)->post(trackUrl($match, '/sanctions'), ['team_member_id' => $jip->id, 'kind' => 'green_card'])->assertSessionHasNoErrors();

    $match = assignReferee($match, $ref, [RefereeDuty::Sanctions]);
    $this->actingAs($coach)->post(trackUrl($match, '/sanctions'), ['team_member_id' => $jip->id, 'kind' => 'green_card'])->assertForbidden();
    $this->actingAs($ref)->post(trackUrl($match, '/sanctions'), ['team_member_id' => $jip->id, 'kind' => 'green_card'])->assertSessionHasNoErrors();
});

it('hides delegated controls from the coach and shows the referee only the field', function () {
    Carbon::setTestNow('2026-10-10 09:30:00');
    $coach = coach();
    $team = staffedTeam($coach);
    $jip = rosterMember($team, 'Jip');
    $sam = rosterMember($team, 'Sam');
    $ref = referee($team);
    $match = assignReferee(plannedMatch($team), $ref);
    lineup($this, $coach, $match, [$jip->id => 'F', $sam->id => 'M']);
    $this->actingAs($ref)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);
    $this->actingAs($coach)->post(trackUrl($match, '/field'), ['team_member_id' => $sam->id]);
    Carbon::setTestNow('2026-10-10 09:31:00');
    $this->actingAs($ref)->post(trackUrl($match, '/goals'), ['scorer_team_member_id' => $jip->id]);

    $this->actingAs($coach)->get(trackUrl($match))->assertOk()
        ->assertDontSee('data-sm-goal-start', false)
        ->assertDontSee('/periods/start', false)
        ->assertSee('data-sm-bench', false)
        ->assertSee('data-sm-editable', false)
        ->assertSee('Time played')
        ->assertSee('Sam off');

    $this->actingAs($ref)->get(trackUrl($match))->assertOk()
        ->assertSee('data-sm-goal-start', false)
        ->assertSee('/periods/start', false)
        ->assertSee('data-sm-player="'.$jip->id.'"', false)
        ->assertDontSee('data-sm-bench', false)
        ->assertDontSee('data-sm-editable', false)
        ->assertDontSee('Time played')
        ->assertDontSee('Sam off');
    $this->actingAs($ref)->get("/sports-management/{$team->id}/matches/{$match->id}/report")->assertOk()
        ->assertSee('Jip')
        ->assertDontSee('Time played')
        ->assertDontSee('Sam off');
});

it('refuses kick-off while the lineup has open places and players on the bench', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $team->update(['format_preset_id' => \Kopling\SportsManagement\TeamFormatPreset::create(['name' => 'JO8', 'players_on_field' => 3])->id]);
    $jip = rosterMember($team, 'Jip');
    $sam = rosterMember($team, 'Sam');
    $noor = rosterMember($team, 'Noor');
    $match = plannedMatch($team);
    lineup($this, $coach, $match, [$jip->id => 'F', $sam->id => 'M']);

    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play'])->assertSessionHasErrors('lineup');
    expect($match->periods()->count())->toBe(0);

    $match->availabilities()->create(['team_member_id' => $noor->id, 'status' => 'absent']);
    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play'])->assertSessionHasNoErrors();
    expect($match->periods()->count())->toBe(1);
});

it('ignores the same live action repeated within seconds', function () {
    Carbon::setTestNow('2026-10-10 09:30:00');
    $coach = coach();
    $match = plannedMatch(staffedTeam($coach));

    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);
    $this->actingAs($coach)->post(trackUrl($match, '/goals'), ['opponent' => '1']);
    Carbon::setTestNow('2026-10-10 09:30:03');
    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);
    $this->actingAs($coach)->post(trackUrl($match, '/goals'), ['opponent' => '1']);

    expect($match->periods()->count())->toBe(1)->and($match->goals()->count())->toBe(1);

    Carbon::setTestNow('2026-10-10 09:30:10');
    $this->actingAs($coach)->post(trackUrl($match, '/goals'), ['opponent' => '1']);
    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'break']);
    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'break']);

    expect($match->periods()->count())->toBe(2)->and($match->goals()->count())->toBe(2);
});

it('drops a removed referee from their upcoming matches', function () {
    Carbon::setTestNow('2026-10-09 12:00:00');
    $coach = coach();
    $team = staffedTeam($coach);
    $ref = referee($team);
    $upcoming = assignReferee(plannedMatch($team), $ref);
    $past = assignReferee(plannedMatch($team, ['scheduled_at' => '2026-10-01 09:30']), $ref);

    $this->actingAs($coach)->post("/sports-management/{$team->id}/staff/{$ref->id}/remove")->assertRedirect();

    expect($upcoming->fresh()->referee_person_id)->toBeNull()
        ->and($past->fresh()->referee_person_id)->toBe($ref->id)
        ->and($past->fresh()->isRefereedBy($ref))->toBeFalse();
});

it('lets an owner change a staff member\'s role, never an owner\'s', function () {
    Carbon::setTestNow('2026-10-09 12:00:00');
    $owner = coach('Owner', 'owner@example.test');
    $team = staffedTeam($owner);
    $other = coach('Other', 'other@example.test');
    $team->staff()->attach($other);
    $ref = referee($team);
    $match = assignReferee(plannedMatch($team), $ref);

    $this->actingAs($owner)->get("/sports-management/{$team->id}")->assertOk()
        ->assertSee("/staff/{$ref->id}/role", false)
        ->assertDontSee("/staff/{$owner->id}/role", false);

    $this->actingAs($other)->post("/sports-management/{$team->id}/staff/{$ref->id}/role", ['role' => 'coach'])->assertForbidden();
    $this->actingAs($owner)->post("/sports-management/{$team->id}/staff/{$owner->id}/role", ['role' => 'referee'])->assertForbidden();

    $this->actingAs($owner)->post("/sports-management/{$team->id}/staff/{$other->id}/role", ['role' => 'referee'])->assertRedirect();
    $this->actingAs($owner)->post("/sports-management/{$team->id}/staff/{$ref->id}/role", ['role' => 'coach'])->assertRedirect();

    expect($team->roleOf($other))->toBe(StaffRole::Referee)
        ->and($team->roleOf($ref))->toBe(StaffRole::Coach)
        ->and($match->fresh()->referee_person_id)->toBeNull();
});
