<?php

declare(strict_types=1);

use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;

/**
 * `old()` reads via `request()->session()`, which is only ever set by `StartSession` middleware
 * during a real HTTP request -- `$this->blade()` renders a bare view with no middleware
 * pipeline, so the current `request()` has no session attached at all unless this binds one
 * first (`hasSession()` would otherwise be false and `old()` would always return the default).
 */
function flashOldForm(string $value): void
{
    app('request')->setLaravelSession(app('session')->driver());

    session(['_old_input' => ['_form' => $value]]);
}

function messageBagErrors(): ViewErrorBag
{
    return (new ViewErrorBag())->put('default', new MessageBag(['name' => 'Required']));
}

it('renders the trigger button and dialog markup', function () {
    $html = (string) $this->blade(
        '<x-k::modal label="Manage groups"><x-slot:trigger>Open</x-slot:trigger><p>Body content</p></x-k::modal>'
    );

    expect($html)->toContain('data-modal-show')
        ->and($html)->toContain('<dialog')
        ->and($html)->toContain('Open')
        ->and($html)->toContain('Body content');
});

it('only closes through its own close button or Escape, never a tap beside the box', function () {
    $html = (string) $this->blade(
        '<x-k::modal label="Manage groups"><x-slot:trigger>Open</x-slot:trigger><p>Body</p></x-k::modal>'
    );

    expect($html)->not->toContain('modal-backdrop')
        ->and($html)->toContain('<form method="dialog">')
        ->and($html)->toContain('aria-label="'.__('kopling-core::ux.close').'"');
});

it('renders a cancel button that closes the dialog without submitting or validating the form', function () {
    $html = (string) $this->blade('<x-k::modal.cancel />');

    expect($html)->toContain('formmethod="dialog"')
        ->and($html)->toContain('formnovalidate')
        ->and($html)->toContain(__('kopling-core::ux.cancel'));
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

it('self-reopens when old(\'_form\') matches its own id and there are validation errors', function () {
    flashOldForm('modal-manage-groups');
    view()->share('errors', messageBagErrors());

    $html = (string) $this->blade(
        '<x-k::modal label="Manage groups" id="modal-manage-groups"><x-slot:trigger>Open</x-slot:trigger><p>Body</p></x-k::modal>'
    );

    expect($html)->toContain('getElementById("modal-manage-groups")?.showModal()');
});

it('does not reopen when there are no validation errors, even if _form matches', function () {
    flashOldForm('modal-manage-groups');

    $html = (string) $this->blade(
        '<x-k::modal label="Manage groups" id="modal-manage-groups"><x-slot:trigger>Open</x-slot:trigger><p>Body</p></x-k::modal>'
    );

    expect($html)->not->toContain('showModal()');
});

it('does not reopen a modal whose id does not match the flashed _form value', function () {
    flashOldForm('some-other-modal');
    view()->share('errors', messageBagErrors());

    $html = (string) $this->blade(
        '<x-k::modal label="Manage groups" id="modal-manage-groups"><x-slot:trigger>Open</x-slot:trigger><p>Body</p></x-k::modal>'
    );

    expect($html)->not->toContain('showModal()');
});

it('lets the trigger slot replace the default trigger classes', function () {
    $html = (string) $this->blade('<x-k::modal label="Edit"><x-slot:trigger class="btn">Edit</x-slot:trigger><p>Body</p></x-k::modal>');

    $trigger = Str::before($html, '</button>');

    expect($trigger)->toContain('class="btn"')
        ->and($trigger)->not->toContain('btn-sm');
});
