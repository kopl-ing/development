<?php

declare(strict_types=1);

use Kopling\Core\People\Group;
use Kopling\Core\People\Person;
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
            'format_preset_id' => $preset->id,
        ])
        ->assertRedirect();

    $team = Team::where('name', 'JO11-2')->firstOrFail();
    expect($team->club)->toBe('SV Testers')
        ->and($team->season)->toBe('2026/2027')
        ->and($team->isStaffedBy($person))->toBeTrue();
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

it('adds an existing account as staff by email', function () {
    $owner = coach('Owner', 'owner@example.test');
    $newStaff = coach('New Staff', 'new-staff@example.test');

    $team = Team::create(['name' => 'JO9-1', 'club' => 'A', 'season' => '2026/2027']);
    $team->staff()->attach($owner);

    $this->actingAs($owner)
        ->post("/sports-management/{$team->id}/staff", ['email' => 'new-staff@example.test'])
        ->assertRedirect();

    expect($team->isStaffedBy($newStaff))->toBeTrue();
});

it('rejects adding staff for an email with no account', function () {
    $owner = coach('Owner', 'owner2@example.test');
    $team = Team::create(['name' => 'JO9-1', 'club' => 'A', 'season' => '2026/2027']);
    $team->staff()->attach($owner);

    $this->actingAs($owner)
        ->post("/sports-management/{$team->id}/staff", ['email' => 'nobody@example.test'])
        ->assertSessionHasErrors('email');
});

it('refuses to remove the last remaining staff member', function () {
    $owner = coach('Owner', 'owner3@example.test');
    $team = Team::create(['name' => 'JO9-1', 'club' => 'A', 'season' => '2026/2027']);
    $team->staff()->attach($owner);

    $this->actingAs($owner)
        ->post("/sports-management/{$team->id}/staff/{$owner->id}/remove")
        ->assertSessionHasErrors('staff');

    expect($team->isStaffedBy($owner))->toBeTrue();
});

it('renders the team page with a roster member holding positions', function () {
    $coach = coach();
    $team = Team::create(['name' => 'JO11-2', 'club' => 'A', 'season' => '2026/2027']);
    $team->staff()->attach($coach);
    $person = Person::create(['name' => 'Jip']);
    TeamMember::create(['team_id' => $team->id, 'person_id' => $person->id, 'positions' => ['K', 'D'], 'jersey_number' => '7']);

    $this->actingAs($coach)
        ->get("/sports-management/{$team->id}")
        ->assertOk()
        ->assertSeeInOrder(['Jip', '#7', 'title="Keeper"', 'title="Defender"'], false)
        ->assertSee(__('kopling-sports-management::messages.delete_team'));
});

it('deletes a team without deleting its roster members\' Person rows', function () {
    $coach = coach();
    $team = Team::create(['name' => 'JO11-2', 'club' => 'A', 'season' => '2026/2027']);
    $team->staff()->attach($coach);
    $person = Person::create(['name' => 'Jip']);
    TeamMember::create(['team_id' => $team->id, 'person_id' => $person->id]);

    $this->actingAs($coach)
        ->post("/sports-management/{$team->id}/delete")
        ->assertRedirect('/sports-management');

    expect(Team::find($team->id))->toBeNull()
        ->and(TeamMember::where('person_id', $person->id)->exists())->toBeFalse()
        ->and(Person::find($person->id))->not->toBeNull();
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
