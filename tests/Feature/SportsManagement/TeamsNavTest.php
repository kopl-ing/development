<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Kopling\SportsManagement\Team;

afterEach(fn () => Carbon::setTestNow());

it('lists the staffed teams and their next unfinished matches in the sidebar, live ones linking to tracking', function () {
    Carbon::setTestNow('2026-10-10 09:00:00');
    $coach = coach();
    $team = staffedTeam($coach);
    Team::create(['name' => 'Not mine', 'club' => 'B', 'season' => '2026/2027']);
    $ended = plannedMatch($team, ['opponent_name' => 'Ended FC', 'scheduled_at' => '2026-10-10 08:00']);
    $live = plannedMatch($team, ['opponent_name' => 'Live FC', 'scheduled_at' => '2026-10-10 08:30']);
    plannedMatch($team, ['opponent_name' => 'Next FC', 'scheduled_at' => '2026-10-17 09:30']);
    plannedMatch($team, ['opponent_name' => 'Past FC', 'scheduled_at' => '2026-10-03 09:30']);

    $this->actingAs($coach)->post(trackUrl($ended, '/periods/start'), ['type' => 'play']);
    $this->actingAs($coach)->post(trackUrl($ended, '/periods/'.$ended->periods()->value('id').'/end'));
    $this->actingAs($coach)->post(trackUrl($live, '/periods/start'), ['type' => 'play']);

    $this->actingAs($coach)->get('/sports-management')
        ->assertSeeInOrder(['id="sidebar"', 'JO11-2', 'Upcoming', 'href="'.url(trackUrl($live)).'"', 'Live FC', 'Next FC'], false)
        ->assertDontSee('Not mine')
        ->assertDontSee('Ended FC')
        ->assertDontSee('Past FC');
});

it('leaves matches out of the sidebar for staff of more than a few teams', function () {
    Carbon::setTestNow('2026-10-10 09:00:00');
    $coach = coach();
    foreach (range(1, 4) as $number) {
        $team = Team::create(['name' => "Team $number", 'club' => 'A', 'season' => '2026/2027']);
        $team->staff()->attach($coach);
        plannedMatch($team, ['opponent_name' => "Opponent $number"]);
    }

    $this->actingAs($coach)->get('/sports-management')
        ->assertSeeInOrder(['id="sidebar"', 'Team 1', 'Team 4'], false)
        ->assertDontSee('Opponent 1');
});
