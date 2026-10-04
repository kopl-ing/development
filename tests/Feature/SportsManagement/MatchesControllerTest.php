<?php

declare(strict_types=1);

use Kopling\Core\People\Person;
use Kopling\SportsManagement\AvailabilityStatus;
use Kopling\SportsManagement\HomeAway;
use Kopling\SportsManagement\MatchAvailability;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamFormatPreset;
use Kopling\SportsManagement\TeamMatch;
use Kopling\SportsManagement\TeamMember;

function staffedTeam(Person $coach): Team
{
    $team = Team::create(['name' => 'JO11-2', 'club' => 'A', 'season' => '2026/2027']);
    $team->staff()->attach($coach, ['owner' => true]);

    return $team;
}

function rosterMember(Team $team, string $name): TeamMember
{
    return TeamMember::create(['team_id' => $team->id, 'person_id' => Person::create(['name' => $name])->id]);
}

function plannedMatch(Team $team, array $attributes = []): TeamMatch
{
    return $team->matches()->create($attributes + [
        'opponent_name' => 'FC Rivals JO11-1',
        'home_away' => HomeAway::Away,
        'scheduled_at' => '2026-10-10 09:30',
    ]);
}

it('plans a match for a staffed team', function () {
    $coach = coach();
    $team = staffedTeam($coach);

    $this->actingAs($coach)
        ->post("/sports-management/{$team->id}/matches", [
            'opponent_name' => 'FC Rivals JO11-1',
            'home_away' => 'away',
            'location_address' => "Sportpark Noord\nVeldweg 1, Utrecht",
            'scheduled_at' => '2026-10-10T09:30',
        ])
        ->assertRedirect();

    $match = TeamMatch::where('team_id', $team->id)->firstOrFail();
    expect($match->opponent_name)->toBe('FC Rivals JO11-1')
        ->and($match->home_away)->toBe(HomeAway::Away)
        ->and($match->location_address)->toBe("Sportpark Noord\nVeldweg 1, Utrecht")
        ->and($match->scheduled_at->format('Y-m-d H:i'))->toBe('2026-10-10 09:30')
        ->and($match->format_preset_id)->toBeNull();
});

it('rejects an invalid home/away value', function () {
    $coach = coach();
    $team = staffedTeam($coach);

    $this->actingAs($coach)
        ->post("/sports-management/{$team->id}/matches", [
            'opponent_name' => 'FC Rivals',
            'home_away' => 'neutral',
            'scheduled_at' => '2026-10-10T09:30',
        ])
        ->assertSessionHasErrors('home_away');
});

it('inherits the team format preset unless the match overrides it', function () {
    $jo11 = TeamFormatPreset::create(['name' => 'JO11', 'players_on_field' => 8]);
    $jo13 = TeamFormatPreset::create(['name' => 'JO13', 'players_on_field' => 11]);
    $team = staffedTeam(coach());
    $team->update(['format_preset_id' => $jo11->id]);

    expect(plannedMatch($team)->effectiveFormatPreset()->is($jo11))->toBeTrue()
        ->and(plannedMatch($team, ['format_preset_id' => $jo13->id])->effectiveFormatPreset()->is($jo13))->toBeTrue();
});

it('takes play minutes from the match, falling back to the format, and shares them over the squad', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $team->update(['format_preset_id' => TeamFormatPreset::create(['name' => 'JO11', 'players_on_field' => 8, 'play_minutes' => 60])->id]);
    $overridden = plannedMatch($team, ['play_minutes' => 50]);
    $jo10 = plannedMatch($team, ['format_preset_id' => TeamFormatPreset::create(['name' => 'JO10', 'players_on_field' => 6, 'play_minutes' => 50])->id]);

    expect(plannedMatch($team)->fairShareSeconds(10))->toBe(48 * 60)
        ->and($overridden->effectivePlayMinutes())->toBe(50)
        ->and($overridden->fairShareSeconds(6))->toBe(50 * 60)
        ->and(plannedMatch($team)->fairBenchSeconds(10))->toBe(12 * 60)
        ->and($overridden->fairBenchSeconds(6))->toBe(0)
        ->and($jo10->fairShareSeconds(7))->toBe(42 * 60)
        ->and($jo10->fairBenchSeconds(7))->toBe(8 * 60)
        ->and(plannedMatch(staffedTeam($coach), ['play_minutes' => 60])->fairShareSeconds(10))->toBeNull()
        ->and(plannedMatch(staffedTeam($coach), ['play_minutes' => 60])->fairBenchSeconds(10))->toBeNull();
});

