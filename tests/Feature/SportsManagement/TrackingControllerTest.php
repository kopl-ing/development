<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Kopling\SportsManagement\MatchGoal;
use Kopling\SportsManagement\MatchState;
use Kopling\SportsManagement\PeriodType;
use Kopling\SportsManagement\Position;
use Kopling\SportsManagement\SubstitutionDirection;
use Kopling\SportsManagement\TeamFormatPreset;
use Kopling\SportsManagement\TeamMatch;

function trackUrl(TeamMatch $match, string $path = ''): string
{
    return "/sports-management/{$match->team_id}/matches/{$match->id}/track{$path}";
}

/**
 * @param array<string, string> $zones zone per team member id
 */
function lineup($test, $coach, TeamMatch $match, array $zones): void
{
    foreach ($zones as $memberId => $zone) {
        $test->actingAs($coach)
            ->post("/sports-management/{$match->team_id}/matches/{$match->id}/lineup", ['team_member_id' => $memberId, 'zone' => $zone])
            ->assertRedirect(trackUrl($match));
    }
}

afterEach(fn () => Carbon::setTestNow());

it('kicks off live with a starting lineup', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $jip = rosterMember($team, 'Jip');
    $sam = rosterMember($team, 'Sam');
    $match = plannedMatch($team);

    lineup($this, $coach, $match, [$jip->id => 'F']);

    $this->actingAs($coach)
        ->post(trackUrl($match, '/periods/start'), ['type' => 'play'])
        ->assertRedirect(trackUrl($match));

    $timeline = $match->fresh()->timeline();
    expect($timeline->state())->toBe(MatchState::Live)
        ->and($timeline->runningPeriod()->sequence)->toBe(1)
        ->and($timeline->onField())->toBe([$jip->id => Position::Forward]);
});

it('ends the running period when the next one starts', function () {
    Carbon::setTestNow('2026-10-10 09:30:00');
    $coach = coach();
    $match = plannedMatch(staffedTeam($coach));

    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);
    Carbon::setTestNow('2026-10-10 09:55:00');
    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'break']);

    $periods = $match->periods()->get();
    expect($periods)->toHaveCount(2)
        ->and($periods[0]->duration_seconds)->toBe(25 * 60)
        ->and($periods[1]->type)->toBe(PeriodType::Break)
        ->and($periods[1]->isRunning())->toBeTrue();
});

it('records goals live at the current match clock, and derives the score', function () {
    Carbon::setTestNow('2026-10-10 09:30:00');
    $coach = coach();
    $team = staffedTeam($coach);
    $jip = rosterMember($team, 'Jip');
    $sam = rosterMember($team, 'Sam');
    $match = plannedMatch($team);

    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);
    $period = $match->periods()->firstOrFail();

    Carbon::setTestNow('2026-10-10 09:42:10');
    $this->actingAs($coach)
        ->post(trackUrl($match, '/goals'), [
            'scorer_team_member_id' => $jip->id,
            'assist_team_member_id' => $sam->id,
        ])
        ->assertRedirect(trackUrl($match));

    $this->actingAs($coach)->post(trackUrl($match, '/goals'), ['minute' => 15, 'opponent' => '1']);
    $this->actingAs($coach)->post(trackUrl($match, '/goals'), ['minute' => 20]);

    $goal = MatchGoal::where('scorer_team_member_id', $jip->id)->firstOrFail();
    expect($goal->offset_seconds)->toBe(12 * 60 + 10)
        ->and($goal->assist_team_member_id)->toBe($sam->id)
        ->and($match->fresh()->timeline()->score())->toBe(['us' => 2, 'them' => 1]);
});

it('tallies goals and assists per player for the report', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $jip = rosterMember($team, 'Jip');
    $sam = rosterMember($team, 'Sam');
    $noor = rosterMember($team, 'Noor');
    $match = plannedMatch($team);
    $this->actingAs($coach)->post(trackUrl($match, '/periods'), ['type' => 'play', 'duration_minutes' => 25]);

    $goal = fn (array $data) => $this->actingAs($coach)->post(trackUrl($match, '/goals'), ['minute' => 5] + $data);
    $goal(['scorer_team_member_id' => $sam->id, 'assist_team_member_id' => $jip->id]);
    $goal(['scorer_team_member_id' => $jip->id, 'assist_team_member_id' => $sam->id]);
    $goal(['scorer_team_member_id' => $jip->id]);
    $goal(['assist_team_member_id' => $noor->id]);
    $goal(['opponent' => '1']);

    expect($match->fresh()->timeline()->contributions())->toBe([
        $jip->id => ['goals' => 2, 'assists' => 1],
        $sam->id => ['goals' => 1, 'assists' => 1],
        $noor->id => ['goals' => 0, 'assists' => 1],
    ]);

    $this->actingAs($coach)->get(trackUrl($match))->assertOk()
        ->assertSeeInOrder(['Goals and assists', 'Jip', '2 goals', '1 assist', 'Sam', '1 goal', 'Noor', '1 assist', 'Time played']);
});

