<?php

declare(strict_types=1);

use Kopling\SportsManagement\Position;
use Kopling\SportsManagement\RefereeDuty;
use Kopling\SportsManagement\TeamMatch;
use phpseclib4\Crypt\RSA;

beforeEach(function () {
    static $key;
    $key ??= RSA::createKey(2048);
    config()->set('passport.private_key', (string) $key);
    config()->set('passport.public_key', (string) $key->getPublicKey());
    $this->travelTo('2026-11-21 09:30');
});

function kickOffReady($coach, array $starters): TeamMatch
{
    $team = optedInTeam($coach);
    $match = plannedMatch($team, ['scheduled_at' => '2026-11-21 09:30']);
    foreach ($starters as $name => $zone) {
        $match->lineup()->create(['team_member_id' => rosterMember($team, $name)->id, 'zone' => $zone]);
    }

    return $match;
}

function memberId(TeamMatch $match, string $name): string
{
    return $match->team->members()->whereHas('person', fn ($query) => $query->where('name', $name))->value('id');
}

it('tracks a match live: kick-off, goals both ways, a swap, a break and the final whistle', function () {
    $coach = coach();
    $match = kickOffReady($coach, ['John' => 'F', 'Kyle' => 'M']);
    $charlie = rosterMember($match->team, 'Charlie');
    $token = smToken($coach, [SM.'::read', SM.'::write']);
    $ids = ['team_id' => $match->team_id, 'match_id' => $match->id];
    $john = memberId($match, 'John');
    $kyle = memberId($match, 'Kyle');

    $state = smResult(smCall($this, $token, 'start-period', $ids + ['type' => 'play']));
    expect($state['state'])->toBe('live')
        ->and(collect($state['on_field'])->pluck('name')->all())->toEqualCanonicalizing(['John', 'Kyle'])
        ->and(collect($state['bench'])->pluck('id')->all())->toBe([$charlie->id]);

    $this->travel(12)->minutes();
    $state = smResult(smCall($this, $token, 'record-goal', $ids + ['for' => 'us', 'scorer_id' => $john, 'assist_id' => $kyle]));
    expect($state['score'])->toBe(['us' => 1, 'them' => 0])
        ->and($state['latest_events'][0])->toMatchArray(['type' => 'goal', 'minute' => 12, 'scorer' => 'John', 'assist' => 'Kyle']);

    $this->travel(3)->minutes();
    expect(smResult(smCall($this, $token, 'record-goal', $ids + ['for' => 'opponent']))['score'])->toBe(['us' => 1, 'them' => 1]);

    $this->travel(5)->minutes();
    $state = smResult(smCall($this, $token, 'substitute', $ids + ['off_player_id' => $john, 'on_player_id' => $charlie->id]));
    expect($state['recorded'])->toHaveCount(2)
        ->and(collect($state['on_field'])->pluck('zone', 'name')->all())->toEqual(['Charlie' => 'F', 'Kyle' => 'M']);

    $state = smResult(smCall($this, $token, 'start-period', $ids + ['type' => 'break']));
    expect($state['running_period']['type'])->toBe('break')->and($state['minute'])->toBe(20);

    $this->travel(5)->minutes();
    smResult(smCall($this, $token, 'start-period', $ids + ['type' => 'play']));
    $this->travel(20)->minutes();
    $state = smResult(smCall($this, $token, 'end-period', $ids));

    $timeline = $match->fresh()->timeline();
    expect($state['state'])->toBe('ended')
        ->and($state['minute'])->toBe(40)
        ->and($timeline->playedSeconds())->toEqual([$john => 20 * 60, $kyle => 40 * 60, $charlie->id => 20 * 60]);
});

it('records a substitution and a goal at an earlier minute, keeping the zone of the player going off', function () {
    $coach = coach();
    $match = kickOffReady($coach, ['John' => 'D']);
    $charlie = rosterMember($match->team, 'Charlie');
    $token = smToken($coach, [SM.'::read', SM.'::write']);
    $ids = ['team_id' => $match->team_id, 'match_id' => $match->id];

    smResult(smCall($this, $token, 'start-period', $ids + ['type' => 'play']));
    $this->travel(15)->minutes();

    smResult(smCall($this, $token, 'substitute', $ids + ['off_player_id' => memberId($match, 'John'), 'on_player_id' => $charlie->id, 'minute' => 10]));
    smResult(smCall($this, $token, 'record-goal', $ids + ['for' => 'us', 'scorer_id' => $charlie->id, 'minute' => 12]));

    $timeline = $match->fresh()->timeline();
    expect($timeline->onField())->toEqual([$charlie->id => Position::Defender])
        ->and($timeline->playedSeconds()[$charlie->id])->toBe(5 * 60)
        ->and($match->goals()->sole()->offset_seconds)->toBe(12 * 60);
});

