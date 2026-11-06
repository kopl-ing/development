<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Kopling\Core\People\Person;
use Kopling\SportsManagement\HomeAway;
use Kopling\SportsManagement\Task;
use Kopling\SportsManagement\TaskEnrolment;
use Kopling\SportsManagement\TaskFrequency;
use Kopling\SportsManagement\TaskOccurrence;
use Kopling\SportsManagement\TaskOccurrences;
use Kopling\SportsManagement\TaskRecurrence;
use Kopling\SportsManagement\TaskSchedule;
use Kopling\SportsManagement\Team;

function tasksTeam(Person $coach): Team
{
    $team = staffedTeam($coach);
    $team->update(['tasks_enabled' => true]);

    return $team;
}

function taskPayload(array $overrides = []): array
{
    return $overrides + [
        'title' => 'Drive to away match',
        'schedule' => 'away_matches',
        'enrolment' => 'per_occurrence',
        'weight' => 1,
    ];
}

function matchTask(Team $team, TaskSchedule $schedule = TaskSchedule::AwayMatches, array $attributes = []): Task
{
    $task = $team->tasks()->create($attributes + ['title' => 'Task', 'schedule' => $schedule, 'enrolment' => TaskEnrolment::PerOccurrence]);
    TaskOccurrences::sync($task);

    return $task;
}

/**
 * @return array<int, string>
 */
function recurrenceDates(array $rule, string $from, string $to): array
{
    return array_map(
        fn (CarbonImmutable $date) => $date->toDateString(),
        TaskRecurrence::fromArray($rule)->dates(CarbonImmutable::parse($from), CarbonImmutable::parse($to)),
    );
}

it('creates a task with an occurrence per away match, moved by the lead time', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    $away = plannedMatch($team, ['scheduled_at' => '2026-11-14 09:30']);
    plannedMatch($team, ['home_away' => HomeAway::Home, 'scheduled_at' => '2026-11-21 09:30']);

    $this->actingAs($coach)
        ->post("/sports-management/{$team->id}/tasks", taskPayload(['lead_minutes' => 45, 'min_people' => 2, 'max_people' => 4, 'effort_amount' => '12.5', 'weight' => 2]))
        ->assertRedirect();

    $task = Task::where('team_id', $team->id)->sole();
    expect($task->schedule)->toBe(TaskSchedule::AwayMatches)
        ->and($task->created_by)->toBe($coach->id)
        ->and($task->effort_cents)->toBe(1250)
        ->and($task->weight)->toBe(2)
        ->and($task->occurrences()->get()->map(fn (TaskOccurrence $occurrence) => [$occurrence->match_id, $occurrence->startsAt()->format('Y-m-d H:i')])->all())
        ->toBe([[$away->id, '2026-11-14 08:45']]);
});

it('keeps match occurrences in step with the matches', function () {
    $team = tasksTeam(coach());
    $first = plannedMatch($team, ['scheduled_at' => '2026-11-14 09:30']);
    $task = matchTask($team);
    $occurrenceId = $task->occurrences()->sole()->id;

    $second = plannedMatch($team, ['scheduled_at' => '2026-11-28 10:00']);
    expect($task->occurrences()->pluck('match_id')->all())->toBe([$first->id, $second->id]);

    $first->update(['scheduled_at' => '2026-11-15 11:00']);
    $moved = $task->occurrences()->where('match_id', $first->id)->sole();
    expect($moved->id)->toBe($occurrenceId)
        ->and($moved->startsAt()->format('Y-m-d H:i'))->toBe('2026-11-15 11:00');

    $second->update(['home_away' => HomeAway::Home]);
    expect($task->occurrences()->pluck('match_id')->all())->toBe([$first->id]);

    $first->delete();
    expect($task->occurrences()->count())->toBe(0);
});

it('covers every match for an all-matches task', function () {
    $team = tasksTeam(coach());
    plannedMatch($team);
    plannedMatch($team, ['home_away' => HomeAway::Home, 'scheduled_at' => '2026-10-17 09:30']);

    expect(matchTask($team, TaskSchedule::AllMatches)->occurrences()->count())->toBe(2);
});

