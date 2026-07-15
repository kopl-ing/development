{{--
    Tailwind's @source only scans .blade.php files (see k-core/src/Ux/css/app.css), never plain
    .php classes -- DecoratePinnedCard builds its border/bg classes dynamically from Pin::REASONS
    at runtime ("border-{$pin->color()}"), so those literal strings need to appear somewhere
    scanned or they'd be purged from the compiled CSS despite being correct in the rendered HTML.
    Pin's reason set is small and fixed by design (no free swatch picker), so a safelist comment
    is simpler than restructuring the event listener to build markup in Blade instead. Keep this
    in sync with Pin::REASONS:
    border-info bg-info/5 border-accent bg-accent/5 border-warning bg-warning/5 border-success bg-success/5
--}}
@if ($pins->isNotEmpty())
    <div class="flex flex-col gap-4 mb-4">
        <h2 class="text-sm font-semibold opacity-60 uppercase tracking-wide">{{ __('kopling-pin::pin.pinned') }}</h2>
        @foreach ($pins as $index => $pin)
            <x-k::card.card :context="$contexts[$index]" />
        @endforeach
    </div>
@endif
