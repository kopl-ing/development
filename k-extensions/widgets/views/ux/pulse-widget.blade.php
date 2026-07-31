{{-- `every 60s` matches `PulseWidget::stats()`'s own cache TTL. --}}
<div class="card bg-base-100 border border-base-300 rounded-box mb-4"
     hx-get="{{ route('kopling-core::community/pulse.refresh') }}" hx-trigger="every 60s" hx-swap="outerHTML">
    <div class="card-body p-4 gap-3">
        <h3 class="text-xs font-bold uppercase tracking-wide opacity-60">{{ __('kopling-widgets::messages.pulse') }}</h3>
        <dl class="grid grid-cols-2 gap-3">
            @foreach ($stats as $key => $value)
                <div>
                    <dd class="text-xl font-bold tabular-nums text-primary leading-tight">{{ number_format($value) }}</dd>
                    <dt class="text-xs opacity-60">{{ __('kopling-widgets::messages.'.$key) }}</dt>
                </div>
            @endforeach
        </dl>
    </div>
</div>
