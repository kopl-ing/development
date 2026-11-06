<?php

declare(strict_types=1);

use Kopling\SportsManagement\TaskBalance;
use Kopling\SportsManagement\TaskEnlistment;
use Kopling\SportsManagement\TaskEnrolment;
use Kopling\SportsManagement\TeamPeriod;

beforeEach(function () {
    $this->travelTo('2026-11-20 12:00');
});

it('shows a staff-only task on the link page without letting parents take it on or back out', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    $daan = rosterMember($team, 'Daan de Kok');
    $task = datesTask($team, ['2026-11-28'], ['title' => 'Referee', 'staff_only' => true]);
    $occurrence = $task->occurrences()->sole();
    $player = '/live/team/'.$team->fresh()->tasks_token.'/tasks/'.$daan->id;

    $this->get($player)->assertSee('Referee')->assertSee('Assigned by staff')->assertDontSee('modal-enlist-'.$occurrence->id);
    $this->post($player, ['task_id' => $task->id, 'occurrence_id' => $occurrence->id])->assertSessionHasErrors('enlist');
    expect(TaskEnlistment::count())->toBe(0);

    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks/{$task->id}/enlist", ['team_member_id' => $daan->id, 'occurrence_id' => $occurrence->id])->assertRedirect();
    $this->post($player.'/leave/'.TaskEnlistment::sole()->id)->assertSessionHasErrors('enlist');
    expect(TaskEnlistment::count())->toBe(1);
});

it('counts planned points apart from earned ones', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    $daan = rosterMember($team, 'Daan de Kok');
    $task = datesTask($team, ['2026-11-14', '2026-11-28', '2026-12-05'], ['weight' => 2]);
    foreach (['2026-11-14', '2026-11-28', '2026-12-05'] as $date) {
        enlistOn($task, $daan, $date);
    }

    expect(TaskBalance::for($team)->sole())->toMatchArray(['points' => 2, 'planned' => 4]);
    $this->actingAs($coach)->get("/sports-management/{$team->id}/tasks/balance")->assertSee('+4 planned');
});

it('backfills a past period for the current season-long sign-ups, once', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    $daan = rosterMember($team, 'Daan de Kok');
    $jip = rosterMember($team, 'Jip Vos');
    $role = datesTask($team, ['2026-09-30', '2026-10-31', '2026-11-30'], ['title' => 'Coordinator', 'enrolment' => TaskEnrolment::WholeTask]);
    $current = enlistOn($role, $daan);
    $stopped = enlistOn($role, $jip, attributes: ['ended_on' => '2026-08-31']);
    expect(TaskBalance::for($team)->firstWhere('member.id', $daan->id)['points'])->toBe(0);

    $this->actingAs($coach)->post("/sports-management/{$team->id}/periods", ['name' => 'Autumn', 'starts_on' => '2026-09-01', 'ends_on' => '2026-10-31', 'backfill' => '1'])->assertRedirect();

    expect($current->fresh()->starts_on->toDateString())->toBe('2026-09-01')
        ->and($stopped->fresh()->starts_on)->toBeNull()
        ->and(TaskBalance::for($team)->firstWhere('member.id', $daan->id)['points'])->toBe(2);

    $period = TeamPeriod::sole();
    $this->actingAs($coach)->post("/sports-management/{$team->id}/periods/{$period->id}/backfill")
        ->assertRedirect()
        ->assertSessionHas('periods_status', 'No sign-ups needed backfilling.');
});

it('lets staff assign and remove people one by one on a season-long task from the team page, up to the maximum', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    $daan = rosterMember($team, 'Daan de Kok');
    $jip = rosterMember($team, 'Jip Vos');
    $role = datesTask($team, ['2026-11-30', '2026-12-31'], ['title' => 'Coordinator', 'enrolment' => TaskEnrolment::WholeTask, 'max_people' => 2]);
    $page = "/sports-management/{$team->id}";
    $assign = fn ($member) => $this->actingAs($coach)->from($page)->post("$page/tasks/{$role->id}/enlist", ['team_member_id' => $member->id]);

    $this->actingAs($coach)->get($page)->assertSee("/tasks/{$role->id}/enlist", false);
    $assign($daan)->assertRedirect($page);
    $assign($jip)->assertRedirect($page);

    $this->actingAs($coach)->get($page)->assertSee('Daan de Kok')->assertSee('Jip Vos')->assertDontSee("/tasks/{$role->id}/enlist", false);

    $daans = TaskEnlistment::where('team_member_id', $daan->id)->sole();
    $this->actingAs($coach)->from($page)->post("$page/tasks/enlistments/{$daans->id}/delete")->assertRedirect($page);
    expect(TaskEnlistment::where('team_member_id', $jip->id)->exists())->toBeTrue()
        ->and(TaskEnlistment::whereKey($daans->id)->exists())->toBeFalse();
    $this->actingAs($coach)->get($page)->assertSee("/tasks/{$role->id}/enlist", false);
});
