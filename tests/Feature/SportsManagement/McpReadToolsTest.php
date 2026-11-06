<?php

declare(strict_types=1);

use Kopling\Core\People\Group;
use Kopling\Core\People\Person as CorePerson;
use Kopling\Mcp\ToolCall;
use Kopling\OAuth\Person;
use Kopling\OAuth\Tokens;
use Kopling\SportsManagement\StaffRole;
use Kopling\SportsManagement\TaskEnrolment;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamFormatPreset;
use phpseclib4\Crypt\RSA;

const SM = 'kopling-sports-management';

beforeEach(function () {
    static $key;
    $key ??= RSA::createKey(2048);
    config()->set('passport.private_key', (string) $key);
    config()->set('passport.public_key', (string) $key->getPublicKey());
    $this->travelTo('2026-11-20 12:00');
});

function grant(CorePerson $person, array $permissions): CorePerson
{
    $group = Group::create(['name' => 'Extra '.$person->id]);
    foreach ($permissions as $permission) {
        $group->givePermissionTo($permission);
    }
    $person->groups()->attach($group);

    return $person;
}

function smToken(CorePerson $person, array $scopes = [SM.'::read']): string
{
    grant($person, ['kopling-mcp::use-mcp']);
    Tokens::personalClient();

    return Person::find($person->id)->createToken('test', ['kopling-mcp::use-mcp', ...$scopes])->accessToken;
}

function siteAdmin(): CorePerson
{
    return grant(coach('Admin', 'admin@example.test', []), ['kopling-sports-management::administer-teams-via-mcp']);
}

function smCall($test, string $token, string $tool, array $arguments = [])
{
    app('auth')->forgetGuards();

    return $test->withToken($token)->postJson('/mcp', [
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
        'params' => ['name' => SM.'__'.$tool, 'arguments' => $arguments],
    ], ['Accept' => 'application/json, text/event-stream']);
}

function smResult($response): mixed
{
    expect($response->json('result.isError'))->toBeFalse();

    return json_decode($response->json('result.content.0.text'), true);
}

function smError($response): string
{
    expect($response->json('result.isError'))->toBeTrue();

    return $response->json('result.content.0.text');
}

function optedInTeam(CorePerson $coach): Team
{
    $team = tasksTeam($coach);
    $team->update(['mcp_enabled' => true]);

    return $team;
}

it('offers staff the read tools, prefixed, and keeps the admin tools to site admins', function () {
    app('auth')->forgetGuards();
    $names = fn (string $token) => collect($this->withToken($token)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], ['Accept' => 'application/json, text/event-stream'])->json('result.tools'))->pluck('name')->all();

    $staff = $names(smToken(coach()));
    expect($staff)->toContain(SM.'__list-my-teams', SM.'__get-team', SM.'__list-matches', SM.'__get-match', SM.'__task-planning', SM.'__task-balance')
        ->not->toContain(SM.'__list-all-teams', SM.'__team-stats');

    app('auth')->forgetGuards();
    expect($names(smToken(siteAdmin())))->toContain(SM.'__list-all-teams', SM.'__team-stats');
});

it('lists only the teams that opted in, with the person\'s role', function () {
    $coach = coach();
    $open = optedInTeam($coach);
    staffedTeam($coach)->update(['name' => 'Closed']);

    expect(smResult(smCall($this, smToken($coach), 'list-my-teams')))->toBe([[
        'id' => $open->id, 'name' => 'JO11-2', 'club' => 'A', 'season' => '2026/2027', 'sport' => 'football',
        'format' => null, 'role' => 'coach', 'tasks_enabled' => true,
    ]]);
});

