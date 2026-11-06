<?php

declare(strict_types=1);

use Kopling\SportsManagement\TaskBalance;
use Kopling\SportsManagement\TaskEnlistmentOutcome;
use Kopling\SportsManagement\TaskEnrolment;
use Kopling\SportsManagement\TaskOutcome;
use Kopling\SportsManagement\Team;

beforeEach(function () {
    $this->travelTo('2026-11-20 12:00');
});

function approvingTeam(\Kopling\Core\People\Person $coach): Team
{
    $team = tasksTeam($coach);
    $team->update(['tasks_approve_completion' => true]);

    return $team;
}

function balanceOf(Team $team): array
{
    return TaskBalance::for($team)->mapWithKeys(fn (array $row) => [$row['member']->person->name => [$row['points'], $row['no_shows'], $row['pending']]])->all();
}

it('counts a passed date only once staff confirmed it was done', function () {
    $coach = coach();
    $team = approvingTeam($coach);
    $daan = rosterMember($team, 'Daan de Kok');
    $jip = rosterMember($team, 'Jip Vos');
    $task = datesTask($team, ['2026-11-14'], ['weight' => 2]);
    $daans = enlistOn($task, $daan, '2026-11-14');
    $jips = enlistOn($task, $jip, '2026-11-14');
    $occurrence = $task->occurrences()->sole();

    expect(balanceOf($team))->toBe(['Daan de Kok' => [0, 0, 1], 'Jip Vos' => [0, 0, 1]]);

    $outcome = fn ($enlistment, string $value) => $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks/enlistments/{$enlistment->id}/outcome", ['occurrence_id' => $occurrence->id, 'outcome' => $value]);
    $outcome($daans, 'done')->assertRedirect();
    $outcome($jips, 'no_show')->assertRedirect();

    expect(balanceOf($team))->toBe(['Jip Vos' => [0, 1, 0], 'Daan de Kok' => [2, 0, 0]])
        ->and(TaskEnlistmentOutcome::where('enlistment_id', $daans->id)->sole()->confirmed_by)->toBe($coach->id);

    $outcome($jips, 'done');
    expect(balanceOf($team)['Jip Vos'])->toBe([2, 0, 0])
        ->and(TaskEnlistmentOutcome::count())->toBe(2);
});

it('counts everything as done when the team does not confirm', function () {
    $team = tasksTeam(coach());
    $daan = rosterMember($team, 'Daan de Kok');
    $task = datesTask($team, ['2026-11-14']);
    $enlistment = enlistOn($task, $daan, '2026-11-14');
    $enlistment->outcomes()->create(['occurrence_id' => $task->occurrences()->sole()->id, 'outcome' => TaskOutcome::NoShow]);

    expect(balanceOf($team))->toBe(['Daan de Kok' => [1, 0, 0]]);
});

it('counts whole-task sign-ups as done unless the task confirms each date', function () {
    $coach = coach();
    $team = approvingTeam($coach);
    $daan = rosterMember($team, 'Daan de Kok');
    $this->travelTo('2026-11-01 12:00');
    $trainer = datesTask($team, ['2026-11-03', '2026-11-10'], ['enrolment' => TaskEnrolment::WholeTask]);
    $enlistment = enlistOn($trainer, $daan);
    $this->travelTo('2026-11-20 12:00');

    expect(balanceOf($team))->toBe(['Daan de Kok' => [2, 0, 0]]);

    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks/enlistments/{$enlistment->id}/outcome", [
        'occurrence_id' => $trainer->occurrences()->whereDate('date', '2026-11-10')->sole()->id, 'outcome' => 'no_show',
    ]);
    expect(balanceOf($team))->toBe(['Daan de Kok' => [1, 1, 0]]);

    $trainer->update(['confirm_each_occurrence' => true]);
    expect(balanceOf($team))->toBe(['Daan de Kok' => [0, 1, 1]]);
});

it('confirms every waiting sign-up on the chosen dates at once', function () {
    $coach = coach();
    $team = approvingTeam($coach);
    $daan = rosterMember($team, 'Daan de Kok');
    $jip = rosterMember($team, 'Jip Vos');
    $task = datesTask($team, ['2026-11-07', '2026-11-14']);
    enlistOn($task, $daan, '2026-11-07');
    enlistOn($task, $daan, '2026-11-14');
    $jips = enlistOn($task, $jip, '2026-11-14');
    $jips->outcomes()->create(['occurrence_id' => $task->occurrences()->whereDate('date', '2026-11-14')->sole()->id, 'outcome' => TaskOutcome::NoShow]);

    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks/confirm", [
        'occurrence_ids' => [$task->occurrences()->whereDate('date', '2026-11-14')->sole()->id],
    ])->assertRedirect();

    expect(balanceOf($team))->toBe(['Jip Vos' => [0, 1, 0], 'Daan de Kok' => [1, 0, 1]]);
});

