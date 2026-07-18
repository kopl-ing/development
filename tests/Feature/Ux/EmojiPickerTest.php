<?php

declare(strict_types=1);

it('renders a trigger showing the current value and a hidden input carrying it', function () {
    $data = [
        'name' => 'upvote_emoji',
        'label' => 'Upvote emoji',
        'value' => '👍',
    ];

    $html = (string) $this->blade('<x-k::form.emoji-picker :data="$data" />', ['data' => $data]);

    expect($html)->toContain('name="upvote_emoji"')
        ->and($html)->toContain('value="👍"')
        ->and($html)->toContain('👍')
        ->and($html)->toContain('data-emoji-trigger')
        ->and($html)->toContain('data-emoji-input');

    // The clear button only shows once a value is actually set.
    preg_match('/data-emoji-clear[^>]*hidden/', $html, $hiddenClear);
    expect($hiddenClear)->toBeEmpty();
});

it('shows a placeholder and hides the clear button when no value is set', function () {
    $data = [
        'name' => 'downvote_emoji',
        'label' => 'Downvote emoji',
    ];

    $html = (string) $this->blade('<x-k::form.emoji-picker :data="$data" />', ['data' => $data]);

    expect($html)->toContain('＋');

    preg_match('/data-emoji-clear[^>]*hidden/', $html, $hiddenClear);
    expect($hiddenClear)->not->toBeEmpty();
});

it('renders a description when given', function () {
    $data = [
        'name' => 'upvote_emoji',
        'label' => 'Upvote emoji',
        'description' => 'Shown on the vote button.',
    ];

    $html = (string) $this->blade('<x-k::form.emoji-picker :data="$data" />', ['data' => $data]);

    expect($html)->toContain('Shown on the vote button.');
});
