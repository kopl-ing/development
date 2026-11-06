<?php

declare(strict_types=1);

use Kopling\SportsManagement\TaskBoard;
use Kopling\SportsManagement\TaskEnrolment;

beforeEach(function () {
    $this->travelTo('2026-11-20 12:00');
});

it('finds dates in the coming week still under their minimum', function () {
    $team = tasksTeam(coach());
    $daan = rosterMember($team, 'Daan de Kok');
    $drive = datesTask($team, ['2026-11-19', '2026-11-22', '2026-11-25', '2026-11-30'], ['title' => 'Drive', 'min_people' => 2]);
    datesTask($team, ['2026-11-22'], ['title' => 'No minimum']);
    enlistOn($drive, $daan, '2026-11-25');
    enlistOn($drive, $daan, '2026-11-25');
    $this->travelTo('2026-11-01 12:00');
    $trainer = datesTask($team, ['2026-11-23'], ['title' => 'Trainer', 'enrolment' => TaskEnrolment::WholeTask, 'min_people' => 1]);
    enlistOn($trainer, $daan);
    $this->travelTo('2026-11-20 12:00');

    expect(TaskBoard::shortHanded([$team])->map(fn (array $row) => [$row['occurrence']->date->toDateString(), $row['count']])->all())
        ->toBe([['2026-11-22', 0]]);

    $team->update(['tasks_enabled' => false]);
    expect(TaskBoard::shortHanded([$team->fresh()]))->toBeEmpty();
});

it('warns coaches on the team page and in the sidebar, and parents on the link page', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    $daan = rosterMember($team, 'Daan de Kok');
    $task = datesTask($team, ['2026-11-22', '2026-12-20'], ['title' => 'Set up goals', 'min_people' => 2]);
    $soon = $task->occurrences()->whereDate('date', '2026-11-22')->sole();

    $this->actingAs($coach)->get("/sports-management/{$team->id}")
        ->assertSee('People needed this week')
        ->assertSee('#occurrence-'.$soon->id, false)
        ->assertSee('0 of at least 2');

    $this->actingAs($coach)->get('/sports-management')->assertSee('People needed this week')->assertSee('Set up goals');

    $this->actingAs($coach)->get("/sports-management/{$team->id}/tasks?filter=open")->assertSee('id="occurrence-'.$soon->id.'"', false);

    $this->get('/live/team/'.$team->fresh()->tasks_token.'/tasks/'.$daan->id)
        ->assertSee('People needed this week')
        ->assertSee('href="#occurrence-'.$soon->id.'"', false)
        ->assertSee('id="occurrence-'.$soon->id.'"', false);

    $this->actingAs(referee($team))->get('/sports-management')->assertDontSee('People needed this week');
});

it('lists the tasks still vacant for a match on its public page until kick-off, without a task link', function () {
    $team = tasksTeam(coach());
    $daan = rosterMember($team, 'Daan de Kok');
    $match = plannedMatch($team, ['scheduled_at' => '2026-11-22 09:30', 'public' => true]);
    $drive = matchTask($team, attributes: ['title' => 'Drive', 'min_people' => 3]);
    $oranges = matchTask($team, attributes: ['title' => 'Oranges']);
    $flag = matchTask($team, attributes: ['title' => 'Flag', 'min_people' => 1]);
    enlistOn($drive, $daan, '2026-11-22');
    enlistOn($flag, $daan, '2026-11-22');

    $this->get($match->fresh()->publicUrl())
        ->assertSee('Still needed for this match: Drive (1 of at least 3), Oranges (nobody yet).')
        ->assertDontSee('Flag (')
        ->assertDontSee($team->fresh()->tasks_token);

    $team->update(['tasks_enabled' => false]);
    $this->get($match->fresh()->publicUrl())->assertDontSee('Still needed for this match');
});
