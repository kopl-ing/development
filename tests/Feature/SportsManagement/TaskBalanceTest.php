<?php

declare(strict_types=1);

use Kopling\SportsManagement\TaskBalance;
use Kopling\SportsManagement\TaskEnlistment;
use Kopling\SportsManagement\TaskEnrolment;
use Kopling\SportsManagement\TaskOccurrences;
use Kopling\SportsManagement\TaskSchedule;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamMember;

beforeEach(function () {
    $this->travelTo('2026-11-20 12:00');
});

function datesTask(Team $team, array $dates, array $attributes = []): \Kopling\SportsManagement\Task
{
    $task = $team->tasks()->create($attributes + ['title' => 'Task', 'schedule' => TaskSchedule::Dates, 'enrolment' => TaskEnrolment::PerOccurrence]);
    $task->dates()->createMany(array_map(fn (string $date) => ['date' => $date], $dates));
    TaskOccurrences::sync($task);

    return $task;
}

function enlistOn(\Kopling\SportsManagement\Task $task, TeamMember $member, ?string $date = null, array $attributes = []): TaskEnlistment
{
    return TaskEnlistment::create($attributes + [
        'task_id' => $task->id,
        'occurrence_id' => $date === null ? null : $task->occurrences()->whereDate('date', $date)->sole()->id,
        'team_member_id' => $member->id,
    ]);
}

it('earns the task weight per passed date and per sign-up, lowest first', function () {
    $team = tasksTeam(coach());
    $daan = rosterMember($team, 'Daan de Kok');
    $jip = rosterMember($team, 'Jip Vos');
    $sem = rosterMember($team, 'Sem Gast');
    $sem->update(['guest' => true]);
    $drive = datesTask($team, ['2026-11-07', '2026-11-14', '2026-11-28'], ['title' => 'Drive', 'weight' => 2, 'effort_minutes' => 60, 'effort_cents' => 1000]);
    $goals = datesTask($team, ['2026-11-14'], ['title' => 'Goals']);

    enlistOn($drive, $daan, '2026-11-07');
    enlistOn($drive, $daan, '2026-11-07', ['note' => 'with a friend']);
    enlistOn($drive, $daan, '2026-11-28');
    enlistOn($goals, $jip, '2026-11-14');
    enlistOn($goals, $sem, '2026-11-14');

    $rows = TaskBalance::for($team);

    expect($rows->map(fn (array $row) => [$row['member']->person->name, $row['points'], $row['done'], $row['upcoming']])->all())
        ->toBe([['Jip Vos', 1, 1, 0], ['Daan de Kok', 4, 2, 1]])
        ->and($rows[1]['minutes'])->toBe(120)
        ->and($rows[1]['cents'])->toBe(2000);
});

it('counts a whole-task sign-up for each passed date it covered, and none that were cancelled', function () {
    $team = tasksTeam(coach());
    $daan = rosterMember($team, 'Daan de Kok');
    $this->travelTo('2026-11-01 12:00');
    $trainer = datesTask($team, ['2026-11-03', '2026-11-10', '2026-11-17', '2026-11-24'], ['enrolment' => TaskEnrolment::WholeTask, 'weight' => 3]);
    enlistOn($trainer, $daan, attributes: ['ended_on' => '2026-11-16']);
    $trainer->occurrences()->whereDate('date', '2026-11-03')->update(['cancelled_at' => now()]);
    $this->travelTo('2026-11-20 12:00');

    $row = TaskBalance::for($team)->sole();

    expect([$row['points'], $row['done'], $row['upcoming']])->toBe([3, 1, 0])
        ->and($row['items'][0]['whole'])->toBeTrue();
});

it('shows the balance to the team coaches only', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    rosterMember($team, 'Daan de Kok');
    $url = "/sports-management/{$team->id}/tasks/balance";

    $this->actingAs($coach)->get($url)->assertOk()->assertSee('<title>Balance · JO11-2</title>', false)->assertSee('Daan de Kok')->assertSee('0 points');
    $this->actingAs(referee($team))->get($url)->assertForbidden();

    $team->update(['tasks_enabled' => false]);
    $this->actingAs($coach)->get($url)->assertNotFound();
});

it('shows parents the balance through the link only when the team allows it', function () {
    $team = tasksTeam(coach());
    $daan = rosterMember($team, 'Daan de Kok');
    $task = datesTask($team, ['2026-11-14', '2026-12-05'], ['effort_cents' => 1500]);
    enlistOn($task, $daan, '2026-11-14');
    $player = '/live/team/'.$team->fresh()->tasks_token.'/tasks/'.$daan->id;

    $this->get($player)->assertOk()->assertDontSee('1 point');

    $team->update(['tasks_balance_public' => true]);
    $this->get($player)->assertSee('Balance')->assertSee('1 point')->assertDontSee('15.00')->assertDontSee('Daan de Kok');
});
