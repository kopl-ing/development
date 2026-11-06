<?php

declare(strict_types=1);

use Kopling\SportsManagement\Task;
use Kopling\SportsManagement\TaskBalance;
use Kopling\SportsManagement\TaskEnlistment;
use Kopling\SportsManagement\TaskEnrolment;
use Kopling\SportsManagement\TaskOccurrence;
use Kopling\SportsManagement\TaskOccurrences;
use Kopling\SportsManagement\TaskSchedule;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamPeriod;

function weeklyTask(Team $team, array $attributes = []): Task
{
    $task = $team->tasks()->create($attributes + [
        'title' => 'Training', 'schedule' => TaskSchedule::Recurring, 'enrolment' => TaskEnrolment::PerOccurrence,
        'recurrence' => ['frequency' => 'weekly', 'interval' => 1, 'weekday' => 3],
        'starts_on' => '2026-12-01', 'ends_on' => '2027-01-31', 'within_periods' => true,
    ]);
    TaskOccurrences::sync($task);

    return $task;
}

function occurrenceDates(Task $task): array
{
    return $task->occurrences()->get()->map(fn (TaskOccurrence $occurrence) => $occurrence->date->toDateString())->all();
}

it('only generates repeating dates within the team\'s periods, and follows period edits', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    $this->actingAs($coach)->post("/sports-management/{$team->id}/periods", ['name' => 'Autumn', 'starts_on' => '2026-08-15', 'ends_on' => '2026-12-12'])->assertRedirect();
    $this->actingAs($coach)->post("/sports-management/{$team->id}/periods", ['name' => 'Spring', 'starts_on' => '2027-01-20', 'ends_on' => '2027-05-31'])->assertRedirect();
    $task = weeklyTask($team);

    expect(occurrenceDates($task))->toBe(['2026-12-02', '2026-12-09', '2027-01-20', '2027-01-27']);

    $spring = TeamPeriod::where('name', 'Spring')->sole();
    $this->actingAs($coach)->post("/sports-management/{$team->id}/periods/{$spring->id}", ['name' => 'Spring', 'starts_on' => '2027-01-10', 'ends_on' => '2027-05-31'])->assertRedirect();
    expect(occurrenceDates($task))->toBe(['2026-12-02', '2026-12-09', '2027-01-13', '2027-01-20', '2027-01-27']);

    $this->actingAs($coach)->post("/sports-management/{$team->id}/periods/{$spring->id}/delete")->assertRedirect();
    expect(occurrenceDates($task))->toBe(['2026-12-02', '2026-12-09']);
});

it('refuses periods that overlap or run longer than two years, but allows room around the season', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    $url = "/sports-management/{$team->id}/periods";
    $this->actingAs($coach)->post($url, ['name' => 'Autumn', 'starts_on' => '2026-09-01', 'ends_on' => '2026-12-12']);

    $this->actingAs($coach)->post($url, ['name' => 'Winter', 'starts_on' => '2026-12-01', 'ends_on' => '2027-01-31'])->assertSessionHasErrors('starts_on');
    $this->actingAs($coach)->post($url, ['name' => 'Long', 'starts_on' => '2027-01-01', 'ends_on' => '2029-01-02'])->assertSessionHasErrors('ends_on');
    $this->actingAs($coach)->post($url, ['name' => 'Kit in', 'starts_on' => '2027-06-01', 'ends_on' => '2027-07-31'])->assertSessionDoesntHaveErrors();
    $this->actingAs($coach)->post($url, ['name' => 'Kit out', 'starts_on' => '2026-06-01', 'ends_on' => '2026-08-31'])->assertSessionDoesntHaveErrors();
    $this->actingAs(referee($team))->post($url, ['name' => 'Spring', 'starts_on' => '2027-02-01', 'ends_on' => '2027-03-31'])->assertForbidden();

    expect(TeamPeriod::count())->toBe(3);
});

