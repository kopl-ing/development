<?php

declare(strict_types=1);

use Kopling\SportsManagement\HomeAway;
use Kopling\SportsManagement\StaffRole;
use Kopling\SportsManagement\TaskBalance;
use Kopling\SportsManagement\TaskEnlistment;
use Kopling\SportsManagement\TaskEnlistmentSkip;
use Kopling\SportsManagement\TaskEnrolment;
use Kopling\SportsManagement\TaskSchedule;
use Kopling\SportsManagement\TeamMatch;
use phpseclib4\Crypt\RSA;

beforeEach(function () {
    static $key;
    $key ??= RSA::createKey(2048);
    config()->set('passport.private_key', (string) $key);
    config()->set('passport.public_key', (string) $key->getPublicKey());
    $this->travelTo('2026-11-20 12:00');
});

function writer($coach): string
{
    return smToken($coach, [SM.'::read', SM.'::write']);
}

it('plans, moves and cancels a match through the same rules as the form', function () {
    $coach = coach();
    $team = optedInTeam($coach);
    $task = matchTask($team, TaskSchedule::AllMatches, ['title' => 'Drive']);
    $token = writer($coach);

    expect(smError(smCall($this, $token, 'plan-match', ['team_id' => $team->id, 'home_away' => 'away', 'scheduled_at' => '2026-11-28T09:30'])))
        ->toContain('opponent');

    $planned = smResult(smCall($this, $token, 'plan-match', ['team_id' => $team->id, 'opponent_name' => 'FC Rivals', 'home_away' => 'away', 'scheduled_at' => '2026-11-28T09:30']));
    expect($planned)->toMatchArray(['opponent' => 'FC Rivals', 'home_away' => 'away', 'state' => 'planned'])
        ->and($task->occurrences()->sole()->date->toDateString())->toBe('2026-11-28');

    smResult(smCall($this, $token, 'update-match', ['team_id' => $team->id, 'match_id' => $planned['id'], 'scheduled_at' => '2026-12-05T10:00']));
    $match = TeamMatch::sole();
    expect([$match->opponent_name, $match->home_away, $match->scheduled_at->format('Y-m-d H:i')])->toBe(['FC Rivals', HomeAway::Away, '2026-12-05 10:00'])
        ->and($task->occurrences()->sole()->date->toDateString())->toBe('2026-12-05');

    smCall($this, $token, 'cancel-match', ['team_id' => $team->id, 'match_id' => $match->id]);
    expect(TeamMatch::count())->toBe(0);
});

it('refuses to cancel a match that was played', function () {
    $coach = coach();
    $team = optedInTeam($coach);
    $match = plannedMatch($team, ['scheduled_at' => '2026-11-14 09:30']);
    $match->periods()->create(['sequence' => 1, 'type' => 'play', 'duration_seconds' => 1500]);

    expect(smError(smCall($this, writer($coach), 'cancel-match', ['team_id' => $team->id, 'match_id' => $match->id])))->toContain('can only be deleted on the site');
});

it('sets availability only for the players given', function () {
    $coach = coach();
    $team = optedInTeam($coach);
    $daan = rosterMember($team, 'Daan de Kok');
    $jip = rosterMember($team, 'Jip Vos');
    $match = plannedMatch($team, ['scheduled_at' => '2026-11-28 09:30']);
    $match->availabilities()->create(['team_member_id' => $jip->id, 'status' => 'available']);

    smResult(smCall($this, writer($coach), 'set-availability', ['team_id' => $team->id, 'match_id' => $match->id, 'availability' => [$daan->id => 'absent']]));

    expect($match->availabilities()->pluck('status', 'team_member_id')->map->value->all())->toEqual([$jip->id => 'available', $daan->id => 'absent']);
});

it('adds a player and takes one off the team, dropping their future tasks', function () {
    $coach = coach();
    $team = optedInTeam($coach);
    $daan = rosterMember($team, 'Daan de Kok');
    $drive = datesTask($team, ['2026-11-14', '2026-11-28']);
    enlistOn($drive, $daan, '2026-11-14');
    enlistOn($drive, $daan, '2026-11-28');
    $token = writer($coach);

    expect(smResult(smCall($this, $token, 'add-player', ['team_id' => $team->id, 'name' => 'Jip Vos', 'jersey_number' => '9'])))
        ->toMatchArray(['name' => 'Jip Vos', 'jersey_number' => '9']);

    smResult(smCall($this, $token, 'update-player', ['team_id' => $team->id, 'player_id' => $daan->id, 'left_on' => '2026-11-21']));
    expect($daan->fresh()->left_on->toDateString())->toBe('2026-11-21')
        ->and($daan->person->fresh()->name)->toBe('Daan de Kok')
        ->and(TaskEnlistment::count())->toBe(1);
});

