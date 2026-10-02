<?php

declare(strict_types=1);

use Kopling\Core\People\Person;

it('sends a guest to log in instead of showing settings', function () {
    $this->get('/settings')->assertRedirect();
});

it('lets a signed-in person change their own name', function () {
    $person = Person::create(['name' => 'Old Name', 'email' => 'settings@example.test', 'password' => 'secret']);

    $this->actingAs($person)->get('/settings')->assertOk()->assertSee('Old Name');

    $this->actingAs($person)->post('/settings', ['name' => 'New Name'])
        ->assertRedirect('/settings')
        ->assertSessionHasNoErrors();

    expect($person->fresh()->name)->toBe('New Name');
});

it('requires a name', function () {
    $person = Person::create(['name' => 'Old Name', 'email' => 'settings@example.test', 'password' => 'secret']);

    $this->actingAs($person)->post('/settings', ['name' => ''])->assertSessionHasErrors('name');

    expect($person->fresh()->name)->toBe('Old Name');
});

it('links settings from the account menu', function () {
    $person = Person::create(['name' => 'Someone', 'email' => 'settings@example.test', 'password' => 'secret']);

    $this->actingAs($person)->get('/')->assertSee('href="'.url('/settings').'"', false);
});

it('gives a person from another origin no settings page', function () {
    $remote = Person::create(['name' => 'Remote', 'origin' => 'example.social']);

    $this->actingAs($remote)->get('/settings')->assertNotFound();
    $this->actingAs($remote)->post('/settings', ['name' => 'Renamed'])->assertNotFound();

    expect($remote->fresh()->name)->toBe('Remote');
});