it('creates occurrences on set dates and keeps the ones still listed when edited', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    $payload = taskPayload([
        'title' => 'Wash kit',
        'schedule' => 'dates',
        'dates' => [['date' => '2026-12-05', 'time' => '10:00'], ['date' => '', 'time' => ''], ['date' => '2026-11-07', 'time' => '']],
    ]);

    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks", $payload)->assertRedirect();
    $task = Task::where('team_id', $team->id)->sole();
    expect($task->occurrences()->get()->map(fn (TaskOccurrence $occurrence) => [$occurrence->date->toDateString(), $occurrence->time === null ? null : substr($occurrence->time, 0, 5)])->all())
        ->toBe([['2026-11-07', null], ['2026-12-05', '10:00']]);
    $kept = $task->occurrences()->whereDate('date', '2026-12-05')->sole()->id;

    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks/{$task->id}", taskPayload([
        'title' => 'Wash kit',
        'schedule' => 'dates',
        'dates' => [['date' => '2026-12-05', 'time' => '11:00'], ['date' => '2027-01-09', 'time' => '']],
    ]))->assertRedirect();

    expect($task->occurrences()->pluck('date')->map->toDateString()->all())->toBe(['2026-12-05', '2027-01-09'])
        ->and($task->occurrences()->whereDate('date', '2026-12-05')->sole()->id)->toBe($kept);
});

it('creates repeating occurrences within the window', function () {
    $coach = coach();
    $team = tasksTeam($coach);

    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks", taskPayload([
        'title' => 'Training',
        'schedule' => 'recurring',
        'enrolment' => 'whole_task',
        'frequency' => 'weekly',
        'interval' => 2,
        'weekday' => 3,
        'time' => '18:30',
        'starts_on' => '2026-11-01',
        'ends_on' => '2026-12-01',
    ]))->assertRedirect();

    $task = Task::where('team_id', $team->id)->sole();
    expect($task->recurrenceRule()->frequency)->toBe(TaskFrequency::Weekly)
        ->and($task->occurrences()->get()->map(fn (TaskOccurrence $occurrence) => $occurrence->startsAt()->format('Y-m-d H:i'))->all())
        ->toBe(['2026-11-04 18:30', '2026-11-18 18:30']);
});

it('drops occurrences when a task switches from matches to set dates', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    plannedMatch($team, ['scheduled_at' => '2026-11-14 09:30']);
    $task = matchTask($team);

    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks/{$task->id}", taskPayload([
        'schedule' => 'dates',
        'dates' => [['date' => '2026-11-14', 'time' => ''], ['date' => '2026-11-21', 'time' => '']],
    ]))->assertRedirect();

    expect($task->occurrences()->get()->map(fn (TaskOccurrence $occurrence) => [$occurrence->match_id, $occurrence->date->toDateString()])->all())
        ->toBe([[null, '2026-11-14'], [null, '2026-11-21']]);
});

it('repeats weekly from the first matching day', function () {
    expect(recurrenceDates(['frequency' => 'weekly', 'weekday' => 6], '2026-11-07', '2026-11-30'))
        ->toBe(['2026-11-07', '2026-11-14', '2026-11-21', '2026-11-28'])
        ->and(recurrenceDates(['frequency' => 'weekly', 'weekday' => 7, 'interval' => 3], '2026-11-02', '2026-12-31'))
        ->toBe(['2026-11-08', '2026-11-29', '2026-12-20']);
});

it('repeats monthly on a day, falling back to the last day of short months', function () {
    expect(recurrenceDates(['frequency' => 'monthly', 'day' => 31], '2026-12-15', '2027-03-31'))
        ->toBe(['2026-12-31', '2027-01-31', '2027-02-28', '2027-03-31'])
        ->and(recurrenceDates(['frequency' => 'monthly', 'day' => 10, 'interval' => 2], '2026-11-15', '2027-04-30'))
        ->toBe(['2026-12-10', '2027-02-10', '2027-04-10']);
});

it('repeats monthly on the nth or last weekday', function () {
    expect(recurrenceDates(['frequency' => 'monthly', 'nth' => 1, 'weekday' => 6], '2026-11-01', '2027-01-31'))
        ->toBe(['2026-11-07', '2026-12-05', '2027-01-02'])
        ->and(recurrenceDates(['frequency' => 'monthly', 'nth' => TaskRecurrence::NTH_LAST, 'weekday' => 5], '2026-11-01', '2026-12-31'))
        ->toBe(['2026-11-27', '2026-12-25']);
});

it('repeats yearly across a year boundary', function () {
    expect(recurrenceDates(['frequency' => 'yearly', 'month' => 2, 'day' => 29], '2026-07-01', '2028-06-30'))
        ->toBe(['2027-02-28', '2028-02-29']);
});

