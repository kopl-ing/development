<?php

declare(strict_types=1);

use Kopling\SportsManagement\AvailabilityStatus;
use Kopling\SportsManagement\MatchAvailability;
use Kopling\SportsManagement\TaskBalance;
use Kopling\SportsManagement\TaskEnlistment;
use Kopling\SportsManagement\TaskEnlistmentAction;
use Kopling\SportsManagement\TaskEnlistmentEvent;
use Kopling\SportsManagement\TaskEnrolment;
use Kopling\SportsManagement\TaskLinkMode;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamMember;

beforeEach(function () {
    $this->travelTo('2026-11-20 12:00');
});

function leave($test, $coach, Team $team, TeamMember $member, ?string $on): void
{
    $test->actingAs($coach)->post("/sports-management/{$team->id}/members/{$member->id}", [
        'name' => $member->person->name, 'left_on' => $on,
    ])->assertRedirect();
}

it('drops future task sign-ups and match planning when a player leaves, and keeps the past', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    $daan = rosterMember($team, 'Daan de Kok');
    $drive = datesTask($team, ['2026-11-14', '2026-11-28']);
    enlistOn($drive, $daan, '2026-11-14');
    enlistOn($drive, $daan, '2026-11-28');
    $this->travelTo('2026-11-01 12:00');
    $trainer = datesTask($team, ['2026-11-10', '2026-11-24'], ['enrolment' => TaskEnrolment::WholeTask]);
    $season = enlistOn($trainer, $daan);
    $this->travelTo('2026-11-20 12:00');
    $past = plannedMatch($team, ['scheduled_at' => '2026-11-15 09:30']);
    $future = plannedMatch($team, ['scheduled_at' => '2026-11-29 09:30']);
    foreach ([$past, $future] as $match) {
        $match->availabilities()->create(['team_member_id' => $daan->id, 'status' => AvailabilityStatus::Available]);
    }

    leave($this, $coach, $team, $daan, '2026-11-20');

    expect(TaskEnlistment::where('occurrence_id', '!=', null)->get()->map(fn ($enlistment) => $enlistment->occurrence->date->toDateString())->all())->toBe(['2026-11-14'])
        ->and($season->fresh()->ended_on->toDateString())->toBe('2026-11-19')
        ->and(TaskEnlistmentEvent::where('action', TaskEnlistmentAction::Left)->pluck('created_by')->unique()->all())->toBe([$coach->id])
        ->and(MatchAvailability::pluck('match_id')->all())->toBe([$past->id])
        ->and(TaskBalance::for($team)->sole())->toMatchArray(['left' => true, 'points' => 2]);
});

it('takes a player who left off the roster, the task links and new matches, but not past ones', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    $daan = rosterMember($team, 'Daan de Kok');
    rosterMember($team, 'Jip Vos');
    $past = plannedMatch($team, ['scheduled_at' => '2026-11-14 09:30']);
    $future = plannedMatch($team, ['scheduled_at' => '2026-11-28 09:30']);
    $task = datesTask($team, ['2026-11-28']);

    leave($this, $coach, $team, $daan, '2026-11-20');

    $this->actingAs($coach)->get("/sports-management/{$team->id}")->assertSee('1 former player')->assertSee('Left on 20 November 2026');
    $this->actingAs($coach)->get("/sports-management/{$team->id}/matches/{$past->id}")->assertSee('Daan de Kok');
    $this->actingAs($coach)->get("/sports-management/{$team->id}/matches/{$future->id}")->assertDontSee('Daan de Kok');

    $link = '/live/team/'.$team->fresh()->tasks_token.'/tasks';
    $this->get($link)->assertSee('Jip V.')->assertDontSee('Daan K.');
    $this->get($link.'/'.$daan->id)->assertNotFound();
    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks/{$task->id}/enlist", ['team_member_id' => $daan->id, 'occurrence_id' => $task->occurrences()->sole()->id])->assertNotFound();

    $team->update(['tasks_link_mode' => TaskLinkMode::PerPlayer]);
    $this->get('/live/player/'.$daan->fresh()->tasks_token.'/tasks')->assertNotFound();
});

it('puts a player back on the team when the leaving date is cleared', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    $daan = rosterMember($team, 'Daan de Kok');

    leave($this, $coach, $team, $daan, '2026-11-20');
    leave($this, $coach, $team, $daan, null);

    expect($daan->fresh()->left_on)->toBeNull();
    $this->get('/live/team/'.$team->fresh()->tasks_token.'/tasks')->assertSee('Daan K.');
});

it('lists former players apart on the balance, and not at all for parents', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    $team->update(['tasks_balance_public' => true]);
    $daan = rosterMember($team, 'Daan de Kok');
    $jip = rosterMember($team, 'Jip Vos');
    leave($this, $coach, $team, $daan, '2026-11-20');

    $this->actingAs($coach)->get("/sports-management/{$team->id}/tasks/balance")->assertSeeInOrder(['Jip Vos', 'Former players', 'Daan de Kok']);
    $this->get('/live/team/'.$team->fresh()->tasks_token.'/tasks/'.$jip->id)->assertSee('Jip V.')->assertDontSee('Daan K.');
});
