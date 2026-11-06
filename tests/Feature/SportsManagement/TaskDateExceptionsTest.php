<?php

declare(strict_types=1);

use Kopling\SportsManagement\TaskBalance;
use Kopling\SportsManagement\TaskEnlistment;
use Kopling\SportsManagement\TaskEnlistmentSkip;
use Kopling\SportsManagement\TaskEnrolment;
use Kopling\SportsManagement\TaskSchedule;

beforeEach(function () {
    $this->travelTo('2026-11-01 12:00');
});

function pointsOf($team): array
{
    return TaskBalance::for($team)->mapWithKeys(fn (array $row) => [$row['member']->person->name => $row['points'] + $row['planned']])->all();
}

it('lets a regular person sit out one match and someone else step in, through the link', function () {
    $team = tasksTeam(coach());
    $daan = rosterMember($team, 'Daan de Kok');
    $jip = rosterMember($team, 'Jip Vos');
    plannedMatch($team, ['scheduled_at' => '2026-11-14 09:30']);
    plannedMatch($team, ['scheduled_at' => '2026-11-21 09:30']);
    $task = matchTask($team, TaskSchedule::AllMatches, ['title' => 'Match trainer', 'enrolment' => TaskEnrolment::WholeTask, 'max_people' => 1]);
    $first = $task->occurrences()->orderBy('date')->first();
    $daans = enlistOn($task, $daan);
    $daanPage = linkPath($team).'/'.$daan->id;
    $jipPage = linkPath($team).'/'.$jip->id;

    $this->get($daanPage)->assertSee('Not this time')->assertSee('regular');
    $this->post($jipPage, ['task_id' => $task->id, 'occurrence_id' => $first->id])->assertSessionHasErrors('enlist');

    $this->post($daanPage.'/dates/'.$daans->id, ['occurrence_id' => $first->id, 'action' => 'skip'])->assertRedirect($daanPage);
    $this->post($jipPage, ['task_id' => $task->id, 'occurrence_id' => $first->id])->assertSessionDoesntHaveErrors();

    expect(pointsOf($team))->toBe(['Daan de Kok' => 1, 'Jip Vos' => 1]);
    $this->post($daanPage.'/dates/'.$daans->id, ['occurrence_id' => $first->id, 'action' => 'restore'])->assertSessionHasErrors('enlist');

    TaskEnlistment::where('team_member_id', $jip->id)->delete();
    $this->post($daanPage.'/dates/'.$daans->id, ['occurrence_id' => $first->id, 'action' => 'restore'])->assertSessionDoesntHaveErrors();
    expect(pointsOf($team))->toBe(['Daan de Kok' => 2, 'Jip Vos' => 0]);
});

it('lets someone join a regular person for one date while there is room', function () {
    $team = tasksTeam(coach());
    $daan = rosterMember($team, 'Daan de Kok');
    $jip = rosterMember($team, 'Jip Vos');
    plannedMatch($team, ['scheduled_at' => '2026-11-14 09:30']);
    $task = matchTask($team, TaskSchedule::AllMatches, ['enrolment' => TaskEnrolment::WholeTask, 'max_people' => 2]);
    enlistOn($task, $daan);

    $this->post(linkPath($team).'/'.$jip->id, ['task_id' => $task->id, 'occurrence_id' => $task->occurrences()->sole()->id])->assertSessionDoesntHaveErrors();

    $this->get(linkPath($team).'/'.$jip->id)->assertSee('2 / 2');
});

it('keeps per-date changes to staff on a staff-only task', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    $daan = rosterMember($team, 'Daan de Kok');
    plannedMatch($team, ['scheduled_at' => '2026-11-14 09:30']);
    $task = matchTask($team, TaskSchedule::AllMatches, ['enrolment' => TaskEnrolment::WholeTask, 'staff_only' => true]);
    $daans = enlistOn($task, $daan);
    $occurrence = $task->occurrences()->sole();
    $page = linkPath($team).'/'.$daan->id;

    $this->get($page)->assertDontSee('Not this time');
    $this->post($page.'/dates/'.$daans->id, ['occurrence_id' => $occurrence->id, 'action' => 'skip'])->assertSessionHasErrors('enlist');

    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks/enlistments/{$daans->id}/date", ['occurrence_id' => $occurrence->id, 'action' => 'skip'])->assertRedirect();
    expect(TaskEnlistmentSkip::count())->toBe(1);
});

it('lets staff take a regular person off a past date and put someone else on it', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    $team->update(['tasks_approve_completion' => true]);
    $daan = rosterMember($team, 'Daan de Kok');
    $jip = rosterMember($team, 'Jip Vos');
    plannedMatch($team, ['scheduled_at' => '2026-11-14 09:30']);
    $task = matchTask($team, TaskSchedule::AllMatches, ['enrolment' => TaskEnrolment::WholeTask, 'max_people' => 1]);
    $daans = enlistOn($task, $daan);
    $occurrence = $task->occurrences()->sole();
    $this->travelTo('2026-11-20 12:00');
    $base = "/sports-management/{$team->id}/tasks";

    $this->actingAs($coach)->get("$base?filter=past")->assertSee('Off this date')->assertDontSee("/tasks/{$task->id}/enlist", false);
    $this->actingAs($coach)->post("$base/enlistments/{$daans->id}/date", ['occurrence_id' => $occurrence->id, 'action' => 'skip'])->assertRedirect();
    $this->actingAs($coach)->post("$base/{$task->id}/enlist", ['team_member_id' => $jip->id, 'occurrence_id' => $occurrence->id])->assertRedirect();

    expect(pointsOf($team))->toBe(['Daan de Kok' => 0, 'Jip Vos' => 1]);
    $this->actingAs($coach)->get("$base?filter=past")->assertSee('Put back');
});