it('requires a minute for events in a period that is not running', function () {
    $coach = coach();
    $match = plannedMatch(staffedTeam($coach));
    $period = $match->periods()->create(['sequence' => 1, 'type' => 'play', 'duration_seconds' => 1500]);

    $this->actingAs($coach)
        ->post(trackUrl($match, '/goals'), [])
        ->assertSessionHasErrors('minute');
});

it('rejects a scorer on an opponent goal', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $jip = rosterMember($team, 'Jip');
    $match = plannedMatch($team);
    $period = $match->periods()->create(['sequence' => 1, 'type' => 'play', 'duration_seconds' => 1500]);

    $this->actingAs($coach)
        ->post(trackUrl($match, '/goals'), ['minute' => 3, 'opponent' => '1', 'scorer_team_member_id' => $jip->id])
        ->assertSessionHasErrors('scorer_team_member_id');
});

it('rejects events for members of another team, or before any play period exists', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $outsider = rosterMember(staffedTeam($coach), 'Outsider');
    $match = plannedMatch($team);

    $this->actingAs($coach)
        ->post(trackUrl($match, '/goals'), ['minute' => 1])
        ->assertSessionHasErrors('minute');

    $match->periods()->create(['sequence' => 1, 'type' => 'play', 'duration_seconds' => 1500]);

    $this->actingAs($coach)
        ->post(trackUrl($match, '/substitutions'), ['minute' => 1, 'on_team_member_id' => $outsider->id, 'zone' => 'M'])
        ->assertSessionHasErrors('on_team_member_id');
});

it('computes time played across periods with rolling substitutions, entered afterwards', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $jip = rosterMember($team, 'Jip');
    $sam = rosterMember($team, 'Sam');
    $noor = rosterMember($team, 'Noor');
    $match = plannedMatch($team);

    lineup($this, $coach, $match, [$jip->id => 'D', $sam->id => 'M']);
    $this->actingAs($coach)->post(trackUrl($match, '/periods'), ['type' => 'play', 'duration_minutes' => 20]);
    $this->actingAs($coach)->post(trackUrl($match, '/periods'), ['type' => 'break', 'duration_minutes' => 5]);
    $this->actingAs($coach)->post(trackUrl($match, '/periods'), ['type' => 'play', 'duration_minutes' => 20]);
    $second = $match->periods()->where('sequence', 3)->firstOrFail();

    $sub = fn (int $minute, ?string $off, ?string $on) => $this->actingAs($coach)
        ->post(trackUrl($match, '/substitutions'), ['minute' => $minute, 'off_team_member_id' => $off, 'on_team_member_id' => $on, 'zone' => 'M'])
        ->assertRedirect(trackUrl($match));

    $sub(10, $jip->id, $noor->id);
    $sub(20, $sam->id, $jip->id);
    $sub(25, $noor->id, $sam->id);

    $timeline = $match->fresh()->timeline();
    expect($timeline->playedSeconds())->toEqual([
        $jip->id => 10 * 60 + 20 * 60,
        $sam->id => 20 * 60 + 15 * 60,
        $noor->id => 10 * 60 + 5 * 60,
    ])
        ->and(array_keys($timeline->onField()))->toEqualCanonicalizing([$jip->id, $sam->id])
        ->and($timeline->state())->toBe(MatchState::Ended)
        ->and($timeline->label($second))->toBe('Period 2');
});

it('corrects a period length after the fact and clamps events beyond it', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $jip = rosterMember($team, 'Jip');
    $match = plannedMatch($team);
    lineup($this, $coach, $match, [$jip->id => 'K']);
    $this->actingAs($coach)->post(trackUrl($match, '/periods'), ['type' => 'play', 'duration_minutes' => 20]);
    $period = $match->periods()->firstOrFail();

    $this->actingAs($coach)
        ->post(trackUrl($match, "/periods/{$period->id}"), ['type' => 'play', 'duration_minutes' => 25])
        ->assertRedirect();

    expect($match->fresh()->timeline()->playedSeconds()[$jip->id])->toBe(25 * 60);
});

it('refuses to delete a period that still has events', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $jip = rosterMember($team, 'Jip');
    $match = plannedMatch($team);
    lineup($this, $coach, $match, [$jip->id => 'K']);
    $this->actingAs($coach)->post(trackUrl($match, '/periods'), ['type' => 'play', 'duration_minutes' => 20]);
    $period = $match->periods()->firstOrFail();

    $this->actingAs($coach)
        ->post(trackUrl($match, "/periods/{$period->id}/delete"))
        ->assertSessionHasErrors('period');

    $substitution = $match->substitutions()->firstOrFail();
    expect($substitution->direction)->toBe(SubstitutionDirection::On);

    $this->actingAs($coach)->post(trackUrl($match, "/substitutions/{$substitution->id}/delete"))->assertRedirect();
    $this->actingAs($coach)->post(trackUrl($match, "/periods/{$period->id}/delete"))->assertRedirect();

    expect($match->periods()->count())->toBe(0);
});

