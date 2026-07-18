{{--
    `items-center`: daisyUI's own `.card-actions` defaults to `align-items: flex-start`, which
    top-aligns entries instead of centering them -- fine when every entry is the same height,
    wrong the moment two entries aren't (e.g. reactions' own `vote` next to its `rail`, deliberately
    different sizes). A plain Tailwind utility here overrides that default for every footer entry,
    not just reactions' own -- the more sensible default for a horizontal action row regardless of
    what ends up in it.
--}}
<div class="card-actions mt-2 items-center">
    @foreach ($entries as $entry)
        <x-dynamic-component :component="$entry->component" :data="$entry->data" :context="$entry->context" />
    @endforeach
</div>