it('creates and partly updates a task, keeping what was not given', function () {
    $coach = coach();
    $team = optedInTeam($coach);
    $token = writer($coach);

    $created = smResult(smCall($this, $token, 'create-task', [
        'team_id' => $team->id, 'title' => 'Wash kit', 'schedule' => 'dates', 'enrolment' => 'per_occurrence',
        'undo_window' => 'day_before', 'weight' => 2, 'dates' => [['date' => '2026-11-28'], ['date' => '2026-12-05', 'time' => '10:00']],
    ]));
    expect($created['dates'])->toBe(2);

    smResult(smCall($this, $token, 'update-task', ['team_id' => $team->id, 'task_id' => $created['id'], 'min_people' => 2]));
    $task = \Kopling\SportsManagement\Task::sole();
    expect([$task->title, $task->weight, $task->min_people, $task->undo_window->value, $task->occurrences()->count()])
        ->toBe(['Wash kit', 2, 2, 'day_before', 2]);
});

it('swaps an ill regular trainer for someone else on one match, and back', function () {
    $coach = coach();
    $team = optedInTeam($coach);
    $daan = rosterMember($team, 'Daan de Kok');
    $jip = rosterMember($team, 'Jip Vos');
    plannedMatch($team, ['scheduled_at' => '2026-11-28 09:30']);
    plannedMatch($team, ['scheduled_at' => '2026-12-05 09:30']);
    $task = matchTask($team, TaskSchedule::AllMatches, ['title' => 'Trainer', 'enrolment' => TaskEnrolment::WholeTask, 'max_people' => 1]);
    $token = writer($coach);

    $regular = smResult(smCall($this, $token, 'assign-task', ['team_id' => $team->id, 'task_id' => $task->id, 'player_id' => $daan->id]));
    $first = $task->occurrences()->orderBy('date')->first();

    expect(smError(smCall($this, $token, 'assign-task', ['team_id' => $team->id, 'task_id' => $task->id, 'player_id' => $jip->id, 'date_id' => $first->id])))->toContain('closed');

    smCall($this, $token, 'unassign-task', ['team_id' => $team->id, 'enlistment_id' => $regular['enlistment_id'], 'date_id' => $first->id]);
    smResult(smCall($this, $token, 'assign-task', ['team_id' => $team->id, 'task_id' => $task->id, 'player_id' => $jip->id, 'date_id' => $first->id]));

    $planning = smResult(smCall($this, $token, 'task-planning', ['team_id' => $team->id]));
    expect(collect($planning['dates'])->map(fn ($date) => [$date['date'], collect($date['people'])->pluck('player')->all()])->all())
        ->toBe([['2026-11-28', ['Jip Vos']], ['2026-12-05', ['Daan de Kok']]]);

    TaskEnlistment::where('team_member_id', $jip->id)->delete();
    smResult(smCall($this, $token, 'assign-task', ['team_id' => $team->id, 'task_id' => $task->id, 'player_id' => $daan->id, 'date_id' => $first->id]));
    expect(TaskEnlistmentSkip::count())->toBe(0);
});

it('needs a date for per-date tasks and records outcomes on passed ones', function () {
    $coach = coach();
    $team = optedInTeam($coach);
    $team->update(['tasks_approve_completion' => true]);
    $daan = rosterMember($team, 'Daan de Kok');
    $task = datesTask($team, ['2026-11-14'], ['weight' => 3]);
    $token = writer($coach);

    expect(smError(smCall($this, $token, 'assign-task', ['team_id' => $team->id, 'task_id' => $task->id, 'player_id' => $daan->id])))->toContain('date by date');

    $date = $task->occurrences()->sole();
    $assigned = smResult(smCall($this, $token, 'assign-task', ['team_id' => $team->id, 'task_id' => $task->id, 'player_id' => $daan->id, 'date_id' => $date->id]));
    expect(TaskBalance::for($team)->sole()['points'])->toBe(3);

    smResult(smCall($this, $token, 'record-outcome', ['team_id' => $team->id, 'enlistment_id' => $assigned['enlistment_id'], 'date_id' => $date->id, 'outcome' => 'no_show']));
    expect(TaskBalance::for($team)->sole())->toMatchArray(['points' => 0, 'no_shows' => 1]);

    $past = smResult(smCall($this, $token, 'task-planning', ['team_id' => $team->id, 'past_weeks' => 2]))['past'];
    expect($past[0]['people'][0])->toMatchArray(['player' => 'Daan de Kok', 'outcome' => 'no_show']);
});

it('keeps write tools to coaches with the matching permission', function () {
    $coach = coach(permissions: ['access-sports-management', 'manage-teams']);
    $team = optedInTeam($coach);
    $ref = referee($team);
    plannedMatch($team, ['scheduled_at' => '2026-11-28 09:30']);

    smCall($this, writer($coach), 'plan-match', ['team_id' => $team->id])->assertJsonPath('error.code', -32602);
    app('auth')->forgetGuards();
    $names = collect($this->withToken(writer($coach))->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], ['Accept' => 'application/json, text/event-stream'])->json('result.tools'))->pluck('name');
    expect($names)->toContain(SM.'__add-player')->not->toContain(SM.'__plan-match');

    expect(smError(smCall($this, writer($ref), 'add-player', ['team_id' => $team->id, 'name' => 'X'])))->toContain('Only the team');
});
