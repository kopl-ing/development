<?php

declare(strict_types=1);

use Kopling\SportsManagement\TaskEnlistment;
use Kopling\SportsManagement\TaskEnlistmentAction;
use Kopling\SportsManagement\TaskEnlistmentEvent;
use Kopling\SportsManagement\TaskEnrolment;
use Kopling\SportsManagement\TaskLinkMode;
use Kopling\SportsManagement\TaskOccurrence;
use Kopling\SportsManagement\TaskSchedule;
use Kopling\SportsManagement\TaskUndoWindow;
use Kopling\SportsManagement\Team;

beforeEach(function () {
    $this->travelTo('2026-11-02 12:00');
});

function linkPath(Team $team): string
{
    return '/live/team/'.$team->fresh()->tasks_token.'/tasks';
}

it('lets a parent pick their player through the team link and sign up', function () {
    $team = tasksTeam(coach());
    $daan = rosterMember($team, 'Daan de Kok');
    $guest = rosterMember($team, 'Sem Gast');
    $guest->update(['guest' => true]);
    $match = plannedMatch($team, ['scheduled_at' => '2026-11-14 09:30']);
    $task = matchTask($team, attributes: ['title' => 'Drive', 'max_people' => 4]);

    $this->get(linkPath($team))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('<title>Tasks · JO11-2</title>', false)
        ->assertSee('Daan K.')
        ->assertDontSee('Daan de Kok')
        ->assertDontSee('Sem G.');

    $player = linkPath($team).'/'.$daan->id;
    $this->get($player)->assertOk()->assertSee('<title>Daan K. · Tasks · JO11-2</title>', false)->assertSee('Drive')->assertSee('0 / 4');

    $this->post($player, ['task_id' => $task->id, 'occurrence_id' => $task->occurrences()->sole()->id, 'note' => 'dad, 3 seats'])
        ->assertRedirect($player);

    $enlistment = TaskEnlistment::sole();
    expect($enlistment->team_member_id)->toBe($daan->id)
        ->and($enlistment->created_by)->toBeNull()
        ->and(TaskEnlistmentEvent::sole()->action)->toBe(TaskEnlistmentAction::Enlisted);
    $this->get($player)->assertSee('1 / 4')->assertSee('dad, 3 seats');

    $this->post(linkPath($team).'/'.$guest->id, ['task_id' => $task->id, 'occurrence_id' => $task->occurrences()->sole()->id])->assertNotFound();
});

it('reaches only its own team, and only in the active link mode', function () {
    $team = tasksTeam(coach());
    $member = rosterMember($team, 'Daan de Kok');
    $other = tasksTeam(coach('Other', 'other@example.test'));
    $stranger = rosterMember($other, 'Jip Vos');

    $this->get(linkPath($team).'/'.$stranger->id)->assertNotFound();
    $this->get('/live/player/'.$member->tasks_token.'/tasks')->assertNotFound();

    $team->update(['tasks_link_mode' => TaskLinkMode::PerPlayer]);
    $this->get(linkPath($team))->assertNotFound();
    $this->get('/live/player/'.$member->tasks_token.'/tasks')->assertOk()->assertSee('Daan K.')->assertDontSee('Iemand anders?')->assertDontSee('Not you?');

    $team->update(['tasks_enabled' => false]);
    $this->get('/live/player/'.$member->tasks_token.'/tasks')->assertNotFound();
});

it('refuses a link once it has been regenerated', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    $member = rosterMember($team, 'Daan de Kok');
    $old = linkPath($team);

    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks/link")->assertRedirect();
    $this->get($old)->assertNotFound();
    $this->get(linkPath($team))->assertOk();

    $team->update(['tasks_link_mode' => TaskLinkMode::PerPlayer]);
    $oldPlayer = '/live/player/'.$member->tasks_token.'/tasks';
    $this->actingAs($coach)->post("/sports-management/{$team->id}/members/{$member->id}/tasks-link")->assertRedirect();
    $this->get($oldPlayer)->assertNotFound();
    $this->get('/live/player/'.$member->fresh()->tasks_token.'/tasks')->assertOk();
});