it('renders the tracking page in each state', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    rosterMember($team, 'Jip');
    $match = plannedMatch($team);

    $this->actingAs($coach)->get(trackUrl($match))->assertOk()->assertSee('data-sm-editable', false)->assertSee('/lineup', false);

    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);
    $this->actingAs($coach)->post(trackUrl($match, '/goals'), ['minute' => 3, 'opponent' => '1']);
    $this->actingAs($coach)->get(trackUrl($match))->assertOk()->assertSee('x-data', false)->assertSee('0&ndash;1', false);

    $this->actingAs($coach)->post(trackUrl($match, "/periods/{$match->periods()->value('id')}/end"));
    $this->actingAs($coach)->get(trackUrl($match))->assertOk()->assertSee('Ended');

    $this->actingAs($coach)->get("/sports-management/{$team->id}/matches/{$match->id}")->assertOk();
    $this->actingAs($coach)->get("/sports-management/{$team->id}")->assertOk();
});

it('lets staff without track-matches view the report but not record events', function () {
    $viewer = coach('Viewer', 'track-viewer@example.test', ['access-sports-management']);
    $match = plannedMatch(staffedTeam($viewer));

    $this->actingAs($viewer)->get(trackUrl($match))->assertOk()->assertDontSee('data-sm-editable', false);
    $this->actingAs($viewer)->post(trackUrl($match, '/periods/start'), ['type' => 'play'])->assertForbidden();
});

it('forbids tracking a match of a team the acting person does not staff', function () {
    $match = plannedMatch(staffedTeam(coach('Owner', 'track-owner@example.test')));

    $this->actingAs(coach('Intruder', 'track-intruder@example.test'))
        ->post(trackUrl($match, '/periods/start'), ['type' => 'play'])
        ->assertForbidden();
});

it('swaps a bench player in for a field player live, taking over their zone', function () {
    Carbon::setTestNow('2026-10-10 09:30:00');
    $coach = coach();
    $team = staffedTeam($coach);
    $jip = rosterMember($team, 'Jip');
    $sam = rosterMember($team, 'Sam');
    $noor = rosterMember($team, 'Noor');
    $match = plannedMatch($team);
    lineup($this, $coach, $match, [$jip->id => 'F', $sam->id => 'D']);
    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);

    Carbon::setTestNow('2026-10-10 09:40:00');
    $this->actingAs($coach)
        ->post(trackUrl($match, '/field'), ['team_member_id' => $noor->id, 'replace_team_member_id' => $jip->id])
        ->assertRedirect(trackUrl($match));

    Carbon::setTestNow('2026-10-10 09:45:00');
    $this->actingAs($coach)->post(trackUrl($match, '/field'), ['team_member_id' => $sam->id, 'zone' => 'M']);

    $timeline = $match->fresh()->timeline();
    expect($timeline->onField())->toEqual([$sam->id => Position::Midfield, $noor->id => Position::Forward])
        ->and($timeline->playedSeconds())->toEqual([$jip->id => 10 * 60, $sam->id => 15 * 60, $noor->id => 5 * 60])
        ->and($match->substitutions()->where('offset_seconds', 10 * 60)->where('direction', 'off')->value('team_member_id'))->toBe($jip->id);
});

it('swaps zones when a field player is dropped on another field player', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $jip = rosterMember($team, 'Jip');
    $sam = rosterMember($team, 'Sam');
    $match = plannedMatch($team);
    lineup($this, $coach, $match, [$jip->id => 'F', $sam->id => 'K']);

    $this->actingAs($coach)
        ->post("/sports-management/{$team->id}/matches/{$match->id}/lineup", ['team_member_id' => $jip->id, 'replace_team_member_id' => $sam->id])
        ->assertRedirect();

    expect($match->lineup()->pluck('zone', 'team_member_id')->all())
        ->toEqual([$jip->id => Position::Keeper, $sam->id => Position::Forward]);

    $this->actingAs($coach)->post("/sports-management/{$team->id}/matches/{$match->id}/lineup", ['team_member_id' => $sam->id, 'zone' => null]);
    expect($match->lineup()->pluck('team_member_id')->all())->toBe([$jip->id]);
});

it('refuses lineup changes once the match has kicked off', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $jip = rosterMember($team, 'Jip');
    $match = plannedMatch($team);
    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);

    $this->actingAs($coach)
        ->post("/sports-management/{$team->id}/matches/{$match->id}/lineup", ['team_member_id' => $jip->id, 'zone' => 'F'])
        ->assertStatus(409);
});