it('lists only dates with something to confirm under that filter', function () {
    $coach = coach();
    $team = approvingTeam($coach);
    $daan = rosterMember($team, 'Daan de Kok');
    $wash = datesTask($team, ['2026-11-07'], ['title' => 'Wash kit']);
    $goals = datesTask($team, ['2026-11-14'], ['title' => 'Set up goals']);
    enlistOn($wash, $daan, '2026-11-07');
    enlistOn($goals, $daan, '2026-11-14')->outcomes()->create(['occurrence_id' => $goals->occurrences()->sole()->id, 'outcome' => TaskOutcome::Done]);
    $url = "/sports-management/{$team->id}/tasks";

    $this->actingAs($coach)->get("$url?filter=confirm")->assertOk()->assertSee('Wash kit')->assertDontSee('Set up goals')->assertSee('1 to confirm');
    $this->actingAs($coach)->get("$url?filter=past")->assertOk()->assertSee('Wash kit')->assertSee('Set up goals');

    $team->update(['tasks_approve_completion' => false]);
    $this->actingAs($coach)->get("$url?filter=confirm")->assertOk()->assertDontSee('To confirm');
});

it('refuses an outcome for a date still to come, or when the team does not confirm', function () {
    $coach = coach();
    $team = approvingTeam($coach);
    $daan = rosterMember($team, 'Daan de Kok');
    $task = datesTask($team, ['2026-11-28']);
    $enlistment = enlistOn($task, $daan, '2026-11-28');
    $post = fn () => $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks/enlistments/{$enlistment->id}/outcome", ['occurrence_id' => $task->occurrences()->sole()->id, 'outcome' => 'done']);

    $post()->assertNotFound();
    $this->travelTo('2026-11-29 12:00');
    $this->actingAs(referee($team))->post("/sports-management/{$team->id}/tasks/enlistments/{$enlistment->id}/outcome", ['occurrence_id' => $task->occurrences()->sole()->id, 'outcome' => 'done'])->assertForbidden();
    $team->update(['tasks_approve_completion' => false]);
    $post()->assertNotFound();

    expect(TaskEnlistmentOutcome::count())->toBe(0);
});

it('keeps no-shows off the parents\' balance', function () {
    $team = approvingTeam(coach());
    $team->update(['tasks_balance_public' => true]);
    $daan = rosterMember($team, 'Daan de Kok');
    $task = datesTask($team, ['2026-11-14']);
    enlistOn($task, $daan, '2026-11-14')->outcomes()->create(['occurrence_id' => $task->occurrences()->sole()->id, 'outcome' => TaskOutcome::NoShow]);

    $this->get('/live/team/'.$team->fresh()->tasks_token.'/tasks/'.$daan->id)->assertOk()->assertSee('0 points')->assertDontSee('no-show');
});

it('stores confirming each date only for tasks taken on for all dates at once', function () {
    $coach = coach();
    $team = approvingTeam($coach);
    $payload = taskPayload(['schedule' => 'all_matches', 'confirm_each_occurrence' => '1']);

    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks", $payload + [])->assertRedirect();
    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks", ['enrolment' => 'whole_task', 'title' => 'Trainer'] + $payload)->assertRedirect();

    expect(\Kopling\SportsManagement\Task::orderBy('title')->pluck('confirm_each_occurrence', 'title')->all())
        ->toBe(['Drive to away match' => false, 'Trainer' => true]);
});

it('lets staff backfill or replace who did a past match task from the past tab', function () {
    $coach = coach();
    $team = approvingTeam($coach);
    $daan = rosterMember($team, 'Daan de Kok');
    $jip = rosterMember($team, 'Jip Vos');
    plannedMatch($team, ['scheduled_at' => '2026-11-14 09:30', 'opponent_name' => 'FC Rivals']);
    $task = matchTask($team, attributes: ['title' => 'Drive', 'max_people' => 1]);
    $occurrence = $task->occurrences()->sole();
    $daans = enlistOn($task, $daan, '2026-11-14');

    $this->actingAs($coach)->get("/sports-management/{$team->id}/tasks?filter=past")
        ->assertSee('FC Rivals')
        ->assertSee(route('kopling-sports-management::sports-management/tasks.leave', [$team, $daans]), false);

    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks/enlistments/{$daans->id}/delete")->assertRedirect();
    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks/{$task->id}/enlist", ['team_member_id' => $jip->id, 'occurrence_id' => $occurrence->id])->assertRedirect();

    expect(balanceOf($team))->toBe(['Daan de Kok' => [0, 0, 0], 'Jip Vos' => [1, 0, 0]]);
});

it('shows count and min/max on past dates and hides assigning once a past date is full', function () {
    $coach = coach();
    $team = approvingTeam($coach);
    $daan = rosterMember($team, 'Daan de Kok');
    $task = datesTask($team, ['2026-11-14'], ['title' => 'Drive', 'min_people' => 1, 'max_people' => 1]);
    $url = "/sports-management/{$team->id}/tasks?filter=past";

    $this->actingAs($coach)->get($url)->assertSee('0 / 1')->assertSee('1 person')->assertSee("/tasks/{$task->id}/enlist", false);

    enlistOn($task, $daan, '2026-11-14');
    $this->actingAs($coach)->get($url)->assertSee('1 / 1')->assertDontSee("/tasks/{$task->id}/enlist", false);
});