it('counts several sign-ups per player and closes at the maximum', function () {
    $team = tasksTeam(coach());
    $daan = rosterMember($team, 'Daan de Kok');
    $jip = rosterMember($team, 'Jip Vos');
    plannedMatch($team, ['scheduled_at' => '2026-11-14 09:30']);
    $task = matchTask($team, attributes: ['max_people' => 2]);
    $occurrence = $task->occurrences()->sole();
    $payload = ['task_id' => $task->id, 'occurrence_id' => $occurrence->id];

    $this->post(linkPath($team).'/'.$daan->id, $payload)->assertRedirect();
    $this->post(linkPath($team).'/'.$daan->id, $payload + ['note' => 'with Sem'])->assertRedirect();
    $this->post(linkPath($team).'/'.$jip->id, $payload)->assertSessionHasErrors('enlist');

    expect(TaskEnlistment::where('team_member_id', $daan->id)->count())->toBe(2)
        ->and(TaskEnlistment::where('team_member_id', $jip->id)->exists())->toBeFalse();
});

it('lets the link undo a sign-up only within the undo window, and never someone else\'s', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    $daan = rosterMember($team, 'Daan de Kok');
    $jip = rosterMember($team, 'Jip Vos');
    plannedMatch($team, ['scheduled_at' => '2026-11-03 09:30']);
    $task = matchTask($team, attributes: ['undo_window' => TaskUndoWindow::DayBefore, 'lead_minutes' => 10, 'effort_minutes' => 90]);
    $occurrence = $task->occurrences()->sole();
    $this->get(linkPath($team).'/'.$daan->id)
        ->assertSee('You can back out until a day before it starts.')
        ->assertSee('Who\'s doing it?')
        ->assertSee('Daan K. will do it')
        ->assertSee('Every away match, 10 min before kick-off · takes about 1 hour 30 minutes');
    $this->post(linkPath($team).'/'.$daan->id, ['task_id' => $task->id, 'occurrence_id' => $occurrence->id]);
    $enlistment = TaskEnlistment::sole();

    $this->post(linkPath($team).'/'.$jip->id.'/leave/'.$enlistment->id)->assertNotFound();
    $this->post(linkPath($team).'/'.$daan->id.'/leave/'.$enlistment->id)->assertSessionHasErrors('enlist');
    expect(TaskEnlistment::count())->toBe(1);

    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks/enlistments/{$enlistment->id}/delete")->assertRedirect();
    expect(TaskEnlistment::count())->toBe(0)
        ->and(TaskEnlistmentEvent::where('action', TaskEnlistmentAction::Left)->sole()->created_by)->toBe($coach->id);
});

it('lets the link undo a sign-up any time by default', function () {
    $team = tasksTeam(coach());
    $daan = rosterMember($team, 'Daan de Kok');
    plannedMatch($team, ['scheduled_at' => '2026-11-02 13:00']);
    $task = matchTask($team);
    $this->post(linkPath($team).'/'.$daan->id, ['task_id' => $task->id, 'occurrence_id' => $task->occurrences()->sole()->id]);

    $this->post(linkPath($team).'/'.$daan->id.'/leave/'.TaskEnlistment::sole()->id)->assertRedirect();
    expect(TaskEnlistment::count())->toBe(0)
        ->and(TaskEnlistmentEvent::pluck('action')->all())->toBe([TaskEnlistmentAction::Enlisted, TaskEnlistmentAction::Left]);
});

it('covers every later date with one whole-task sign-up, until it ends', function () {
    $team = tasksTeam(coach());
    $daan = rosterMember($team, 'Daan de Kok');
    $task = $team->tasks()->create(['title' => 'Trainer', 'schedule' => TaskSchedule::Dates, 'enrolment' => TaskEnrolment::WholeTask, 'max_people' => 1]);
    $task->dates()->createMany([['date' => '2026-11-03'], ['date' => '2026-11-10'], ['date' => '2026-11-17']]);
    \Kopling\SportsManagement\TaskOccurrences::sync($task);
    $player = linkPath($team).'/'.$daan->id;

    $this->post($player, ['task_id' => $task->id])->assertRedirect();
    $this->post($player, ['task_id' => $task->id])->assertSessionHasErrors('enlist');
    $enlistment = TaskEnlistment::sole();
    $occurrences = $task->occurrences()->get();
    expect($occurrences->map(fn (TaskOccurrence $occurrence) => $enlistment->covers($occurrence))->all())->toBe([true, true, true]);

    $this->travelTo('2026-11-05 12:00');
    $this->post($player.'/leave/'.$enlistment->id)->assertRedirect();
    $enlistment->refresh();
    expect($enlistment->ended_on->toDateString())->toBe('2026-11-09')
        ->and($occurrences->map(fn (TaskOccurrence $occurrence) => $enlistment->covers($occurrence))->all())->toBe([true, false, false]);
});

