{{-- `flex-col` alone gives every entry (Title, Accreditation) its own full-width row -- no
     `flex-wrap`/`basis-full` hack needed. `Control` is fixed rather than one of `$entries` (see
     `Top`'s own docblock), absolutely positioned in this `relative` container's top-right
     corner; `pr-10` on the row reserves room so a long title's text never runs under it. --}}
@if ($entries->isNotEmpty())
    <div class="relative flex flex-col gap-2 px-4 py-3 pr-10 sm:px-6 sm:py-5 sm:pr-12">
        @foreach ($entries as $entry)
            <x-dynamic-component :component="$entry->component" :data="$entry->data" :context="$entry->context" />
        @endforeach

        <x-k::card.control :context="$context" :slot="$controlSlot" class="absolute top-2 right-2 sm:top-3 sm:right-3" />
    </div>
@endif
