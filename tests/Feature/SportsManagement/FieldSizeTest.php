<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Kopling\SportsManagement\Actions\SaveMatch;
use Kopling\SportsManagement\TeamFormatPreset;
use Kopling\SportsManagement\TeamMatch;

function fieldSizeUrl(TeamMatch $match): string
{
    return "/sports-management/{$match->team_id}/matches/{$match->id}/field-size";
}

afterEach(fn () => Carbon::setTestNow());

it('overrides the format\'s players on the field before kick-off, the format\'s own number clearing it', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    [$a, $b, $c] = [rosterMember($team, 'Anna'), rosterMember($team, 'Bram'), rosterMember($team, 'Cas')];
    $match = plannedMatch($team, ['format_preset_id' => TeamFormatPreset::create(['name' => 'JO7', 'players_on_field' => 2])->id]);

    $this->actingAs($coach)->post(fieldSizeUrl($match), ['players_on_field' => 3])->assertRedirect(trackUrl($match));
    expect($match->fresh()->effectivePlayersOnField())->toBe(3)
        ->and($match->fieldSizes()->count())->toBe(0);

    lineup($this, $coach, $match, [$a->id => 'K', $b->id => 'D', $c->id => 'F']);

    $this->actingAs($coach)->post(fieldSizeUrl($match), ['players_on_field' => ''])->assertSessionHasErrors('players_on_field');
    $this->actingAs($coach)->post(fieldSizeUrl($match), ['players_on_field' => 2])->assertSessionHasNoErrors();
    expect($match->fresh()->players_on_field)->toBeNull()
        ->and($match->fresh()->effectivePlayersOnField())->toBe(2);
});

it('leaves players on the field out of the match form; planning sets it before kick-off only, the format\'s number clearing it', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $team->update(['format_preset_id' => TeamFormatPreset::create(['name' => 'JO10', 'players_on_field' => 6])->id]);
    $match = plannedMatch($team);
    $form = ['opponent_name' => 'FC Rivals', 'home_away' => 'away', 'scheduled_at' => '2026-10-10T09:30'];

    $this->actingAs($coach)->get("/sports-management/{$team->id}/matches/{$match->id}")->assertOk()->assertDontSee('name="players_on_field"', false);

    expect(SaveMatch::handle($team, $form + ['players_on_field' => 5], $match)->players_on_field)->toBe(5)
        ->and(SaveMatch::handle($team, $form + ['players_on_field' => 6], $match)->players_on_field)->toBeNull();

    SaveMatch::handle($team, $form + ['players_on_field' => 5], $match);
    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);

    expect(fn () => SaveMatch::handle($team, $form + ['players_on_field' => 4], $match->fresh()))->toThrow(ValidationException::class);
    $this->actingAs($coach)->post("/sports-management/{$team->id}/matches/{$match->id}", $form)->assertSessionHasNoErrors();
    expect($match->fresh()->players_on_field)->toBe(5);
});

it('changes the players on the field from now on during the match, and divides the fair share over both', function () {
    Carbon::setTestNow('2026-10-10 09:30:00');
    $coach = coach();
    $team = staffedTeam($coach);
    $team->update(['format_preset_id' => TeamFormatPreset::create(['name' => 'Duo', 'players_on_field' => 2, 'play_minutes' => 40])->id]);
    [$a, $b, $c, $d] = [rosterMember($team, 'Anna'), rosterMember($team, 'Bram'), rosterMember($team, 'Cas'), rosterMember($team, 'Dirk')];
    $match = plannedMatch($team);
    lineup($this, $coach, $match, [$a->id => 'D', $b->id => 'F']);
    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);

    Carbon::setTestNow('2026-10-10 09:50:00');
    $this->actingAs($coach)->post(fieldSizeUrl($match), ['players_on_field' => 3])->assertRedirect(trackUrl($match));

    $match = $match->fresh();
    expect($match->players_on_field)->toBeNull()
        ->and($match->effectiveMaxOnField($match->timeline()))->toBe(3)
        ->and($match->fairShareSeconds(4))->toBe(25 * 60)
        ->and($match->fairBenchSeconds(4))->toBe(15 * 60);

    $this->actingAs($coach)->post(trackUrl($match, '/field'), ['team_member_id' => $c->id, 'zone' => 'M'])->assertSessionHasNoErrors();
    $this->actingAs($coach)->post(trackUrl($match, '/field'), ['team_member_id' => $d->id, 'zone' => 'M'])->assertSessionHasErrors('zone');
});