it('renders the field with players in their zones and short initials', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $jip = rosterMember($team, 'Jip de Vries');
    rosterMember($team, 'Janneke Kok');
    rosterMember($team, 'Sam');
    $match = plannedMatch($team);
    lineup($this, $coach, $match, [$jip->id => 'D']);

    $this->actingAs($coach)->get(trackUrl($match))
        ->assertOk()
        ->assertSeeInOrder(['data-sm-zone="D"', 'data-sm-player="'.$jip->id.'"', 'JV', 'data-sm-zone="K"', 'data-sm-bench', 'JK', '>S<'], false);
});

it('leaves players marked absent off the tracking page and out of the lineup', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    rosterMember($team, 'Jip');
    $sam = rosterMember($team, 'Sam Absent');
    $match = plannedMatch($team);
    $match->availabilities()->create(['team_member_id' => $sam->id, 'status' => 'absent']);

    $this->actingAs($coach)->get(trackUrl($match))->assertOk()->assertSee('Jip')->assertDontSee('Sam Absent');

    $this->actingAs($coach)
        ->post("/sports-management/{$team->id}/matches/{$match->id}/lineup", ['team_member_id' => $sam->id, 'zone' => 'F'])
        ->assertSessionHasErrors('team_member_id');
});

it('runs one match clock that stands still during breaks, and maps match minutes onto periods', function () {
    Carbon::setTestNow('2026-10-10 09:30:00');
    $coach = coach();
    $match = plannedMatch(staffedTeam($coach));

    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);
    Carbon::setTestNow('2026-10-10 09:50:00');
    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'break']);
    Carbon::setTestNow('2026-10-10 09:52:00');
    $this->actingAs($coach)->get(trackUrl($match))->assertOk()
        ->assertSeeInOrder(['aria-label="Continue"', '<svg', 'x-data="{ base: 120,', '</button>'], false)
        ->assertDontSee('>Continue<', false);
    Carbon::setTestNow('2026-10-10 09:55:00');
    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);
    Carbon::setTestNow('2026-10-10 10:02:00');
    $this->actingAs($coach)->post(trackUrl($match, '/goals'), ['minute' => 23]);

    $timeline = $match->fresh()->timeline();
    $goal = $match->goals()->firstOrFail();
    expect($timeline->matchSeconds())->toBe(27 * 60)
        ->and($goal->period->sequence)->toBe(3)
        ->and($goal->offset_seconds)->toBe(3 * 60)
        ->and($timeline->matchSecond($goal->period, $goal->offset_seconds))->toBe(23 * 60);

    $this->actingAs($coach)->get(trackUrl($match))->assertOk()->assertSee('End match')->assertSee("23'")
        ->assertSeeInOrder(['aria-label="Break"', '<svg', 'x-data="{ base: 1620,', '</button>'], false);

    $running = $timeline->runningPeriod();
    $this->actingAs($coach)->post(trackUrl($match, "/periods/{$running->id}/end"));
    expect($match->fresh()->timeline()->state())->toBe(MatchState::Ended);
    $this->actingAs($coach)->get(trackUrl($match))->assertOk()->assertDontSee('End match')->assertSee('Resume match');

    Carbon::setTestNow('2026-10-10 10:30:00');
    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play'])->assertRedirect();
    $timeline = $match->fresh()->timeline();
    expect($timeline->state())->toBe(MatchState::Live)
        ->and($timeline->matchSeconds())->toBe(27 * 60);
});

it('deletes a tracked match from the tracking page, with everything recorded for it', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $jip = rosterMember($team, 'Jip');
    $match = plannedMatch($team);
    $match->availabilities()->create(['team_member_id' => $jip->id, 'status' => 'available']);
    lineup($this, $coach, $match, [$jip->id => 'F']);
    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);
    $this->actingAs($coach)->post(trackUrl($match, '/goals'), ['scorer_team_member_id' => $jip->id]);

    $this->actingAs($coach)->get(trackUrl($match))->assertSee(__('kopling-sports-management::messages.delete_match'));

    $this->actingAs($coach)
        ->post("/sports-management/{$team->id}/matches/{$match->id}/delete")
        ->assertRedirect("/sports-management/{$team->id}");

    expect(TeamMatch::find($match->id))->toBeNull();
    foreach (['sm_match_availabilities', 'sm_match_lineups', 'sm_match_periods', 'sm_match_substitutions', 'sm_match_goals'] as $table) {
        expect(DB::table($table)->where('match_id', $match->id)->count())->toBe(0);
    }
    expect($jip->fresh())->not->toBeNull();
});