it('gives coaches the roster with names and numbers only, and refuses teams that did not opt in', function () {
    $coach = coach();
    $team = optedInTeam($coach);
    rosterMember($team, 'Daan de Kok')->update(['jersey_number' => '7']);
    rosterMember($team, 'Jip Vos')->update(['left_on' => '2026-11-01']);
    $token = smToken($coach);

    $response = smCall($this, $token, 'get-team', ['team_id' => $team->id]);
    $team = smResult($response);
    expect(collect($team['roster'])->map(fn ($player) => [$player['name'], $player['jersey_number'] ?? null, $player['left_on'] ?? null])->all())
        ->toBe([['Daan de Kok', '7', null], ['Jip Vos', null, '2026-11-01']])
        ->and($response->getContent())->not->toContain('coach@example.test')->not->toContain('token');

    $closed = staffedTeam($coach);
    expect(smError(smCall($this, $token, 'get-team', ['team_id' => $closed->id])))->toContain('not available to you');
});

it('lets a site admin reach any team, opted in or hidden, and logs it for the owner to see', function () {
    $owner = coach();
    $team = staffedTeam($owner);
    $team->delete();
    $admin = siteAdmin();

    expect(smResult(smCall($this, smToken($admin), 'get-team', ['team_id' => $team->id]))['hidden_by_moderation'])->toBeTrue()
        ->and(ToolCall::sole()->team_id)->toBe($team->id);

    $team->restore();
    $this->actingAs($owner)->get("/sports-management/{$team->id}")->assertSee('AI assistant activity')->assertSee('get-team')->assertSee('Admin');
    $other = coach('Other', 'other@example.test');
    $team->staff()->attach($other, ['role' => StaffRole::Coach->value]);
    $this->actingAs($other)->get("/sports-management/{$team->id}")->assertDontSee('AI assistant activity');
});

it('shows a referee only their own matches and no roster', function () {
    $coach = coach();
    $team = optedInTeam($coach);
    rosterMember($team, 'Daan de Kok');
    $ref = referee($team);
    assignReferee(plannedMatch($team, ['opponent_name' => 'Refereed FC', 'scheduled_at' => '2026-11-28 09:30']), $ref);
    plannedMatch($team, ['opponent_name' => 'Other FC', 'scheduled_at' => '2026-11-29 09:30']);
    $token = smToken($ref);

    expect(collect(smResult(smCall($this, $token, 'list-matches', ['team_id' => $team->id])))->pluck('opponent')->all())->toBe(['Refereed FC'])
        ->and(smResult(smCall($this, $token, 'get-team', ['team_id' => $team->id])))->not->toHaveKey('roster')
        ->and(smError(smCall($this, $token, 'task-balance', ['team_id' => $team->id])))->toContain('Only the team');
});

it('describes a played match with score and goal minutes', function () {
    $coach = coach();
    $team = optedInTeam($coach);
    $daan = rosterMember($team, 'Daan de Kok');
    $match = plannedMatch($team, ['opponent_name' => 'FC Rivals', 'scheduled_at' => '2026-11-14 09:30']);
    $period = $match->periods()->create(['sequence' => 1, 'type' => 'play', 'duration_seconds' => 1500]);
    $match->goals()->create(['period_id' => $period->id, 'opponent' => false, 'own_goal' => false, 'scorer_team_member_id' => $daan->id, 'offset_seconds' => 600]);
    $match->goals()->create(['period_id' => $period->id, 'opponent' => true, 'own_goal' => false, 'offset_seconds' => 900]);

    $result = smResult(smCall($this, smToken($coach), 'get-match', ['team_id' => $team->id, 'match_id' => $match->id]));

    expect($result['score'])->toBe(['us' => 1, 'them' => 1])
        ->and($result['goals'])->toBe([
            ['minute' => 10, 'for' => 'us', 'points' => 1, 'scorer' => 'Daan de Kok'],
            ['minute' => 15, 'for' => 'opponent', 'points' => 1],
        ]);
});