it('lets a repeating task stay within periods only once the team has some', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    $payload = taskPayload(['schedule' => 'recurring', 'frequency' => 'weekly', 'interval' => 1, 'weekday' => 3, 'starts_on' => '2026-12-01', 'ends_on' => '2027-01-31', 'within_periods' => '1']);

    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks", $payload)->assertSessionHasErrors('within_periods');

    $team->periods()->create(['name' => 'Autumn', 'starts_on' => '2026-09-01', 'ends_on' => '2026-12-12']);
    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks", $payload)->assertRedirect();
    expect(occurrenceDates(Task::sole()))->toBe(['2026-12-02', '2026-12-09']);

    $this->actingAs($coach)->get("/sports-management/{$team->id}")->assertSee('Every Wednesday, within periods')->assertSee('Autumn');
});

it('earns a whole-task sign-up its weight only for dates within periods', function () {
    $this->travelTo('2027-02-15 12:00');
    $team = tasksTeam(coach());
    $daan = rosterMember($team, 'Daan de Kok');
    $team->periods()->create(['name' => 'Autumn', 'starts_on' => '2026-09-01', 'ends_on' => '2026-12-12']);
    $team->periods()->create(['name' => 'Spring', 'starts_on' => '2027-01-20', 'ends_on' => '2027-05-31']);
    $task = weeklyTask($team, ['enrolment' => TaskEnrolment::WholeTask, 'weight' => 2]);
    TaskEnlistment::forceCreate(['task_id' => $task->id, 'team_member_id' => $daan->id, 'created_at' => '2026-11-01 12:00', 'updated_at' => '2026-11-01 12:00']);

    expect(TaskBalance::for($team)->sole()['points'])->toBe(8);
});

it('explains form errors in Dutch with the field names the form shows', function () {
    app()->setLocale('nl');
    $coach = coach();
    $team = tasksTeam($coach);

    $this->actingAs($coach)->post("/sports-management/{$team->id}/periods", ['name' => 'Voorjaar', 'starts_on' => '2027-03-01', 'ends_on' => '2027-02-01'])
        ->assertSessionHasErrors(['ends_on' => 'Tot en met moet op of na vanaf liggen.']);

    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks", taskPayload(['schedule' => 'recurring', 'frequency' => 'weekly', 'weekday' => 9, 'starts_on' => '2027-01-01', 'ends_on' => '1-2-2027']))
        ->assertSessionHasErrors(['weekday' => 'Dag moet tussen 1 en 7 liggen.', 'ends_on' => 'Tot en met is geen geldige datum of tijd.']);
});

it('counts a fixed role only for months with days in a period, dated on its last day in one', function () {
    $team = tasksTeam(coach());
    $team->periods()->create(['name' => 'Autumn', 'starts_on' => '2026-09-01', 'ends_on' => '2026-12-12']);
    $team->periods()->create(['name' => 'Spring', 'starts_on' => '2027-02-10', 'ends_on' => '2027-05-31']);
    $role = $team->tasks()->create([
        'title' => 'Trainer', 'schedule' => TaskSchedule::Season, 'enrolment' => TaskEnrolment::WholeTask,
        'starts_on' => '2026-08-01', 'ends_on' => '2027-06-30', 'within_periods' => true,
    ]);
    TaskOccurrences::sync($role);

    expect(occurrenceDates($role))->toBe([
        '2026-09-30', '2026-10-31', '2026-11-30', '2026-12-12',
        '2027-02-28', '2027-03-31', '2027-04-30', '2027-05-31',
    ])->and($role->scheduleSummary())->toBe('Fixed role, 1 Aug 2026 – 30 Jun 2027, within periods');
});

it('shows the period a match falls in', function () {
    $this->travelTo('2026-09-01 12:00');
    $coach = coach();
    $team = tasksTeam($coach);
    $team->periods()->create(['name' => 'Autumn', 'starts_on' => '2026-09-01', 'ends_on' => '2026-12-12']);
    $match = plannedMatch($team, ['scheduled_at' => '2026-10-10 09:30']);
    plannedMatch($team, ['scheduled_at' => '2027-01-09 09:30', 'opponent_name' => 'Winter cup']);

    $this->actingAs($coach)->get("/sports-management/{$team->id}")->assertSeeInOrder(['FC Rivals JO11-1', 'Autumn', 'Winter cup']);
    $this->actingAs($coach)->get("/sports-management/{$team->id}/matches/{$match->id}")->assertSee('Autumn');
});