it('allows only one keeper and at most the format\'s number of players on the field', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    [$a, $b, $c] = [rosterMember($team, 'Anna'), rosterMember($team, 'Bram'), rosterMember($team, 'Cas')];
    $preset = \Kopling\SportsManagement\TeamFormatPreset::create(['name' => 'JO7', 'players_on_field' => 2]);
    $match = plannedMatch($team, ['format_preset_id' => $preset->id]);
    $lineupUrl = "/sports-management/{$team->id}/matches/{$match->id}/lineup";

    lineup($this, $coach, $match, [$a->id => 'K']);
    $this->actingAs($coach)->post($lineupUrl, ['team_member_id' => $b->id, 'zone' => 'K'])->assertSessionHasErrors('zone');
    lineup($this, $coach, $match, [$b->id => 'D']);
    $this->actingAs($coach)->post($lineupUrl, ['team_member_id' => $c->id, 'zone' => 'F'])->assertSessionHasErrors('zone');
    $this->actingAs($coach)->post($lineupUrl, ['team_member_id' => $c->id, 'replace_team_member_id' => $a->id])->assertSessionHasNoErrors();

    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);
    $this->actingAs($coach)->post(trackUrl($match, '/field'), ['team_member_id' => $a->id, 'zone' => 'M'])->assertSessionHasErrors('zone');
    $this->actingAs($coach)->post(trackUrl($match, '/field'), ['team_member_id' => $b->id, 'zone' => 'K'])->assertSessionHasErrors('zone');

    expect($match->fresh()->timeline()->onField())->toEqual([$c->id => Position::Keeper, $b->id => Position::Defender]);
});

it('undoes the last field change or goal, only within the grace period', function () {
    Carbon::setTestNow('2026-10-10 09:30:00');
    $coach = coach();
    $team = staffedTeam($coach);
    $jip = rosterMember($team, 'Jip');
    $sam = rosterMember($team, 'Sam');
    $match = plannedMatch($team);
    lineup($this, $coach, $match, [$jip->id => 'F']);
    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);

    Carbon::setTestNow('2026-10-10 09:35:00');
    $this->actingAs($coach)->post(trackUrl($match, '/field'), ['team_member_id' => $sam->id, 'replace_team_member_id' => $jip->id]);
    $this->actingAs($coach)->get(trackUrl($match))->assertSee(__('kopling-sports-management::messages.undo'));
    $this->actingAs($coach)->post(trackUrl($match, '/undo'))->assertRedirect(trackUrl($match));
    expect($match->fresh()->timeline()->onField())->toEqual([$jip->id => Position::Forward]);

    $this->actingAs($coach)->post(trackUrl($match, '/goals'), ['scorer_team_member_id' => $jip->id]);
    Carbon::setTestNow('2026-10-10 09:37:00');
    $this->actingAs($coach)->get(trackUrl($match))->assertDontSee(__('kopling-sports-management::messages.undo'));
    $this->actingAs($coach)->post(trackUrl($match, '/undo'));
    expect($match->goals()->count())->toBe(1);
});

it('lists the bench by fewest minutes played once the match is on', function () {
    Carbon::setTestNow('2026-10-10 09:30:00');
    $coach = coach();
    $team = staffedTeam($coach);
    $anna = rosterMember($team, 'Anna');
    $zoe = rosterMember($team, 'Zoe');
    $match = plannedMatch($team);
    lineup($this, $coach, $match, [$anna->id => 'F']);
    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);
    Carbon::setTestNow('2026-10-10 09:40:00');
    $this->actingAs($coach)->post(trackUrl($match, '/field'), ['team_member_id' => $anna->id]);

    $this->actingAs($coach)->get(trackUrl($match))
        ->assertSeeInOrder(['data-sm-bench', 'data-sm-player="'.$zoe->id.'"', 'data-sm-player="'.$anna->id.'"'], false);
});

it('lists time played from most to least minutes, not by who is on the field', function () {
    Carbon::setTestNow('2026-10-10 09:30:00');
    $coach = coach();
    $team = staffedTeam($coach);
    rosterMember($team, 'Anna');
    $bob = rosterMember($team, 'Bob');
    $zoe = rosterMember($team, 'Zoe');
    $match = plannedMatch($team);
    lineup($this, $coach, $match, [$zoe->id => 'F']);
    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);
    Carbon::setTestNow('2026-10-10 09:40:00');
    $this->actingAs($coach)->post(trackUrl($match, '/field'), ['team_member_id' => $bob->id, 'replace_team_member_id' => $zoe->id])
        ->assertSessionHasNoErrors();
    Carbon::setTestNow('2026-10-10 09:42:00');

    $this->actingAs($coach)->get(trackUrl($match))
        ->assertSeeInOrder(['Time played', 'Zoe', 'Bob', 'Anna', 'Enter afterwards']);
});

