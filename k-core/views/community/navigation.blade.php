@if ($surface === 'dock')
    {{-- Overrides daisyUI's own shrink-to-fit `.dock` with a scrollable strip instead, so it
         degrades by scrolling rather than squeezing icons unreadably thin. --}}
    <div class="dock md:hidden overflow-x-auto justify-center-safe [&>*]:shrink-0 [&>*]:basis-auto" id="mobile-nav">
        @foreach ($entries as $entry)
            <x-dynamic-component :component="$entry->component" :data="$entry->data" :context="$entry->context" surface="dock" />
        @endforeach
    </div>
@else
    {{-- `w-full` overrides daisyUI's own `.menu` shrink-to-fit-content default. --}}
    <ul class="menu p-4 w-full">
        @foreach ($entries as $entry)
            <x-dynamic-component :component="$entry->component" :data="$entry->data" :context="$entry->context" />
        @endforeach
    </ul>
@endif
