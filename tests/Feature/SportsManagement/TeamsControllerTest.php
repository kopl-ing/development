<?php

declare(strict_types=1);

use Kopling\Core\People\Group;
use Kopling\Core\People\Person;
use Kopling\SportsManagement\Sport;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamFormatPreset;
use Kopling\SportsManagement\TeamMember;

function coach(
    string $name = 'Coach',
    string $email = 'coach@example.test',
    array $permissions = ['access-sports-management', 'manage-teams', 'manage-matches', 'track-matches'],
): Person {
    $person = Person::create(['name' => $name, 'email' => $email, 'password' => 'secret']);

    $group = Group::create(['name' => "Coaches ($email)"]);
    foreach ($permissions as $permission) {
        $group->givePermissionTo("kopling-sports-management::$permission");
    }
    $person->groups()->attach($group);

    return $person;
}

it('denies a guest entirely', function () {
    $this->get('/sports-management')->assertForbidden();
});

it('denies a person without access-sports-management', function () {
    $person = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);

    $this->actingAs($person)->get('/sports-management')->assertForbidden();
});

it('creates a team and attaches the creator as staff', function () {
    $preset = TeamFormatPreset::create(['name' => 'JO11', 'players_on_field' => 8]);
    $person = coach();

    $this->actingAs($person)
        ->post('/sports-management', [
            'name' => 'JO11-2',
            'club' => 'SV Testers',
            'season' => '2026/2027',
            'sport' => 'football',
            'format_preset_id' => $preset->id,
        ])
        ->assertRedirect();

    $team = Team::where('name', 'JO11-2')->firstOrFail();
    expect($team->club)->toBe('SV Testers')
        ->and($team->sport)->toBe(\Kopling\SportsManagement\Sport::Football)
        ->and($team->season)->toBe('2026/2027')
        ->and($team->isOwnedBy($person))->toBeTrue();
});

it('only lists teams the acting person actually staffs', function () {
    $mine = coach('Mine', 'mine@example.test');
    $other = coach('Other', 'other@example.test');

    $myTeam = Team::create(['name' => 'Mine', 'club' => 'A', 'season' => '2026/2027']);
    $myTeam->staff()->attach($mine);

    $otherTeam = Team::create(['name' => 'Other', 'club' => 'B', 'season' => '2026/2027']);
    $otherTeam->staff()->attach($other);

    $html = $this->actingAs($mine)->get('/sports-management')->assertOk()->getContent();

    expect($html)->toContain('Mine')->and($html)->not->toContain('Other');
});

it('forbids viewing a team the acting person does not staff', function () {
    $mine = coach('Mine', 'mine2@example.test');
    $other = coach('Other', 'other2@example.test');

    $otherTeam = Team::create(['name' => 'Other', 'club' => 'B', 'season' => '2026/2027']);
    $otherTeam->staff()->attach($other);

    $this->actingAs($mine)->get("/sports-management/{$otherTeam->id}")->assertForbidden();
});

it('renders the team page with a roster member holding positions', function () {
    $coach = coach();
    $team = Team::create(['name' => 'JO11-2', 'club' => 'A', 'season' => '2026/2027']);
    $team->staff()->attach($coach, ['owner' => true]);
    $person = Person::create(['name' => 'Jip']);
    TeamMember::create(['team_id' => $team->id, 'person_id' => $person->id, 'positions' => ['K', 'D'], 'jersey_number' => '7']);

    $this->actingAs($coach)
        ->get("/sports-management/{$team->id}")
        ->assertOk()
        ->assertSeeInOrder(['Jip', '#7', 'title="Keeper"', 'title="Defender"'], false)
        ->assertSee(__('kopling-sports-management::messages.delete_team'));
});