it('gives the match format with its play periods, so lineups follow quarters instead of assumed halves', function () {
    $coach = coach();
    $team = optedInTeam($coach);
    $team->update(['format_preset_id' => TeamFormatPreset::create(['name' => 'JO10', 'players_on_field' => 6, 'play_minutes' => 50, 'breaks' => 3])->id]);
    $match = plannedMatch($team, ['opponent_name' => 'FC Rivals', 'scheduled_at' => '2026-11-28 09:30', 'play_minutes' => 40]);
    $token = smToken($coach);

    $format = smResult(smCall($this, $token, 'get-team', ['team_id' => $team->id]))['format'];
    expect($format)->toMatchArray(['name' => 'JO10', 'players_on_field' => 6, 'play_minutes' => 50, 'play_periods' => 4, 'minutes_per_period' => 12.5])
        ->and($format['substitutions'])->toContain('any moment')
        ->and(smResult(smCall($this, $token, 'get-match', ['team_id' => $team->id, 'match_id' => $match->id]))['format'])
        ->toMatchArray(['play_minutes' => 40, 'play_periods' => 4, 'minutes_per_period' => 10]);
});

it('shows coaches the task planning and balance', function () {
    $coach = coach();
    $team = optedInTeam($coach);
    $daan = rosterMember($team, 'Daan de Kok');
    $drive = datesTask($team, ['2026-11-14', '2026-11-28'], ['title' => 'Drive', 'min_people' => 2, 'weight' => 2]);
    enlistOn($drive, $daan, '2026-11-14');
    enlistOn($drive, $daan, '2026-11-28', ['note' => 'dad']);
    $token = smToken($coach);

    $planning = smResult(smCall($this, $token, 'task-planning', ['team_id' => $team->id]));
    expect($planning['dates'])->toHaveCount(1)
        ->and($planning['dates'][0])->toMatchArray(['task' => 'Drive', 'date' => '2026-11-28', 'state' => 'needs_people', 'count' => 1])
        ->and($planning['dates'][0]['people'])->toHaveCount(1)
        ->and($planning['dates'][0]['people'][0])->toMatchArray(['player' => 'Daan de Kok', 'note' => 'dad']);

    expect(smResult(smCall($this, $token, 'task-balance', ['team_id' => $team->id])))->toBe([
        ['player' => 'Daan de Kok', 'points' => 2, 'planned_points' => 2, 'done' => 1, 'upcoming' => 1],
    ]);

    $team->update(['tasks_enabled' => false]);
    expect(smError(smCall($this, $token, 'list-tasks', ['team_id' => $team->id])))->toContain('switched off');
});

it('gives site admins every team and figures per team', function () {
    $coach = coach();
    $team = optedInTeam($coach);
    $hidden = staffedTeam($coach);
    $hidden->forceFill(['name' => 'Hidden', 'deleted_reason' => 'spam'])->save();
    $hidden->delete();
    $match = plannedMatch($team, ['scheduled_at' => '2026-11-14 09:30']);
    $period = $match->periods()->create(['sequence' => 1, 'type' => 'play', 'duration_seconds' => 1500]);
    $match->goals()->create(['period_id' => $period->id, 'opponent' => false, 'own_goal' => false, 'offset_seconds' => 60]);
    $token = smToken(siteAdmin());

    expect(collect(smResult(smCall($this, $token, 'list-all-teams')))->mapWithKeys(fn ($row) => [$row['name'] => $row['hidden_reason'] ?? null])->all())
        ->toBe(['Hidden' => 'spam', 'JO11-2' => null]);

    expect(smResult(smCall($this, $token, 'team-stats', ['team_id' => $team->id]))[0])
        ->toMatchArray(['matches_played' => 1, 'won' => 1, 'goals_for' => 1, 'goals_against' => 0]);
});

it('lets only the team owner switch assistant access', function () {
    $owner = coach();
    $team = staffedTeam($owner);
    $other = coach('Other', 'other@example.test');
    $team->staff()->attach($other, ['role' => StaffRole::Coach->value]);
    $form = ['name' => $team->name, 'sport' => 'football', 'season' => $team->season, 'mcp_enabled' => '1'];

    $this->actingAs($other)->post("/sports-management/{$team->id}", $form)->assertRedirect();
    expect($team->fresh()->mcp_enabled)->toBeFalse();

    $this->actingAs($owner)->get("/sports-management/{$team->id}")->assertSee('name="mcp_enabled"', false);
    $this->actingAs($owner)->post("/sports-management/{$team->id}", $form)->assertRedirect();
    expect($team->fresh()->mcp_enabled)->toBeTrue();
});
