{{--
    Tailwind's @source only scans .blade.php files (see k-core/src/Ux/css/app.css), never plain
    .php classes -- DecoratePinnedCard builds its outline/bg classes dynamically from Pin::REASONS
    at runtime ("outline-{$pin->color()}"), so those literal strings need to appear somewhere
    scanned or they'd be purged from the compiled CSS despite being correct in the rendered HTML.
    Pin's reason set is small and fixed by design (no free swatch picker), so a safelist comment
    is simpler than restructuring the event listener to build markup in Blade instead. Keep this
    in sync with Pin::REASONS:
    outline-info bg-info/5 outline-accent bg-accent/5 outline-warning bg-warning/5 outline-success bg-success/5
--}}
@if ($pins->isNotEmpty())
    <div class="flex flex-col gap-4 mb-4">
        <h2 class="text-sm font-semibold opacity-60 uppercase tracking-wide">{{ __('kopling-pin::pin.pinned') }}</h2>
        @foreach ($pins as $index => $pin)
            <x-k::card.card :context="$contexts[$index]" />
        @endforeach
    </div>
@endif