it('deletes a team for good, including roster Person rows that have no login', function () {
    $coach = coach();
    $team = Team::create(['name' => 'JO11-2', 'club' => 'A', 'season' => '2026/2027']);
    $team->staff()->attach($coach, ['owner' => true]);
    $player = Person::create(['name' => 'Jip']);
    TeamMember::create(['team_id' => $team->id, 'person_id' => $player->id]);
    $account = Person::create(['name' => 'Sem', 'email' => 'sem@example.test', 'password' => 'secret']);
    TeamMember::create(['team_id' => $team->id, 'person_id' => $account->id]);

    $this->actingAs($coach)
        ->post("/sports-management/{$team->id}/delete")
        ->assertRedirect('/sports-management');

    expect(Team::withTrashed()->find($team->id))->toBeNull()
        ->and(TeamMember::where('team_id', $team->id)->exists())->toBeFalse()
        ->and(Person::find($player->id))->toBeNull()
        ->and(Person::find($account->id))->not->toBeNull();
});

it('lets only an owner delete the team', function () {
    $owner = coach('Owner', 'owner@example.test');
    $staff = coach('Staff', 'staff@example.test');
    $team = Team::create(['name' => 'JO11-2', 'club' => 'A', 'season' => '2026/2027']);
    $team->staff()->attach($owner, ['owner' => true]);
    $team->staff()->attach($staff);

    $this->actingAs($staff)->get("/sports-management/{$team->id}")
        ->assertDontSee(__('kopling-sports-management::messages.delete_team'));
    $this->actingAs($staff)->post("/sports-management/{$team->id}/delete")->assertForbidden();

    expect(Team::find($team->id))->not->toBeNull();
});

it('rate-limits creating teams', function () {
    $coach = coach();

    foreach (range(1, 10) as $number) {
        $this->actingAs($coach)->post('/sports-management', ['name' => "Team $number", 'club' => 'A', 'season' => '2026/2027'])->assertRedirect();
    }

    $this->actingAs($coach)->post('/sports-management', ['name' => 'One too many', 'club' => 'A', 'season' => '2026/2027'])->assertTooManyRequests();
});

it('counts permanent players and guests separately on the roster', function () {
    $coach = coach();
    $team = Team::create(['name' => 'JO11-2', 'club' => 'A', 'season' => '2026/2027']);
    $team->staff()->attach($coach);
    foreach (['Jip', 'Janneke'] as $name) {
        TeamMember::create(['team_id' => $team->id, 'person_id' => Person::create(['name' => $name])->id]);
    }
    TeamMember::create(['team_id' => $team->id, 'person_id' => Person::create(['name' => 'Gast'])->id, 'guest' => true]);

    $this->actingAs($coach)
        ->get("/sports-management/{$team->id}")
        ->assertSee('2 players')
        ->assertSee('1 guest');
});

it('lists the roster sorted by name', function () {
    $coach = coach();
    $team = Team::create(['name' => 'JO11-2', 'club' => 'A', 'season' => '2026/2027']);
    $team->staff()->attach($coach);
    foreach (['Sem', 'anna', 'Jip'] as $name) {
        TeamMember::create(['team_id' => $team->id, 'person_id' => Person::create(['name' => $name])->id]);
    }

    $this->actingAs($coach)
        ->get("/sports-management/{$team->id}")
        ->assertSeeInOrder(['anna', 'Jip', 'Sem']);
});

it('hides matches until the team has a roster, then shows them above it', function () {
    $coach = coach();
    $team = Team::create(['name' => 'JO11-2', 'club' => 'A', 'season' => '2026/2027']);
    $team->staff()->attach($coach);

    $this->actingAs($coach)->get("/sports-management/{$team->id}")
        ->assertSee('Add member')
        ->assertDontSee('Plan match');

    TeamMember::create(['team_id' => $team->id, 'person_id' => Person::create(['name' => 'Jip'])->id]);

    $this->actingAs($coach)->get("/sports-management/{$team->id}")
        ->assertSeeInOrder(['Plan match', 'Add member']);
});