it('lets lowering the number leave everyone on, refusing more until someone comes off', function () {
    Carbon::setTestNow('2026-10-10 09:30:00');
    $coach = coach();
    $team = staffedTeam($coach);
    $team->update(['format_preset_id' => TeamFormatPreset::create(['name' => 'Trio', 'players_on_field' => 3])->id]);
    [$a, $b, $c, $d] = [rosterMember($team, 'Anna'), rosterMember($team, 'Bram'), rosterMember($team, 'Cas'), rosterMember($team, 'Dirk')];
    $match = plannedMatch($team);
    lineup($this, $coach, $match, [$a->id => 'D', $b->id => 'M', $c->id => 'F']);
    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);

    Carbon::setTestNow('2026-10-10 09:40:00');
    $this->actingAs($coach)->post(fieldSizeUrl($match), ['players_on_field' => 2])->assertSessionHasNoErrors();
    expect($match->fresh()->timeline()->onField())->toHaveCount(3);

    $this->actingAs($coach)->get(trackUrl($match))->assertOk()->assertSee('3 / 2');
    $this->actingAs($coach)->post(trackUrl($match, '/field'), ['team_member_id' => $d->id, 'replace_team_member_id' => $c->id])->assertSessionHasNoErrors();
    $this->actingAs($coach)->post(trackUrl($match, '/field'), ['team_member_id' => $a->id])->assertSessionHasNoErrors();
    expect($match->fresh()->timeline()->onField())->toHaveCount(2);
});

it('undoes a change of players on the field', function () {
    Carbon::setTestNow('2026-10-10 09:30:00');
    $coach = coach();
    $team = staffedTeam($coach);
    $match = plannedMatch($team, ['players_on_field' => 6]);
    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);

    $this->actingAs($coach)->post(fieldSizeUrl($match), ['players_on_field' => 5]);
    $this->actingAs($coach)->get(trackUrl($match))->assertSee(__('kopling-sports-management::messages.recorded_field_size'));
    $this->actingAs($coach)->post(trackUrl($match, '/undo'));

    expect($match->fieldSizes()->count())->toBe(0);
});

it('requires a number during the match and refuses one once it has ended', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $match = plannedMatch($team);
    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);

    $this->actingAs($coach)->post(fieldSizeUrl($match), ['players_on_field' => ''])->assertSessionHasErrors('players_on_field');

    $this->actingAs($coach)->post(trackUrl($match, '/periods/'.$match->periods()->first()->id.'/end'));
    $this->actingAs($coach)->post(fieldSizeUrl($match), ['players_on_field' => 5])->assertSessionHasErrors('players_on_field');
    expect($match->fieldSizes()->count())->toBe(0);
});

it('offers the change in the match menu to coaches only, not to referees', function () {
    $coach = coach();
    $team = staffedTeam($coach);
    $match = plannedMatch($team);
    $ref = referee($team);
    assignReferee($match, $ref);
    $label = __('kopling-sports-management::messages.players_on_field');

    $this->actingAs($coach)->get(trackUrl($match))->assertOk()->assertSee('data-modal-show="modal-field-size"', false)->assertSee($label)->assertSee('name="players_on_field" value="" placeholder="" class="input w-full"', false)->assertSee('min="1" ', false);
    $this->actingAs($ref)->get(trackUrl($match))->assertOk()->assertDontSee('data-modal-show="modal-field-size"', false);
    $this->actingAs($ref)->post(fieldSizeUrl($match), ['players_on_field' => 5])->assertForbidden();
});

it('prefills the number currently played with, and records nothing when it stays the same', function () {
    Carbon::setTestNow('2026-10-10 09:30:00');
    $coach = coach();
    $team = staffedTeam($coach);
    $team->update(['format_preset_id' => TeamFormatPreset::create(['name' => 'JO10', 'players_on_field' => 6])->id]);
    $match = plannedMatch($team);

    $this->actingAs($coach)->get(trackUrl($match))->assertSee('name="players_on_field" value="6"', false);

    $this->actingAs($coach)->post(trackUrl($match, '/periods/start'), ['type' => 'play']);
    Carbon::setTestNow('2026-10-10 09:40:00');
    $this->actingAs($coach)->post(fieldSizeUrl($match), ['players_on_field' => 6])->assertSessionHasNoErrors();
    expect($match->fieldSizes()->count())->toBe(0);

    $this->actingAs($coach)->post(fieldSizeUrl($match), ['players_on_field' => 5]);
    $this->actingAs($coach)->get(trackUrl($match))->assertSee('name="players_on_field" value="5"', false);
});
