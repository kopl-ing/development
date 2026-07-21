{{--
    Each entry gets its own box, `divide-y` drawing a line between whichever ones actually
    rendered -- none, one, or several stack the same way `Top`/`Body`/`Footer` themselves do
    one level up in `card.blade.php`, so a second Body registration (Discussions' own teaser,
    say) reads as its own section rather than crammed into Content's box via a manual margin.
    A single entry (the common case: just `Content`) looks identical to before -- one box, no
    divider, nothing to draw a line against. `flush` entries (a card image meant to bleed
    edge-to-edge) skip the padding this box otherwise applies -- see `UxEntry::$flush`.
--}}
@if ($entries->isNotEmpty())
    <div class="divide-y divide-base-content/10">
        @foreach ($entries as $entry)
            <div @class(['px-4 py-5 sm:p-6' => ! $entry->flush])>
                <x-dynamic-component :component="$entry->component" :data="$entry->data" :context="$entry->context" />
            </div>
        @endforeach
    </div>
@endif