it('only offers and accepts presets of the team\'s sport, and fixes the sport once it has matches', function () {
    $football = TeamFormatPreset::create(['name' => 'JO11', 'players_on_field' => 8]);
    $hockey = TeamFormatPreset::create(['sport' => 'hockey', 'name' => 'D 8-tal', 'players_on_field' => 8]);
    $person = coach();
    $team = ['name' => 'H1', 'club' => 'HC Test', 'season' => '2026/2027'];

    $this->actingAs($person)->get('/sports-management/sport-fields?sport=hockey')->assertOk()
        ->assertSee('D 8-tal')->assertDontSee('JO11')
        ->assertSee('hx-get', false);

    $this->actingAs($person)->post('/sports-management', $team + ['sport' => 'hockey', 'format_preset_id' => $football->id])
        ->assertSessionHasErrors('format_preset_id');
    $this->actingAs($person)->post('/sports-management', $team + ['sport' => 'curling'])->assertSessionHasErrors('sport');
    $this->actingAs($person)->post('/sports-management', $team + ['sport' => 'hockey', 'format_preset_id' => $hockey->id])->assertRedirect();

    $created = Team::where('name', 'H1')->firstOrFail();
    expect($created->sport->value)->toBe('hockey');

    plannedMatch($created);
    $this->actingAs($person)->post("/sports-management/{$created->id}", $team + ['sport' => 'football', 'format_preset_id' => $hockey->id])
        ->assertSessionHasNoErrors();
    expect($created->fresh()->sport->value)->toBe('hockey');
    $this->actingAs($person)->get("/sports-management/{$created->id}")->assertOk()->assertSee('Fixed once the team has matches.');

    $this->actingAs($person)->post("/sports-management/{$created->id}/matches", [
        'opponent_name' => 'HC Rivals', 'home_away' => 'home', 'scheduled_at' => '2026-10-10 09:30', 'format_preset_id' => $football->id,
    ])->assertSessionHasErrors('format_preset_id');
});

it('prefills the season from the date, lists sports alphabetically with football chosen, and marks what is required', function () {
    $person = coach();

    \Illuminate\Support\Carbon::setTestNow('2027-06-30 12:00');
    expect(Team::currentSeason())->toBe('2026/2027');
    \Illuminate\Support\Carbon::setTestNow('2027-07-01 12:00');
    expect(Team::currentSeason())->toBe('2027/2028');

    $this->actingAs($person)->get('/sports-management')->assertOk()
        ->assertSee('value="2027/2028"', false)
        ->assertSeeInOrder(['Basketball', 'Football', 'Handball', 'Hockey'])
        ->assertSee('<option value="football" selected>', false)
        ->assertSee('name="name" value="" placeholder="" class="input w-full" required', false)
        ->assertDontSee('name="club" value="" placeholder="" class="input w-full" required', false);

    $this->actingAs($person)->post('/sports-management', ['name' => 'JO11-2', 'season' => '2027/2028', 'sport' => 'football'])->assertSessionHasNoErrors();
    expect(Team::where('name', 'JO11-2')->value('club'))->toBeNull();
    $this->actingAs($person)->get('/sports-management')->assertSee('2027/2028')->assertDontSee('· 2027/2028');

    \Illuminate\Support\Carbon::setTestNow();
});

it('preselects the sport stored in the session when creating a team', function () {
    $person = coach();

    $this->actingAs($person)->withSession([Sport::SESSION_KEY => 'hockey'])->get('/sports-management')->assertOk()
        ->assertSee('<option value="hockey" selected>', false);

    $this->actingAs($person)->withSession([Sport::SESSION_KEY => 'curling'])->get('/sports-management')->assertOk()
        ->assertSee('<option value="football" selected>', false);
});

it('deletes a team, roster included, once its last staff member is deleted', function () {
    $coach = coach();
    $alone = staffedTeam($coach);
    $kid = rosterMember($alone, 'Kid');
    $shared = Team::create(['name' => 'Shared', 'club' => 'A', 'season' => '2026/2027']);
    $shared->staff()->attach($coach, ['owner' => true]);
    $shared->staff()->attach(coach('Other', 'other@example.test'));
    $hidden = Team::create(['name' => 'Hidden', 'club' => 'A', 'season' => '2026/2027']);
    $hidden->staff()->attach($coach, ['owner' => true]);
    $hidden->delete();

    $coach->delete();

    expect(Team::withTrashed()->find($alone->id))->toBeNull()
        ->and(Team::withTrashed()->find($hidden->id))->toBeNull()
        ->and(\Kopling\Core\People\Person::find($kid->person_id))->toBeNull()
        ->and(Team::find($shared->id))->not->toBeNull();
});
