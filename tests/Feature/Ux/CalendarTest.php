<?php

declare(strict_types=1);

it('renders two plain date fields inside the calendar mount point', function () {
    $html = (string) $this->blade('<x-k::form.date-range :data="$data" />', ['data' => [
        'label' => 'Away',
        'startName' => 'from',
        'endName' => 'until',
        'start' => '2026-12-20',
        'end' => '2026-12-27',
        'min' => '2026-11-01',
        'required' => true,
    ]]);

    expect($html)->toContain('<k-date-range')
        ->and($html)->toContain('data-min="2026-11-01"')
        ->and($html)->toMatch('/<input type="date" name="from" value="2026-12-20"[^>]*required/')
        ->and($html)->toMatch('/<input type="date" name="until" value="2026-12-27"[^>]*required/')
        ->and($html)->not->toContain('data-date-range-clear');
});

it('defaults the field names and offers clearing when optional', function () {
    $html = (string) $this->blade('<x-k::form.date-range :data="$data" />', ['data' => ['label' => 'Away']]);

    expect($html)->toContain('name="starts_on"')
        ->and($html)->toContain('name="ends_on"')
        ->and($html)->toContain('data-date-range-clear')
        ->and($html)->toContain('First day')
        ->and($html)->not->toContain('data-marked')
        ->and($html)->not->toContain('data-min');
});

it('encodes marked days for the calendar to read', function () {
    $html = (string) $this->blade('<x-k::form.date-range :data="$data" />', ['data' => [
        'label' => 'Away',
        'marked' => ['2026-12-22' => 'Match "A"'],
    ]]);

    preg_match('/data-marked="([^"]+)"/', $html, $match);

    expect(json_decode(html_entity_decode($match[1] ?? ''), true))->toBe(['2026-12-22' => 'Match "A"']);
});