it('puts the match controls in the portal top bar and drops the sidebar on the match screen only', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $match = plannedMatch($team);

    $this->actingAs($coach)->get(trackUrl($match))
        ->assertOk()
        ->assertSeeInOrder(['<header', 'data-sm-controls', __('kopling-sports-management::messages.kick_off'), 'flex-none', '</header>'], false)
        ->assertDontSee('id="sidebar"', false)
        ->assertDontSee('pb-16', false)
        ->assertDontSee('>Sports Management</span>', false);

    $this->actingAs($coach)->get("/sports-management/{$team->id}")
        ->assertOk()
        ->assertSee('id="sidebar"', false)
        ->assertSee('>Sports Management</span>', false)
        ->assertDontSee('data-sm-controls', false);
});

it('opts every match screen form out of browser form restoration', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    rosterMember($team, 'Jip');
    $match = plannedMatch($team);
    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);

    $html = $this->actingAs($coach)->get(trackUrl($match))->assertOk()->getContent();
    preg_match_all('/<form\b[^>]*>/', $html, $forms);
    $own = array_filter($forms[0], fn (string $form) => str_contains($form, 'data-sm-') || str_contains($form, '/sports-management/'));

    expect($own)->not->toBeEmpty()
        ->each->toContain('autocomplete="off"');
});

it('lists report events latest first', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $jip = rosterMember($team, 'Jip');
    $sam = rosterMember($team, 'Sam');
    $match = plannedMatch($team);
    $this->actingAs($coach)->post(trackUrl($match, '/periods'), ['type' => 'play', 'duration_minutes' => 20]);
    $this->actingAs($coach)->post(trackUrl($match, '/periods'), ['type' => 'play', 'duration_minutes' => 20]);
    $this->actingAs($coach)->post(trackUrl($match, '/goals'), ['minute' => 5, 'scorer_team_member_id' => $jip->id]);
    $this->actingAs($coach)->post(trackUrl($match, '/goals'), ['minute' => 30, 'scorer_team_member_id' => $sam->id]);

    $this->actingAs($coach)->get(trackUrl($match))
        ->assertSeeInOrder(['Period 2', "30'", 'Sam', 'Period 1', "5'", 'Jip']);
});

it('tints minutes-played badges from fewest (warning) to most (info) within the squad', function () {
    Carbon::setTestNow('2026-10-10 09:30:00');
    $coach = coach();
    $team = staffedTeam($coach);
    $anna = rosterMember($team, 'Anna');
    rosterMember($team, 'Zoe');
    $match = plannedMatch($team);
    lineup($this, $coach, $match, [$anna->id => 'F']);
    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);
    Carbon::setTestNow('2026-10-10 09:40:00');

    $this->actingAs($coach)->get(trackUrl($match))
        ->assertSeeInOrder(['data-sm-player="'.$anna->id.'"', 'var(--color-info) 100%'], false)
        ->assertSee('var(--color-warning) 100%', false);
});

it('turns a minutes-played badge green once the player reached their fair share of play time', function () {
    Carbon::setTestNow('2026-10-10 09:30:00');
    $coach = coach();
    $team = staffedTeam($coach);
    $team->update(['format_preset_id' => TeamFormatPreset::create(['name' => 'JO7', 'players_on_field' => 1, 'play_minutes' => 20])->id]);
    $anna = rosterMember($team, 'Anna');
    $zoe = rosterMember($team, 'Zoe');
    $match = plannedMatch($team);
    lineup($this, $coach, $match, [$anna->id => 'F']);
    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);
    Carbon::setTestNow('2026-10-10 09:40:00');

    $this->actingAs($coach)->get(trackUrl($match))
        ->assertSeeInOrder(['data-sm-player="'.$anna->id.'"', '--badge-color: var(--color-success)', 'data-sm-player="'.$zoe->id.'"', 'var(--color-warning) 100%'], false);
});

it('leaves players marked absent out of the squad the fair share is divided over', function () {
    Carbon::setTestNow('2026-10-10 09:30:00');
    $coach = coach();
    $team = staffedTeam($coach);
    $team->update(['format_preset_id' => TeamFormatPreset::create(['name' => 'JO10', 'players_on_field' => 6, 'play_minutes' => 50])->id]);
    $anna = rosterMember($team, 'Anna');
    foreach (range(2, 9) as $number) {
        rosterMember($team, "Player $number");
    }
    $absent = rosterMember($team, 'Absent');
    $match = plannedMatch($team);
    $match->availabilities()->create(['team_member_id' => $absent->id, 'status' => 'absent']);
    lineup($this, $coach, $match, [$anna->id => 'F']);
    $this->actingAs($coach)->get(trackUrl($match))->assertDontSee('Target play time');
    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);

    Carbon::setTestNow(Carbon::parse('2026-10-10 09:30:00')->addSeconds(1979));
    $this->actingAs($coach)->get(trackUrl($match))
        ->assertSeeInOrder(['data-sm-player="'.$anna->id.'"', '>= 1980'], false);

    Carbon::setTestNow(Carbon::parse('2026-10-10 09:30:00')->addSeconds(1980));
    $this->actingAs($coach)->get(trackUrl($match))
        ->assertSeeInOrder(['data-sm-player="'.$anna->id.'"', 'style="--badge-color: var(--color-success)'], false)
        ->assertSeeInOrder(['data-sm-bench', "Target play time: 33'"]);
});

