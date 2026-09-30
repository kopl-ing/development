<?php

declare(strict_types=1);

use Kopling\Core\People\Person;
use Kopling\SportsManagement\Position;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamMember;

it('adds a roster member, creating a Person with no login credentials', function () {
    $coach = coach();
    $team = Team::create(['name' => 'JO11-2', 'club' => 'A', 'season' => '2026/2027']);
    $team->staff()->attach($coach);

    $this->actingAs($coach)
        ->post("/sports-management/{$team->id}/members", [
            'name' => 'Jip',
            'jersey_number' => '9',
            'positions' => ['M', 'F'],
        ])
        ->assertRedirect();

    $person = Person::where('name', 'Jip')->firstOrFail();
    expect($person->email)->toBeNull()
        ->and($person->password)->toBeNull();

    $member = TeamMember::where('person_id', $person->id)->firstOrFail();
    expect($member->team_id)->toBe($team->id)
        ->and($member->jersey_number)->toBe('9')
        ->and($member->positions->all())->toBe([Position::Midfield, Position::Forward])
        ->and($member->guest)->toBeFalse();
});

it('adds a guest roster member', function () {
    $coach = coach('Coach', 'guest-coach@example.test');
    $team = Team::create(['name' => 'JO11-2', 'club' => 'A', 'season' => '2026/2027']);
    $team->staff()->attach($coach);

    $this->actingAs($coach)
        ->post("/sports-management/{$team->id}/members", ['name' => 'Guest Player', 'guest' => '1'])
        ->assertRedirect();

    $member = TeamMember::whereHas('person', fn ($q) => $q->where('name', 'Guest Player'))->firstOrFail();
    expect($member->guest)->toBeTrue();
});

it('updates a roster member, including the underlying Person name', function () {
    $coach = coach('Coach', 'update-coach@example.test');
    $team = Team::create(['name' => 'JO11-2', 'club' => 'A', 'season' => '2026/2027']);
    $team->staff()->attach($coach);

    $person = Person::create(['name' => 'Old Name']);
    $member = TeamMember::create(['team_id' => $team->id, 'person_id' => $person->id]);

    $this->actingAs($coach)
        ->post("/sports-management/{$team->id}/members/{$member->id}", [
            'name' => 'New Name',
            'jersey_number' => '10',
        ])
        ->assertRedirect();

    expect($person->refresh()->name)->toBe('New Name')
        ->and($member->refresh()->jersey_number)->toBe('10');
});

it('deletes a roster member but keeps its underlying Person', function () {
    $coach = coach('Coach', 'delete-coach@example.test');
    $team = Team::create(['name' => 'JO11-2', 'club' => 'A', 'season' => '2026/2027']);
    $team->staff()->attach($coach);

    $person = Person::create(['name' => 'Doomed']);
    $member = TeamMember::create(['team_id' => $team->id, 'person_id' => $person->id]);

    $this->actingAs($coach)
        ->post("/sports-management/{$team->id}/members/{$member->id}/delete")
        ->assertRedirect();

    expect(TeamMember::find($member->id))->toBeNull()
        ->and(Person::find($person->id))->not->toBeNull();
});

it('forbids managing the roster of a team the acting person does not staff', function () {
    $owner = coach('Owner', 'roster-owner@example.test');
    $intruder = coach('Intruder', 'roster-intruder@example.test');

    $team = Team::create(['name' => 'JO11-2', 'club' => 'A', 'season' => '2026/2027']);
    $team->staff()->attach($owner);

    $this->actingAs($intruder)
        ->post("/sports-management/{$team->id}/members", ['name' => 'Someone'])
        ->assertForbidden();
});

it('rejects an unknown position', function () {
    $coach = coach('Coach', 'position-coach@example.test');
    $team = Team::create(['name' => 'JO11-2', 'club' => 'A', 'season' => '2026/2027']);
    $team->staff()->attach($coach);

    $this->actingAs($coach)
        ->post("/sports-management/{$team->id}/members", ['name' => 'Jip', 'positions' => ['X']])
        ->assertSessionHasErrors('positions.0');
});

it('sorts permanent players before guests, each by name, naturally and case-insensitively', function () {
    $team = staffedTeam(coach());
    $guest = rosterMember($team, 'Aad');
    $guest->update(['guest' => true]);
    rosterMember($team, 'speler 10');
    rosterMember($team, 'Speler 2');
    rosterMember($team, 'bram');

    expect(TeamMember::sorted($team->members()->with('person')->get())->map(fn ($member) => $member->person->name)->all())
        ->toBe(['bram', 'Speler 2', 'speler 10', 'Aad']);
});