it('drops a whole-task sign-up outright when it covered nothing yet', function () {
    $team = tasksTeam(coach());
    $daan = rosterMember($team, 'Daan de Kok');
    $task = $team->tasks()->create(['title' => 'Trainer', 'schedule' => TaskSchedule::Dates, 'enrolment' => TaskEnrolment::WholeTask]);
    $task->dates()->createMany([['date' => '2026-11-10'], ['date' => '2026-11-17']]);
    \Kopling\SportsManagement\TaskOccurrences::sync($task);

    $this->post(linkPath($team).'/'.$daan->id, ['task_id' => $task->id]);
    $this->post(linkPath($team).'/'.$daan->id.'/leave/'.TaskEnlistment::sole()->id)->assertRedirect();

    expect(TaskEnlistment::count())->toBe(0);
});

it('cancels an occurrence with sign-ups instead of deleting it when its match goes', function () {
    $team = tasksTeam(coach());
    $daan = rosterMember($team, 'Daan de Kok');
    $match = plannedMatch($team, ['scheduled_at' => '2026-11-14 09:30']);
    $task = matchTask($team, attributes: ['title' => 'Drive']);
    $occurrence = $task->occurrences()->sole();
    $this->post(linkPath($team).'/'.$daan->id, ['task_id' => $task->id, 'occurrence_id' => $occurrence->id]);

    $match->delete();

    expect($occurrence->fresh()->cancelled_at)->not->toBeNull();
    $this->get(linkPath($team).'/'.$daan->id)->assertSee('Drive')->assertSee('Cancelled');
});

it('lets staff assign a player and plan from the tasks list', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    $daan = rosterMember($team, 'Daan de Kok');
    plannedMatch($team, ['scheduled_at' => '2026-11-14 09:30']);
    $task = matchTask($team, attributes: ['title' => 'Drive', 'min_people' => 2]);
    $occurrence = $task->occurrences()->sole();

    $this->actingAs($coach)->get("/sports-management/{$team->id}/tasks")->assertOk()->assertSee('<title>Task planning · JO11-2</title>', false)->assertSee('Drive')->assertSee('Needs people');

    $this->actingAs($coach)->post("/sports-management/{$team->id}/tasks/{$task->id}/enlist", ['team_member_id' => $daan->id, 'occurrence_id' => $occurrence->id])->assertRedirect();
    expect(TaskEnlistment::sole()->created_by)->toBe($coach->id);

    $this->actingAs($coach)->get("/sports-management/{$team->id}/tasks")->assertSee('Daan de Kok')->assertSee('assigned');
    $this->actingAs(referee($team))->get("/sports-management/{$team->id}/tasks")->assertForbidden();
});

it('shows the team link on the team page once there are tasks, or a note in per-player mode', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    rosterMember($team, 'Daan de Kok');

    $this->actingAs($coach)->get("/sports-management/{$team->id}")->assertDontSee(linkPath($team));
    matchTask($team);
    $this->actingAs($coach)->get("/sports-management/{$team->id}")->assertSee(linkPath($team));

    $this->actingAs($coach)->post("/sports-management/{$team->id}", ['name' => $team->name, 'sport' => 'football', 'season' => $team->season, 'tasks_enabled' => '1', 'tasks_link_mode' => 'per_player'])->assertRedirect();
    $this->actingAs($coach)->get("/sports-management/{$team->id}")
        ->assertDontSee(linkPath($team))
        ->assertSee('Copy link');
});

it('names the match next to the date for match tasks, for parents and staff', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    $daan = rosterMember($team, 'Daan de Kok');
    plannedMatch($team, ['scheduled_at' => '2026-11-14 09:30', 'opponent_name' => 'FC Rivals JO11-1']);
    plannedMatch($team, ['scheduled_at' => '2026-11-21 09:30', 'opponent_name' => 'VV Home', 'home_away' => \Kopling\SportsManagement\HomeAway::Home]);
    matchTask($team, TaskSchedule::AllMatches, ['title' => 'Oranges']);

    $this->get(linkPath($team).'/'.$daan->id)
        ->assertSee('09:30 · away at FC Rivals JO11-1')
        ->assertSee('Saturday 14 November, 09:30 · away at FC Rivals JO11-1')
        ->assertSee('09:30 · home vs VV Home');

    $this->actingAs($coach)->get("/sports-management/{$team->id}/tasks")->assertSee('away at FC Rivals JO11-1')->assertSee('home vs VV Home');
});

