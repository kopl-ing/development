{{--
    Each entry gets its own box, `divide-y` drawing a line between whichever ones rendered -- a
    second Body registration (Discussions' teaser) reads as its own section instead of being
    crammed into Content's box. `flush` entries skip the padding -- see `UxEntry::$flush`. An
    entry that renders nothing (e.g. poll's widget on a moment with no poll) is skipped entirely
    rather than leaving an empty padded box -- output is captured and checked, since a slot entry
    can be class-backed or anonymous and either way only ever produces plain HTML text.
--}}
@php
    $rendered = $entries->map(fn ($entry) => trim((string) view('kopling-core::ux.dynamic', [
        'component' => $entry->component,
        'data' => $entry->data,
        'context' => $entry->context,
    ])->render()));
@endphp
@if ($rendered->contains(fn (string $html) => $html !== ''))
    <div class="divide-y divide-base-content/10">
        @foreach ($entries as $index => $entry)
            @continue($rendered[$index] === '')
            <div @class(['px-4 py-3 sm:p-6' => ! $entry->flush])>
                {!! $rendered[$index] !!}
            </div>
        @endforeach
    </div>
@endif
