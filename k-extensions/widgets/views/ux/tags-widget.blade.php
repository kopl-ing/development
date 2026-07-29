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
                                    {!! $tag['icon'] !!}
                                @endif
                                {{ $tag['name'] }}
                            </span>
                            <span class="shrink-0 text-xs opacity-60">{{ \Illuminate\Support\Carbon::parse($tag['last_activity'])->diffForHumans() }}</span>
                        </a>
                        <div class="flex items-center gap-1.5 pl-1">
                            <x-k::person.avatar-group
                                :avatars="$tag['avatars']"
                                spacing="-space-x-2"
                                :overflow="$tag['more_contributors']"
                            />
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
