{{-- `Title` gets its own full-width row by structure (pulled out by `id`), not a
     `flex-wrap`/`basis-full` hack -- everything else stacks in its own wrapped row below it. --}}
@if ($entries->isNotEmpty())
    <div class="flex flex-col gap-2 px-4 py-3 sm:px-6 sm:py-5">
        @foreach ($entries as $entry)
            <x-dynamic-component :component="$entry->component" :data="$entry->data" :context="$entry->context" />
        @endforeach
    </div>
@endif
