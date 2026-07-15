<?php

declare(strict_types=1);

it('renders one option per entry, selecting the one matching value', function () {
    $data = [
        'name' => 'reason',
        'label' => 'Reason',
        'options' => ['announcement' => 'Announcement', 'event' => 'Event'],
        'value' => 'event',
    ];

    $html = (string) $this->blade('<x-k::form.select :data="$data" />', ['data' => $data]);

    expect($html)->toContain('name="reason"')
        ->and($html)->toContain('value="announcement"')
        ->and($html)->toContain('value="event"')
        ->and($html)->toContain('Announcement')
        ->and($html)->toContain('Event');

    preg_match('/value="event"[^>]*selected/', $html, $selectedEvent);
    preg_match('/value="announcement"[^>]*selected/', $html, $selectedAnnouncement);

    expect($selectedEvent)->not->toBeEmpty()
        ->and($selectedAnnouncement)->toBeEmpty();
});

it('falls back to default when no value is given', function () {
    $data = [
        'name' => 'reason',
        'label' => 'Reason',
        'options' => ['announcement' => 'Announcement'],
        'default' => 'announcement',
    ];

    $html = (string) $this->blade('<x-k::form.select :data="$data" />', ['data' => $data]);

    preg_match('/value="announcement"[^>]*selected/', $html, $selected);

    expect($selected)->not->toBeEmpty();
});

it('renders a description when given', function () {
    $data = [
        'name' => 'reason',
        'label' => 'Reason',
        'description' => 'Why this is pinned.',
        'options' => ['announcement' => 'Announcement'],
    ];

    $html = (string) $this->blade('<x-k::form.select :data="$data" />', ['data' => $data]);

    expect($html)->toContain('Why this is pinned.');
});