it('gives every live page the team\'s sport, like the public match page, for layouts that use it', function () {
    $team = tasksTeam(coach());
    $daan = rosterMember($team, 'Daan de Kok');

    $this->get(linkPath($team))->assertViewHas('sport', $team->sport);
    $this->get(linkPath($team).'/'.$daan->id)->assertViewHas('sport', $team->sport);
});

it('groups a match\'s tasks under that match, ordered by kickoff, without week or day headings', function () {
    $team = tasksTeam(coach());
    $daan = rosterMember($team, 'Daan de Kok');
    plannedMatch($team, ['scheduled_at' => '2026-11-14 09:30', 'opponent_name' => 'FC Early']);
    plannedMatch($team, ['scheduled_at' => '2026-11-14 13:00', 'opponent_name' => 'FC Late']);
    matchTask($team, attributes: ['title' => 'Drive']);
    matchTask($team, attributes: ['title' => 'Fruit']);
    datesTask($team, ['2026-11-10'], ['title' => 'Wash shirts']);
    datesTask($team, ['2026-11-11'], ['title' => 'Line the pitch']);

    $html = $this->get(linkPath($team).'/'.$daan->id)
        ->assertSeeInOrder(['Wash shirts', 'Line the pitch', 'Away at FC Early', 'Drive', 'Fruit', 'Away at FC Late', 'Drive', 'Fruit'])
        ->assertDontSee('Saturday 14 November</h2>', false)
        ->getContent();

    expect(substr_count($html, '<ul class="list">'))->toBe(3);
});

it('shows who actually does it, on behalf of the player, to parents and staff', function () {
    $coach = coach();
    $team = tasksTeam($coach);
    $daan = rosterMember($team, 'Daan de Kok');
    $task = datesTask($team, ['2026-11-28'], ['title' => 'Game leader']);
    enlistOn($task, $daan, '2026-11-28', ['note' => 'Tim (brother)']);

    $this->get(linkPath($team).'/'.$daan->id)->assertSeeInOrder(['Tim (brother)', 'for Daan K.'], false);
    $this->actingAs($coach)->get("/sports-management/{$team->id}/tasks")->assertSeeInOrder(['Tim (brother)', 'for Daan de Kok'], false);
});

it('puts season roles and staff assignments collapsed at the bottom, and colours the counts', function () {
    $this->travelTo('2026-11-01 12:00');
    $team = tasksTeam(coach());
    $daan = rosterMember($team, 'Daan de Kok');
    $short = datesTask($team, ['2026-11-14'], ['title' => 'Short task', 'min_people' => 2]);
    $full = datesTask($team, ['2026-11-15'], ['title' => 'Full task', 'max_people' => 1]);
    enlistOn($full, $daan, '2026-11-15');
    datesTask($team, ['2026-11-16'], ['title' => 'Staff task', 'staff_only' => true, 'min_people' => 1]);
    datesTask($team, ['2026-11-30'], ['title' => 'Season role', 'schedule' => TaskSchedule::Season, 'enrolment' => TaskEnrolment::WholeTask, 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-30']);

    $html = $this->get(linkPath($team).'/'.$daan->id)->assertSeeInOrder(['Short task', 'Full task', 'Fixed roles and staff assignments', 'Season role', 'Staff task'])->getContent();

    expect(preg_match('/Short task.*?badge badge-sm shrink-0 whitespace-nowrap badge-warning/s', $html))->toBe(1)
        ->and(preg_match('/Full task.*?badge badge-sm shrink-0 whitespace-nowrap badge-success/s', $html))->toBe(1)
        ->and(preg_match('/Staff task.*?badge badge-sm shrink-0 whitespace-nowrap badge-ghost/s', $html))->toBe(1)
        ->and(substr_count($html, 'Staff task'))->toBe(substr_count(substr($html, strpos($html, 'Fixed roles and staff assignments')), 'Staff task'));
});