it('updates and deletes a match', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $match = plannedMatch($team);

    $this->actingAs($coach)
        ->post("/sports-management/{$team->id}/matches/{$match->id}", [
            'opponent_name' => 'FC Other',
            'home_away' => 'home',
            'scheduled_at' => '2026-10-17T10:00',
        ])
        ->assertRedirect();

    expect($match->refresh()->opponent_name)->toBe('FC Other')
        ->and($match->home_away)->toBe(HomeAway::Home);

    $this->actingAs($coach)
        ->post("/sports-management/{$team->id}/matches/{$match->id}/delete")
        ->assertRedirect("/sports-management/{$team->id}");

    expect(TeamMatch::find($match->id))->toBeNull();
});

it('renders the team and match pages', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    rosterMember($team, 'Jip');
    $match = plannedMatch($team);

    $this->actingAs($coach)->get("/sports-management/{$team->id}")
        ->assertOk()
        ->assertSee('FC Rivals JO11-1');

    $this->actingAs($coach)->get("/sports-management/{$team->id}/matches/{$match->id}")
        ->assertOk()
        ->assertSee('Jip')
        ->assertSee('availability[', false);
});

it('marks availability as a full-state submit, clearing omitted members', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $jip = rosterMember($team, 'Jip');
    $janneke = rosterMember($team, 'Janneke');
    $sam = rosterMember($team, 'Sam');
    $match = plannedMatch($team);

    $this->actingAs($coach)
        ->post("/sports-management/{$team->id}/matches/{$match->id}/availability", [
            'availability' => [$jip->id => 'available', $janneke->id => 'absent', $sam->id => 'maybe'],
        ])
        ->assertRedirect();

    $this->actingAs($coach)
        ->post("/sports-management/{$team->id}/matches/{$match->id}/availability", [
            'availability' => [$jip->id => 'available', $janneke->id => 'maybe', $sam->id => ''],
        ])
        ->assertRedirect();

    $statuses = $match->availabilities()->get()->pluck('status', 'team_member_id');
    expect($statuses)->toHaveCount(2)
        ->and($statuses[$jip->id])->toBe(AvailabilityStatus::Available)
        ->and($statuses[$janneke->id])->toBe(AvailabilityStatus::Maybe);
});

it('ignores availability for members of another team', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $outsider = rosterMember(Team::create(['name' => 'Other', 'club' => 'B', 'season' => '2026/2027']), 'Outsider');
    $match = plannedMatch($team);

    $this->actingAs($coach)
        ->post("/sports-management/{$team->id}/matches/{$match->id}/availability", [
            'availability' => [$outsider->id => 'available'],
        ])
        ->assertRedirect();

    expect(MatchAvailability::count())->toBe(0);
});

it('rejects an unknown availability status', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $jip = rosterMember($team, 'Jip');
    $match = plannedMatch($team);

    $this->actingAs($coach)
        ->post("/sports-management/{$team->id}/matches/{$match->id}/availability", [
            'availability' => [$jip->id => 'injured'],
        ])
        ->assertSessionHasErrors("availability.{$jip->id}");
});

it('forbids planning matches for a team the acting person does not staff', function () {
    $team = staffedTeam(coach('Owner', 'match-owner@example.test'));

    $this->actingAs(coach('Intruder', 'match-intruder@example.test'))
        ->post("/sports-management/{$team->id}/matches", [
            'opponent_name' => 'FC Rivals',
            'home_away' => 'home',
            'scheduled_at' => '2026-10-10T09:30',
        ])
        ->assertForbidden();
});

it('404s a match addressed through the wrong team', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $otherTeam = staffedTeam($coach);
    $match = plannedMatch($otherTeam);

    $this->actingAs($coach)
        ->get("/sports-management/{$team->id}/matches/{$match->id}")
        ->assertNotFound();
});

