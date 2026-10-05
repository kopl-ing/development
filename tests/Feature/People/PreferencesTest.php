<?php

declare(strict_types=1);

use Kopling\Core\People\Person;
use Kopling\Core\Settings\PersonSettings;
use Kopling\Core\Ux\Alert;

it('shows core\'s alert preferences with their defaults', function () {
    $person = Person::create(['name' => 'Coach', 'email' => 'coach@example.test', 'password' => 'secret']);

    $this->actingAs($person)->get('/settings')
        ->assertOk()
        ->assertSee('name="'.Alert::VIBRATE.'"', false)
        ->assertSee('name="'.Alert::SOUND.'"', false)
        ->assertSee('name="'.Alert::WAKE_LOCK.'"', false);

    expect(Alert::preferences($person))->toBe(['vibrate' => true, 'sound' => Alert::DEFAULT_SOUND, 'wakeLock' => true]);
});

it('saves preferences per person and exposes them to the page', function () {
    $person = Person::create(['name' => 'Coach', 'email' => 'coach@example.test', 'password' => 'secret']);
    $other = Person::create(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'secret']);

    $this->actingAs($person)->post('/settings/preferences', [
        Alert::VIBRATE => '0',
        Alert::SOUND => 'whistle',
        Alert::WAKE_LOCK => '0',
    ])->assertRedirect('/settings')->assertSessionHasNoErrors();

    expect(Alert::preferences($person))->toBe(['vibrate' => false, 'sound' => 'whistle', 'wakeLock' => false])
        ->and(Alert::preferences($other))->toBe(['vibrate' => true, 'sound' => Alert::DEFAULT_SOUND, 'wakeLock' => true]);

    $this->actingAs($person)->get('/')
        ->assertSee('<meta name="kopling-alert" content="'.e(json_encode(Alert::preferences($person))).'">', false);
});

it('refuses a sound or toggle value outside the declared options', function () {
    $person = Person::create(['name' => 'Coach', 'email' => 'coach@example.test', 'password' => 'secret']);

    $this->actingAs($person)->post('/settings/preferences', [Alert::SOUND => 'https://evil.test/a.mp3', Alert::VIBRATE => 'yes'])
        ->assertSessionHasErrors([Alert::SOUND, Alert::VIBRATE]);

    expect(PersonSettings::all($person))->toBe([]);
});

it('drops a person\'s preferences along with the person', function () {
    $person = Person::create(['name' => 'Coach', 'email' => 'coach@example.test', 'password' => 'secret']);
    PersonSettings::set($person, Alert::SOUND, 'chime');

    $person->delete();

    expect(DB::table('person_settings')->count())->toBe(0);
});

it('gives a guest the default alert preferences', function () {
    $this->get('/')->assertSee('content="'.e(json_encode(Alert::preferences(null))).'"', false);
});

it('offers a play button for the sound choice', function () {
    $person = Person::create(['name' => 'Coach', 'email' => 'coach@example.test', 'password' => 'secret']);

    $this->actingAs($person)->get('/settings')->assertSee('data-alert-preview="'.Alert::SOUND.'"', false);
});
