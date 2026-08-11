{{-- No wrapping element of its own -- `$attributes` (e.g. `Top`'s absolute-positioning classes)
     forwards straight onto `Dropdown`'s trigger button, the only element `Dropdown` itself
     merges extra attributes onto (see its own `$attributes->merge()`).

     Rendered to a string and empty-checked first, same as `Card\Body`'s own pattern -- an entry
     whose own view conditions its output on state other than `->when()`'s Gate (e.g.
     `ReportControlEntry` hiding itself from a moment's own author, or a dual-state entry like
     Pin's own toggle) can render nothing for a given viewer/moment, and a bare `<li></li>` left
     behind for it would be a stray empty row in the menu. --}}
@php
    $rendered = $entries->map(fn ($entry) => trim((string) view('kopling-core::ux.dynamic', [
        'component' => $entry->component,
        'data' => $entry->data,
        'context' => $entry->context,
    ])->render()));
@endphp
@if ($rendered->contains(fn (string $html) => $html !== ''))
    <x-k::dropdown :label="__('kopling-core::community.post_actions')" {{ $attributes }}>
        <x-slot:trigger>
            <x-k::icon name="kopling-core::post-actions" class="w-4 h-4" />
        </x-slot:trigger>

        @foreach ($entries as $index => $entry)
            @continue($rendered[$index] === '')
            <li>{!! $rendered[$index] !!}</li>
        @endforeach
    </x-k::dropdown>
@endif
