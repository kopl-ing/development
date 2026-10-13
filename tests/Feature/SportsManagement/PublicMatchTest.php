<?php

declare(strict_types=1);

use Kopling\SportsManagement\TeamMatch;

it('keeps a match private unless the team defaults to public', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $payload = ['opponent_name' => 'FC Rivals JO11-1', 'home_away' => 'home', 'scheduled_at' => '2026-10-10T09:30'];

    $this->actingAs($coach)->post("/sports-management/{$team->id}/matches", $payload)->assertRedirect();
    $private = TeamMatch::latest('id')->firstOrFail();

    $this->actingAs($coach)->post("/sports-management/{$team->id}", ['name' => $team->name, 'season' => $team->season, 'public_matches' => '1'])->assertRedirect();
    $this->actingAs($coach)->post("/sports-management/{$team->id}/matches", ['opponent_name' => 'VV Later'] + $payload)->assertRedirect();
    $public = TeamMatch::where('opponent_name', 'VV Later')->firstOrFail();

    expect($team->fresh()->public_matches)->toBeTrue()
        ->and($private->public)->toBeFalse()
        ->and($private->public_token)->toBeNull()
        ->and($public->public)->toBeTrue()
        ->and($public->public_token)->toHaveLength(40);
});

it('lets a match opt out of the team default, and back in on the same link', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $team->update(['public_matches' => true]);

    $this->actingAs($coach)->post("/sports-management/{$team->id}/matches", [
        'opponent_name' => 'FC Rivals JO11-1', 'home_away' => 'home', 'scheduled_at' => '2026-10-10T09:30', 'public' => '0',
    ])->assertRedirect();
    $match = TeamMatch::where('team_id', $team->id)->firstOrFail();
    expect($match->public)->toBeFalse();

    $match->update(['public' => true]);
    $url = $match->publicUrl();
    $match->update(['public' => false]);
    $this->get($url)->assertNotFound();

    $match->update(['public' => true]);
    expect($match->fresh()->publicUrl())->toBe($url);
    $this->get($url)->assertOk();
});

it('shows guests the score and initials, never names', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $jip = rosterMember($team, 'Jip Jansen');
    $sam = rosterMember($team, 'Sam de Vries');
    $match = plannedMatch($team, ['public' => true]);

    $this->actingAs($coach)->post("/sports-management/{$team->id}/matches/{$match->id}/lineup", ['team_member_id' => $jip->id, 'zone' => 'F']);
    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);
    $this->actingAs($coach)->post(trackUrl($match, '/goals'), ['scorer_team_member_id' => $jip->id, 'assist_team_member_id' => $sam->id]);
    auth()->logout();

    $this->get($match->fresh()->publicUrl())
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('JO11-2')
        ->assertSee('title="J"', false)
        ->assertSee('title="S"', false)
        ->assertSee('every 15s', false)
        ->assertDontSee('Jip')
        ->assertDontSee('Jansen')
        ->assertDontSee('Sam')
        ->assertDontSee('Vries')
        ->assertDontSee('Coach');
});

it('hides private matches, unknown tokens, and deleted teams', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $match = plannedMatch($team, ['public' => true]);
    $url = $match->publicUrl();

    $this->get($url)->assertOk();
    $this->get('/live/'.str_repeat('a', 40))->assertNotFound();

    $team->delete();
    $this->get($url)->assertNotFound();
});