it('refuses a maximum below the minimum and set dates without a date', function () {
    $coach = coach();
    $team = tasksTeam($coach);

    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks", taskPayload(['min_people' => 3, 'max_people' => 2]))
        ->assertSessionHasErrors('max_people');
    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks", taskPayload(['schedule' => 'dates', 'dates' => [['date' => '', 'time' => '']]]))
        ->assertSessionHasErrors('dates');
    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks", taskPayload(['schedule' => 'recurring', 'frequency' => 'weekly', 'weekday' => 1, 'starts_on' => '2026-07-01', 'ends_on' => '2029-07-01']))
        ->assertSessionHasErrors('ends_on');

    expect(Task::count())->toBe(0);
});

it('only lets the team coaches manage tasks, and only when tasks are on', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $other = coach('Other', 'other@example.test');

    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks", taskPayload())->assertNotFound();

    $team->update(['tasks_enabled' => true]);
    $this->actingAs($other)->post("/sports-management/{$team->id}/tasks", taskPayload())->assertForbidden();
    $this->actingAs(referee($team))->post("/sports-management/{$team->id}/tasks", taskPayload())->assertForbidden();

    $task = matchTask(tasksTeam($other));
    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks/{$task->id}/delete")->assertNotFound();
    expect(Task::whereKey($task->id)->exists())->toBeTrue();
});

it('deletes a task with its occurrences', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    plannedMatch($team);
    $task = matchTask($team);

    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks/{$task->id}/delete")->assertRedirect();

    expect(Task::count())->toBe(0)->and(TaskOccurrence::count())->toBe(0);
});

it('switches tasks on from the team form and lists them on the team page', function () {
    $coach = coach();
    $team = staffedTeam($coach);

    $this->actingAs($coach)->post("/sports-management/{$team->id}", ['name' => $team->name, 'sport' => 'football', 'season' => $team->season, 'tasks_enabled' => '1'])->assertRedirect();
    expect($team->fresh()->tasks_enabled)->toBeTrue();

    plannedMatch($team, ['scheduled_at' => now()->addWeek()->setTime(9, 30)]);
    matchTask($team, attributes: ['title' => 'Bring oranges', 'lead_minutes' => 30, 'min_people' => 1, 'max_people' => 2]);

    $this->actingAs($coach)->get("/sports-management/{$team->id}")
        ->assertOk()
        ->assertSee('Bring oranges')
        ->assertSee('Every away match, 30 min before kick-off')
        ->assertSee('1–2 people');
});

it('renders the fields for the chosen schedule', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    $url = "/sports-management/{$team->id}/tasks/schedule-fields";

    $this->actingAs($coach)->get($url.'?schedule=recurring&frequency=monthly&monthly_by=nth')
        ->assertOk()->assertSee('name="nth"', false)->assertDontSee('name="lead_minutes"', false);
    $this->actingAs($coach)->get($url.'?schedule=dates&add_date=1&dates[0][date]=2026-11-07')
        ->assertOk()->assertSee('name="dates[1][date]"', false)->assertSee('value="2026-11-07"', false);
});

it('derives the season window from the team season', function () {
    $team = Team::create(['name' => 'JO9', 'season' => '2026/2027']);

    expect(array_map(fn (CarbonImmutable $date) => $date->toDateString(), $team->seasonBounds()))->toBe(['2026-07-01', '2027-06-30']);
});

it('signs up per date for a task on one set date, whatever was sent', function () {
    $coach = coach();
    $team = tasksTeam($coach);

    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks", taskPayload([
        'schedule' => 'dates',
        'enrolment' => 'whole_task',
        'dates' => [['date' => '2026-12-05', 'time' => '']],
    ]))->assertRedirect();

    expect(Task::where('team_id', $team->id)->sole()->enrolment)->toBe(TaskEnrolment::PerOccurrence);
});

it('refuses set dates when no dates are sent at all', function () {
    $coach = coach();
    $team = tasksTeam($coach);

    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks", taskPayload(['schedule' => 'dates']))
        ->assertSessionHasErrors('dates');

    expect(Task::count())->toBe(0);
});

it('offers the sign-up choice only when a task happens more than once', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    $url = "/sports-management/{$team->id}/tasks/schedule-fields";

    $this->actingAs($coach)->get($url.'?schedule=dates&dates[0][date]=2026-11-07')
        ->assertOk()->assertDontSee('<select name="enrolment"', false)->assertSee('name="enrolment" value="per_occurrence"', false);
    $this->actingAs($coach)->get($url.'?schedule=dates&dates[0][date]=2026-11-07&dates[1][date]=2026-11-14')
        ->assertOk()->assertSee('<select name="enrolment"', false);
    $this->actingAs($coach)->get($url.'?schedule=away_matches')
        ->assertOk()->assertSee('<select name="enrolment"', false);
});
