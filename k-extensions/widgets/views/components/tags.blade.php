@php
    use Illuminate\Support\Facades\Cache;
    use Illuminate\Support\Str;
    use Kopling\Core\Ux\Form\IconSearch\IconRenderer;

    // "Heat", not lifetime popularity -- a tag with nothing from the last week doesn't appear at
    // all, and ranking is by how much has happened recently (moments tagged with it, plus
    // replies to any moment carrying it, when discussions is installed), never total post count
    // (a popularity monument that rewards dead tags with big numbers). Faces + a plain recency
    // stamp instead of a count: a timestamp proves life, a number only proves size.
    $window = now()->subDays(7);

    $tags = class_exists(\Kopling\Tags\Tag::class)
        ? Cache::remember('kopling-widgets.tags', 60, function () use ($window) {
            $hasReplies = class_exists(\Kopling\Discussions\Reply::class);

            return \Kopling\Tags\Tag::query()
                ->whereHas('moments')
                ->get()
                ->map(function ($tag) use ($window, $hasReplies) {
                    $moments = $tag->moments()
                        ->with('person:id,name')
                        ->get(['moments.id', 'moments.person_id', 'moments.created_at']);

                    // Every (timestamp, person) pair counting as "recent activity" under this
                    // tag -- a moment itself posted within the window, or (with discussions
                    // installed) a reply within the window to any moment carrying this tag,
                    // however old that moment is. A tag stays "hot" as long as people keep
                    // replying under it, not just while its moments are freshly posted.
                    $activity = collect();

                    foreach ($moments as $moment) {
                        if ($moment->created_at?->gte($window)) {
                            $activity->push(['at' => $moment->created_at, 'person' => $moment->person]);
                        }
                    }

                    if ($hasReplies && $moments->isNotEmpty()) {
                        \Kopling\Discussions\Reply::query()
                            ->whereIn('moment_id', $moments->pluck('id'))
                            ->where('created_at', '>=', $window)
                            ->with('person:id,name')
                            ->get()
                            ->each(fn ($reply) => $activity->push(['at' => $reply->created_at, 'person' => $reply->person]));
                    }

                    return ['tag' => $tag, 'activity' => $activity->sortByDesc('at')->values()];
                })
                ->filter(fn (array $entry) => $entry['activity']->isNotEmpty())
                ->sortByDesc(fn (array $entry) => $entry['activity']->count())
                ->take(5)
                ->map(function (array $entry) {
                    // Real people who actually posted/replied under this tag in the window --
                    // never filler. Capped at 3 here (not the 5 fetched above): three small
                    // avatars plus a "+N" badge reads clearly in a ~256px sidebar; five crammed
                    // onto one row didn't leave enough width for anything to actually look like
                    // faces rather than noise.
                    $contributors = $entry['activity']->pluck('person')->filter()->unique('id')->values();

                    return [
                        'name' => $entry['tag']->name,
                        'slug' => $entry['tag']->slug,
                        'color' => $entry['tag']->color,
                        'icon' => $entry['tag']->icon,
                        'contributors' => $contributors->take(3)->map(fn ($person) => ['id' => $person->id, 'name' => $person->name])->values()->all(),
                        'more_contributors' => max(0, $contributors->count() - 3),
                        // A plain ISO8601 string, not a Carbon instance -- the `array` cache
                        // driver Pest tests run under never actually serializes anything, so a
                        // Carbon object round-trips fine there and the tests stayed green, but
                        // the real `file` driver production uses does a genuine PHP serialize()/
                        // unserialize(), which doesn't reliably reconstruct Carbon (confirmed
                        // live: "incomplete object" on the very next request). A string has no
                        // such risk, same reasoning this file's own long-standing rule already
                        // states for Eloquent models -- cache plain, re-hydrate on read.
                        'last_activity' => $entry['activity']->first()['at']->toIso8601String(),
                    ];
                })
                ->values()
                ->all();
        })
        : [];
@endphp

@if (! empty($tags))
    <div class="card bg-base-100 border border-base-300 rounded-box mb-4">
        <div class="card-body p-4 gap-2">
            <h3 class="text-xs font-bold uppercase tracking-wide opacity-60">{{ __('kopling-widgets::messages.popular_tags') }}</h3>
            <div class="flex flex-col gap-3">
                {{--
                    Two rows per tag, not one -- a badge (variable-width name), an avatar row,
                    and a recency stamp all competing for space on a single line left the
                    avatars with nowhere to actually render as recognizable faces. Splitting
                    "what and when" (top) from "who" (bottom) gives each its own width.
                --}}
                @foreach ($tags as $tag)
                    <div class="flex flex-col gap-1">
                        <a href="{{ route('kopling-core::community/tags.show', $tag['slug']) }}"
                           class="flex items-center justify-between gap-2 no-underline hover:opacity-80">
                            <span class="badge badge-sm gap-1 shrink-0"
                                  @if ($tag['color']) style="background-color:{{ $tag['color'] }};border-color:{{ $tag['color'] }};color:#fff" @endif>
                                {{-- Inherits currentColor (white, set above) rather than being
                                     tinted to $tag['color'] -- it already sits on that exact
                                     color as the badge's own background. --}}
                                @if ($tag['icon'])
                                    {!! IconRenderer::svg($tag['icon'], '0.9em') !!}
                                @endif
                                {{ $tag['name'] }}
                            </span>
                            <span class="shrink-0 text-xs opacity-60">{{ \Illuminate\Support\Carbon::parse($tag['last_activity'])->diffForHumans() }}</span>
                        </a>
                        <div class="flex items-center gap-1.5 pl-1">
                            <span class="avatar-group -space-x-2">
                                @foreach ($tag['contributors'] as $person)
                                    <div class="avatar avatar-placeholder" title="{{ $person['name'] }}">
                                        <div class="w-6 bg-neutral text-neutral-content">
                                            <span class="text-[10px]">{{ Str::of($person['name'])->explode(' ')->take(2)->map(fn (string $word) => Str::upper(Str::substr($word, 0, 1)))->implode('') }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </span>
                            @if ($tag['more_contributors'] > 0)
                                <span class="text-xs opacity-50">+{{ $tag['more_contributors'] }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