it('replaces the history entry instead of pushing one for every match screen action', function () {
    $coach = coach();
    $match = plannedMatch(staffedTeam($coach));

    $this->actingAs($coach)->get(trackUrl($match))
        ->assertSeeInOrder(['data-sm-controls hx-replace-url:inherited="true"', 'hx-replace-url:inherited="true"', 'data-sm-field'], false);
});

it('names the position a player was moved onto in the event log', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $anna = rosterMember($team, 'Anna');
    $match = plannedMatch($team);
    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);

    $this->actingAs($coach)->post(trackUrl($match, '/field'), ['team_member_id' => $anna->id, 'zone' => 'M'])
        ->assertSessionHasNoErrors();

    $this->actingAs($coach)->get(trackUrl($match))->assertSee('Anna to Midfield');
});

it('counts bench minutes against the fair bench time', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $team->update(['format_preset_id' => TeamFormatPreset::create(['name' => 'Duo', 'players_on_field' => 1, 'play_minutes' => 40])->id]);
    $jip = rosterMember($team, 'Jip');
    $sam = rosterMember($team, 'Sam');
    $match = plannedMatch($team);
    lineup($this, $coach, $match, [$jip->id => 'F']);
    $this->actingAs($coach)->post(trackUrl($match, '/periods'), ['type' => 'play', 'duration_minutes' => 25]);

    $this->actingAs($coach)->get(trackUrl($match))->assertOk()
        ->assertSee("Target play time: 20&#039;, target bench time: 20&#039;", false)
        ->assertSeeInOrder(['data-sm-player="'.$jip->id.'"', ">25'<", 'data-sm-bench', 'data-sm-player="'.$sam->id.'"', ">played 0'<", 'indicator-bottom', 'var(--color-success)', ">benched 25'<"], false);
});

it('shows earlier bench minutes on players back on the field', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $team->update(['format_preset_id' => TeamFormatPreset::create(['name' => 'Duo', 'players_on_field' => 1, 'play_minutes' => 40])->id]);
    $jip = rosterMember($team, 'Jip');
    $sam = rosterMember($team, 'Sam');
    $match = plannedMatch($team);
    lineup($this, $coach, $match, [$jip->id => 'F']);
    $this->actingAs($coach)->post(trackUrl($match, '/periods'), ['type' => 'play', 'duration_minutes' => 25]);
    $this->actingAs($coach)->post(trackUrl($match, '/substitutions'), ['minute' => 10, 'off_team_member_id' => $jip->id, 'on_team_member_id' => $sam->id, 'zone' => 'F']);

    $this->actingAs($coach)->get(trackUrl($match))->assertOk()
        ->assertSeeInOrder(['data-sm-player="'.$sam->id.'"', ">15'<", 'indicator-bottom', ">10'<", 'data-sm-bench', 'data-sm-player="'.$jip->id.'"', ">played 10'<", 'indicator-bottom', ">benched 15'<"], false);
});

it('serves the report on its own page and links ended matches to it', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $jip = rosterMember($team, 'Jip');
    $match = plannedMatch($team);
    $report = "/sports-management/{$team->id}/matches/{$match->id}/report";

    $this->actingAs($coach)->get("/sports-management/{$team->id}/matches/{$match->id}")->assertOk()->assertDontSee($report, false);

    $this->actingAs($coach)->post(trackUrl($match, '/periods'), ['type' => 'play', 'duration_minutes' => 25]);
    $this->actingAs($coach)->from($report)
        ->post(trackUrl($match, '/goals'), ['minute' => 5, 'scorer_team_member_id' => $jip->id])
        ->assertRedirect($report);

    $this->actingAs($coach)->get($report)->assertOk()
        ->assertSeeInOrder(['FC Rivals', '1 &ndash; 0', 'Goals and assists', 'Jip', '1 goal'], false)
        ->assertDontSee('data-sm-field', false);
    $this->actingAs($coach)->get("/sports-management/{$team->id}/matches/{$match->id}")->assertOk()->assertSee($report, false);
    $this->actingAs($coach)->get("/sports-management/{$team->id}")->assertOk()->assertSee($report, false);
    $this->actingAs(coach('Other', 'other@example.test'))->get($report)->assertForbidden();
});

