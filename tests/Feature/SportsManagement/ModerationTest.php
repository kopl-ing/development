<?php

declare(strict_types=1);

use Kopling\Core\Extension\Manager;
use Kopling\Core\People\Group;
use Kopling\Core\People\Person;
use Kopling\Moderation\Flag;
use Kopling\SportsManagement\Team;

function teamModerator(): Person
{
    $moderator = Person::create(['name' => 'Moderator', 'email' => 'team-mod@example.test', 'password' => 'secret']);
    $group = Group::create(['name' => 'Team moderators']);
    $group->givePermissionTo('kopling-moderation::moderate');
    $moderator->groups()->attach($group);

    return $moderator;
}

function teamModerationType(): string
{
    return app(Manager::class)->moderationTargets()->first(fn ($target) => $target->model === Team::class)->alias;
}

it('shows moderators every team with metadata only, never roster names', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    rosterMember($team, 'Jip Jansen');
    $team->delete();

    $this->actingAs(teamModerator())->get('/moderation/sports-management')
        ->assertOk()
        ->assertSee('JO11-2')
        ->assertSee('coach@example.test')
        ->assertSee('1 player')
        ->assertSee(__('kopling-moderation::moderation.hidden'))
        ->assertDontSee('Jip Jansen');
});

it('keeps the teams overview inside the moderate permission', function () {
    $this->actingAs(coach())->get('/moderation/sports-management')->assertForbidden();
});

it('lets staff report their team, showing its roster and matches in the queue', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    rosterMember($team, 'Jip Jansen');
    plannedMatch($team, ['opponent_name' => 'FC Rude Words']);

    $this->actingAs($coach)->get("/sports-management/{$team->id}")->assertSee(__('kopling-moderation::moderation.report'));
    $this->actingAs($coach)->post('/_xhr/kopling-moderation/'.teamModerationType()."/{$team->id}", ['reason' => 'inappropriate'])
        ->assertSessionHasNoErrors();

    expect(Flag::where('flaggable_id', $team->id)->exists())->toBeTrue();
    $this->actingAs(teamModerator())->get('/moderation')
        ->assertSee('JO11-2')
        ->assertSee('Jip Jansen')
        ->assertSee('FC Rude Words')
        ->assertSee(__('kopling-moderation::moderation.sanction'));
});

it('hides a team from its staff until unhidden, and deletes it with its roster people', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $player = rosterMember($team, 'Jip');
    $moderator = teamModerator();
    $url = '/_xhr/kopling-moderation/'.teamModerationType()."/{$team->id}";

    $this->actingAs($moderator)->post("$url/hide", ['reason' => 'spam'])->assertRedirect();
    expect(Team::withTrashed()->find($team->id)->deleted_by)->toBe($moderator->id);
    $this->actingAs($coach)->get("/sports-management/{$team->id}")->assertNotFound();

    $this->actingAs($moderator)->post("$url/unhide")->assertRedirect();
    $this->actingAs($coach)->get("/sports-management/{$team->id}")->assertOk();

    $this->actingAs($moderator)->post("$url/delete")->assertRedirect();
    expect(Team::withTrashed()->find($team->id))->toBeNull()
        ->and(Person::find($player->person_id))->toBeNull();
});
