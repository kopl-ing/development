{{--
    Each entry gets its own box, `divide-y` drawing a line between whichever ones rendered -- a
    second Body registration (Discussions' teaser) reads as its own section instead of being
    crammed into Content's box. `flush` entries skip the padding -- see `UxEntry::$flush`.
--}}
@if ($entries->isNotEmpty())
    <div class="divide-y divide-base-content/10">
        @foreach ($entries as $entry)
            <div @class(['px-4 py-3 sm:p-6' => ! $entry->flush])>
                <x-dynamic-component :component="$entry->component" :data="$entry->data" :context="$entry->context" />
            </div>
        @endforeach
    </div>
@endif
