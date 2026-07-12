@php
    use Illuminate\Support\Facades\Cache;
    use Kopling\Core\Content\Moment;
    use Kopling\Core\People\Person;

    // A few cheap counts, cached briefly — the rail renders on every community page, so this
    // never wants to be per-request. Reply/Reaction counts only when those extensions exist.
    $stats = Cache::remember('kopling-widgets.pulse', 60, function () {
        $s = ['moments' => Moment::count(), 'people' => Person::count()];

        if (class_exists(\Kopling\Discussions\Reply::class)) {
            $s['replies'] = \Kopling\Discussions\Reply::count();
        }
        if (class_exists(\Kopling\Reactions\Reaction::class)) {
            $s['reactions'] = \Kopling\Reactions\Reaction::count();
        }

        return $s;
    });
@endphp

<div class="card bg-base-100 border border-base-300 rounded-box mb-4">
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