it('remembers where in a zone a player was put, before kick-off and live', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    [$anna, $bram, $cas, $dirk] = array_map(fn (string $name) => rosterMember($team, $name), ['Anna', 'Bram', 'Cas', 'Dirk']);
    $match = plannedMatch($team);
    $lineup = fn (array $data) => $this->actingAs($coach)->post("/sports-management/{$team->id}/matches/{$match->id}/lineup", $data)->assertRedirect(trackUrl($match));
    $field = fn (array $data) => $this->actingAs($coach)->post(trackUrl($match, '/field'), $data)->assertRedirect(trackUrl($match));
    $zoneOrder = fn (array $ids) => $this->actingAs($coach)->get(trackUrl($match))->assertOk()
        ->assertSeeInOrder(['data-sm-zone="M"', ...array_map(fn ($member) => 'data-sm-player="'.$member->id.'"', $ids), 'data-sm-zone="D"'], false);

    $lineup(['team_member_id' => $cas->id, 'zone' => 'M']);
    $lineup(['team_member_id' => $anna->id, 'zone' => 'M']);
    $lineup(['team_member_id' => $bram->id, 'zone' => 'M', 'before_team_member_id' => $cas->id]);
    $zoneOrder([$bram, $cas, $anna]);

    $lineup(['team_member_id' => $anna->id, 'zone' => 'M', 'before_team_member_id' => $bram->id]);
    $zoneOrder([$anna, $bram, $cas]);

    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);
    $zoneOrder([$anna, $bram, $cas]);

    $field(['team_member_id' => $cas->id, 'zone' => 'M', 'before_team_member_id' => $anna->id]);
    expect($match->substitutions()->count())->toBe(3)
        ->and(session()->has(\Kopling\SportsManagement\Controllers\TrackingController::undoKey($match)))->toBeFalse();
    $zoneOrder([$cas, $anna, $bram]);

    $field(['team_member_id' => $dirk->id, 'replace_team_member_id' => $anna->id]);
    $zoneOrder([$cas, $dirk, $bram]);

    $field(['team_member_id' => $anna->id, 'zone' => 'M', 'before_team_member_id' => $dirk->id]);
    $zoneOrder([$cas, $anna, $dirk, $bram]);
});

it('ignores a remembered slot once the player is in another zone', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    [$anna, $bram, $cas] = array_map(fn (string $name) => rosterMember($team, $name), ['Anna', 'Bram', 'Cas']);
    $match = plannedMatch($team);
    $lineup = fn (array $data) => $this->actingAs($coach)->post("/sports-management/{$team->id}/matches/{$match->id}/lineup", $data);

    $lineup(['team_member_id' => $anna->id, 'zone' => 'F']);
    $lineup(['team_member_id' => $cas->id, 'zone' => 'M']);
    $lineup(['team_member_id' => $bram->id, 'zone' => 'M', 'before_team_member_id' => $cas->id]);
    $match->lineup()->where('team_member_id', $bram->id)->update(['zone' => 'F']);

    expect($match->slots()->where('team_member_id', $bram->id)->value('slot'))->toBeLessThan($match->slots()->where('team_member_id', $anna->id)->value('slot'));
    $this->actingAs($coach)->get(trackUrl($match))->assertOk()
        ->assertSeeInOrder(['data-sm-zone="F"', 'data-sm-player="'.$anna->id.'"', 'data-sm-player="'.$bram->id.'"', 'data-sm-zone="M"'], false);
});

it('records an own goal by the opponent as ours, without a scorer', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $jip = rosterMember($team, 'Jip');
    $match = plannedMatch($team);
    lineup($this, $coach, $match, [$jip->id => 'F']);
    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);

    $this->actingAs($coach)->get(trackUrl($match))->assertOk()->assertSee('data-sm-goal-own', false)->assertSee('name="own_goal"', false);

    $this->actingAs($coach)->post(trackUrl($match, '/goals'), ['own_goal' => '1'])->assertRedirect(trackUrl($match));
    $this->actingAs($coach)->post(trackUrl($match, '/goals'), ['own_goal' => '1', 'scorer_team_member_id' => $jip->id])->assertSessionHasErrors('scorer_team_member_id');
    $this->actingAs($coach)->post(trackUrl($match, '/goals'), ['opponent' => '1', 'own_goal' => '1'])->assertRedirect(trackUrl($match));

    $timeline = $match->fresh()->timeline();
    expect($timeline->score())->toBe(['us' => 1, 'them' => 1])
        ->and($timeline->contributions())->toBe([])
        ->and(MatchGoal::where('opponent', true)->value('own_goal'))->toBeFalse();

    $this->actingAs($coach)->get("/sports-management/{$team->id}/matches/{$match->id}/report")->assertOk()
        ->assertSee('Own goal by FC Rivals JO11-1')
        ->assertSeeInOrder(['Goals and assists', 'No goals or assists yet.']);
});
