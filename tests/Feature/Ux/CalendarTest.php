<?php

declare(strict_types=1);

function calendarHtml(array $data): string
{
    return (string) test()->blade('<x-k::form.calendar :data="$data" />', ['data' => $data]);
}

it('renders a range as two plain date fields inside the mount point', function () {
    $html = calendarHtml([
        'label' => 'Away',
        'startName' => 'from',
        'endName' => 'until',
        'start' => '2026-12-20',
        'end' => '2026-12-27',
        'min' => '2026-11-01',
        'required' => true,
    ]);

    expect($html)->toContain('<k-calendar')
        ->and($html)->toContain('data-mode="range"')
        ->and($html)->toContain('data-min="2026-11-01"')
        ->and($html)->toContain('data-required')
        ->and($html)->toMatch('/<input type="date" name="from" value="2026-12-20"[^>]*required/')
        ->and($html)->toMatch('/<input type="date" name="until" value="2026-12-27"[^>]*required/')
        ->and($html)->not->toContain('data-calendar-clear');
});

it('defaults the range field names and offers clearing when optional', function () {
    $html = calendarHtml(['label' => 'Away']);

    expect($html)->toContain('name="starts_on"')
        ->and($html)->toContain('name="ends_on"')
        ->and($html)->toContain('data-calendar-clear')
        ->and($html)->toContain('First day')
        ->and($html)->not->toContain('data-marked')
        ->and($html)->not->toContain('data-required');
});

it('encodes marked days for the calendar to read', function () {
    $html = calendarHtml(['label' => 'Away', 'marked' => ['2026-12-22' => 'Match "A"']]);

    preg_match('/data-marked="([^"]+)"/', $html, $match);

    expect(json_decode(html_entity_decode($match[1] ?? ''), true))->toBe(['2026-12-22' => 'Match "A"']);
});

it('renders loose days as a sorted list plus one empty row', function () {
    $html = calendarHtml(['label' => 'Days', 'mode' => 'multiple', 'name' => 'days', 'value' => ['2026-12-24', '', '2026-12-20']]);

    expect($html)->toContain('data-mode="multiple"')
        ->and($html)->toContain('Tap the days you want.')
        ->and(substr_count($html, 'name="days[]"'))->toBe(3)
        ->and($html)->toMatch('/value="2026-12-20".*value="2026-12-24".*name="days\[\]" value=""/s')
        ->and($html)->not->toContain('type="time"');
});

it('pairs each loose day with a time when asked', function () {
    $html = calendarHtml([
        'label' => 'Days',
        'mode' => 'multiple',
        'time' => true,
        'value' => [['date' => '2026-12-24', 'time' => '18:30']],
    ]);

    expect($html)->toContain('data-time')
        ->and($html)->toContain('name="dates[0][date]" value="2026-12-24"')
        ->and($html)->toContain('name="dates[0][time]" value="18:30"')
        ->and($html)->toContain('name="dates[1][date]" value=""');
});
