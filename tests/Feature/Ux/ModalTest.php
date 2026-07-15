<?php

declare(strict_types=1);

it('renders the trigger button and dialog markup', function () {
    $html = (string) $this->blade(
        '<x-k::modal label="Manage groups"><x-slot:trigger>Open</x-slot:trigger><p>Body content</p></x-k::modal>'
    );

    expect($html)->toContain('data-modal-show')
        ->and($html)->toContain('<dialog')
        ->and($html)->toContain('modal-backdrop')
        ->and($html)->toContain('Open')
        ->and($html)->toContain('Body content');
});

it('gives two modals sharing the same label distinct ids', function () {
    $markup = '<x-k::modal label="Manage groups"><x-slot:trigger>Open</x-slot:trigger><p>Body</p></x-k::modal>';

    $first = (string) $this->blade($markup);
    $second = (string) $this->blade($markup);

    preg_match('/data-modal-show="([^"]+)"/', $first, $firstMatch);
    preg_match('/data-modal-show="([^"]+)"/', $second, $secondMatch);

    expect($firstMatch[1])->toStartWith('modal-manage-groups-')
        ->and($secondMatch[1])->toStartWith('modal-manage-groups-')
        ->and($firstMatch[1])->not->toBe($secondMatch[1]);
});