it('takes back a recorded swap and goal by their ids', function () {
    $coach = coach();
    $match = kickOffReady($coach, ['John' => 'F']);
    $charlie = rosterMember($match->team, 'Charlie');
    $token = smToken($coach, [SM.'::read', SM.'::write']);
    $ids = ['team_id' => $match->team_id, 'match_id' => $match->id];

    smResult(smCall($this, $token, 'start-period', $ids + ['type' => 'play']));
    $this->travel(5)->minutes();
    $goal = smResult(smCall($this, $token, 'record-goal', $ids + ['for' => 'us', 'scorer_id' => $charlie->id]))['recorded'];
    $swap = smResult(smCall($this, $token, 'substitute', $ids + ['off_player_id' => memberId($match, 'John'), 'on_player_id' => $charlie->id]))['recorded'];

    $state = smResult(smCall($this, $token, 'delete-match-event', $ids + ['event_ids' => [...$goal, ...$swap]]));

    expect($state['score'])->toBe(['us' => 0, 'them' => 0])
        ->and(collect($state['on_field'])->pluck('name')->all())->toBe(['John'])
        ->and(smError(smCall($this, $token, 'delete-match-event', $ids + ['event_ids' => $goal])))->toContain('not found');
});

it('refuses to take off a player who is not on the field, or bring one on without a zone', function () {
    $coach = coach();
    $match = kickOffReady($coach, ['John' => 'F']);
    $charlie = rosterMember($match->team, 'Charlie');
    $token = smToken($coach, [SM.'::read', SM.'::write']);
    $ids = ['team_id' => $match->team_id, 'match_id' => $match->id];
    smResult(smCall($this, $token, 'start-period', $ids + ['type' => 'play']));

    expect(smError(smCall($this, $token, 'substitute', $ids + ['off_player_id' => $charlie->id])))->toContain('not on the field')
        ->and(smError(smCall($this, $token, 'substitute', $ids + ['on_player_id' => $charlie->id])))->toContain('zone')
        ->and(smError(smCall($this, $token, 'record-goal', $ids + ['for' => 'us', 'minute' => 999])))->toContain('minute');
});

it('leaves delegated duties to the referee and substitutions to the coaches', function () {
    $coach = coach();
    $match = kickOffReady($coach, ['John' => 'F']);
    $ref = referee($match->team);
    assignReferee($match, $ref, [RefereeDuty::Timing, RefereeDuty::Scoring]);
    $ids = ['team_id' => $match->team_id, 'match_id' => $match->id];
    $coachToken = smToken($coach, [SM.'::read', SM.'::write']);
    $refToken = smToken($ref, [SM.'::read', SM.'::write']);

    expect(smError(smCall($this, $coachToken, 'start-period', $ids + ['type' => 'play'])))->toContain('referee handles');
    smResult(smCall($this, $refToken, 'start-period', $ids + ['type' => 'play']));

    expect(smResult(smCall($this, $refToken, 'live-match', $ids))['state'])->toBe('live')
        ->and(smError(smCall($this, $refToken, 'substitute', $ids + ['off_player_id' => memberId($match, 'John')])))->toContain('coaches');
});

it('hides the live tools from a token without the write scope or a person without track-matches', function () {
    $names = function (string $token) {
        app('auth')->forgetGuards();

        return collect($this->withToken($token)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], ['Accept' => 'application/json, text/event-stream'])->json('result.tools'))->pluck('name')->all();
    };

    expect($names(smToken(coach())))->toContain(SM.'__live-match')->not->toContain(SM.'__record-goal');

    $viewer = coach('Viewer', 'viewer@example.test', ['access-sports-management']);
    expect($names(smToken($viewer, [SM.'::read', SM.'::write'])))
        ->toContain(SM.'__live-match')->not->toContain(SM.'__record-goal');
});
