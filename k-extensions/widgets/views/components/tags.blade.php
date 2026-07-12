@php
    use Illuminate\Support\Facades\Cache;

    // No-op without the tags extension; self-hides when there are no tags yet. Cache PLAIN
    // ARRAYS, never the Eloquent collection — a serialized model unserializes to an incomplete
    // object across requests (the class isn't loaded yet at unserialize time) and blows up.
    $tags = class_exists(\Kopling\Tags\Tag::class)
        ? Cache::remember('kopling-widgets.tags', 60, fn () => \Kopling\Tags\Tag::query()
            ->withCount('moments')
            ->orderByDesc('moments_count')
            ->limit(8)
            ->get()
            ->map(fn ($tag) => [
                'name' => $tag->name,
                'slug' => $tag->slug,
                'color' => $tag->color,
                'count' => $tag->moments_count,
            ])
            ->all())
        : [];
@endphp

@if (! empty($tags))
    <div class="card bg-base-100 border border-base-300 rounded-box mb-4">
        <div class="card-body p-4 gap-2">
            <h3 class="text-xs font-bold uppercase tracking-wide opacity-60">{{ __('kopling-widgets::messages.popular_tags') }}</h3>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($tags as $tag)
                    <a href="{{ route('tags.show', $tag['slug']) }}" class="badge badge-sm no-underline gap-1"
                       @if ($tag['color']) style="background-color:{{ $tag['color'] }};border-color:{{ $tag['color'] }};color:#fff" @endif>
                        {{ $tag['name'] }}<span class="opacity-70">{{ $tag['count'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endif