it('requires manage-matches to plan, but not to view, a staffed team\'s matches', function () {
    $viewer = coach('Viewer', 'viewer@example.test', ['access-sports-management', 'manage-teams']);
    $team = staffedTeam($viewer);
    $match = plannedMatch($team);

    $this->actingAs($viewer)
        ->get("/sports-management/{$team->id}/matches/{$match->id}")
        ->assertOk()
        ->assertDontSee('availability[', false);

    $this->actingAs($viewer)
        ->post("/sports-management/{$team->id}/matches/{$match->id}/availability", ['availability' => []])
        ->assertForbidden();
});

it('lets a manage-matches-only staff member view the roster without editing it', function () {
    $planner = coach('Planner', 'planner@example.test', ['access-sports-management', 'manage-matches']);
    $team = staffedTeam($planner);

    $this->actingAs($planner)->get("/sports-management/{$team->id}")
        ->assertOk()
        ->assertDontSee(__('kopling-sports-management::messages.add_member'));

    $this->actingAs($planner)
        ->post("/sports-management/{$team->id}/members", ['name' => 'Someone'])
        ->assertForbidden();
});

it('offers availability as three icon buttons, with no answer shown as none selected', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $jip = rosterMember($team, 'Jip');
    $match = plannedMatch($team);

    $html = $this->actingAs($coach)->get("/sports-management/{$team->id}/matches/{$match->id}")->assertOk()->getContent();

    expect(substr_count($html, 'name="availability['.$jip->id.']"'))->toBe(3)
        ->and($html)->not->toContain('name="availability['.$jip->id.']" value=""')
        ->and($html)->not->toMatch('/name="availability\[[^"]+\]"[^>]*checked/')
        ->and(substr_count($html, 'join-item btn btn-sm btn-square'))->toBe(3)
        ->and($html)->toContain(svg('fas-check', '', ['width' => '1em', 'height' => '1em'])->toHtml());
});

it('shows the available count in red until enough players are available, maybe and absent not counting', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $team->update(['format_preset_id' => TeamFormatPreset::create(['name' => 'JO7', 'players_on_field' => 2])->id]);
    $jip = rosterMember($team, 'Jip');
    $sam = rosterMember($team, 'Sam');
    $match = plannedMatch($team);
    $url = "/sports-management/{$team->id}/matches/{$match->id}";

    $this->actingAs($coach)->post("{$url}/availability", ['availability' => [$jip->id => 'available', $sam->id => 'maybe']]);
    $this->actingAs($coach)->get($url)->assertSee('badge badge-error', false)->assertDontSee('badge badge-success', false);

    $this->actingAs($coach)->post("{$url}/availability", ['availability' => [$jip->id => 'available', $sam->id => 'available']]);
    $this->actingAs($coach)->get($url)->assertSee('badge badge-success', false)->assertDontSee('badge badge-error', false);
});

it('puts edit and delete in the more-actions menu, and track below the match details', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $match = plannedMatch($team, ['location_address' => 'Sportpark Noord']);

    $this->actingAs($coach)->get("/sports-management/{$team->id}/matches/{$match->id}")
        ->assertSeeInOrder([
            __('kopling-sports-management::messages.more_actions'),
            __('kopling-sports-management::messages.edit_match'),
            __('kopling-sports-management::messages.delete_match'),
            'FC Rivals JO11-1',
            'Sportpark Noord',
            __('kopling-sports-management::messages.track_match'),
            __('kopling-sports-management::messages.availability'),
        ]);
});

it('seeds the KNVB formats as football presets with their breaks', function () {
    $this->artisan('kopling:sports-management:seed-knvb-presets')->assertSuccessful();

    $jo11 = TeamFormatPreset::where('name', 'JO11')->firstOrFail();
    expect($jo11->sport)->toBe(\Kopling\SportsManagement\Sport::Football)
        ->and($jo11->breaks)->toBe(3)
        ->and(TeamFormatPreset::where('name', 'JO13')->value('breaks'))->toBe(1)
        ->and(TeamFormatPreset::where('name', 'JO7')->value('breaks'))->toBeNull();
});
