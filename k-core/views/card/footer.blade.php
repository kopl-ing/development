{{--
    `items-center`: daisyUI's own `.card-actions` defaults to `align-items: flex-start`, which
    top-aligns entries instead of centering them -- fine when every entry is the same height,
    wrong the moment two entries aren't (e.g. reactions' own `vote` next to its `rail`, deliberately
    different sizes). A plain Tailwind utility here overrides that default for every footer entry,
    not just reactions' own -- the more sensible default for a horizontal action row regardless of
    what ends up in it.

    `flex-nowrap overflow-x-auto` replaces `.card-actions`' own default wrapping: one row, always
    -- an entry that can grow wide (reactions' own emoji rail + worded chips, potentially many)
    scrolls horizontally within the row's own bounds instead of wrapping the whole footer onto a
    second line, which is what let a trailing entry (discussions' own `engage`/`quote-op`, both
    `ml-auto`) reliably pin to the row's end rather than drift depending on how much came before
    it wrapped or not.
--}}
@if ($entries->isNotEmpty())
    <div class="card-actions flex-nowrap items-center overflow-x-auto px-4 py-4 sm:px-6">
        @foreach ($entries as $entry)
            <x-dynamic-component :component="$entry->component" :data="$entry->data" :context="$entry->context" />
        @endforeach
    </div>
@endif
