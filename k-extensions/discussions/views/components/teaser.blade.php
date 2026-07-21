@php
    use Illuminate\Support\Str;
    use Kopling\Discussions\Reply;
@endphp
@props(['data' => [], 'context' => null])
{{--
    The activity teaser, in the card body after core's `content`: faces of recent repliers (the
    fastest, pre-linguistic "humans here" signal) plus the demo's calm one-liner about the
    conversation ("N people used X words to talk about this"), linking to the discussion page.
    Reads `$context->subject` like every card leaf.
--}}
@php
    $moment = $context?->getSubject();
    $stats = $moment ? Reply::statsFor($moment) : null;
    $contributors = $moment && $stats && $stats['count'] > 0 ? Reply::recentContributors($moment) : collect();
@endphp
@if ($moment && $stats)
    <div class="flex items-center gap-2 text-sm opacity-70 py-2 px-6 italic">
        {{-- Never shown for a moment with no replies -- an empty avatar row would look barren,
             the opposite of the "warm, alive" signal this exists to give. --}}
        @if ($contributors->isNotEmpty())
            <span class="avatar-group -space-x-3">
                @foreach ($contributors as $person)
                    <div class="avatar avatar-placeholder">
                        <div class="w-6 bg-neutral text-neutral-content">
                            <span class="text-xs">{{ Str::of($person->name)->explode(' ')->take(2)->map(fn (string $word) => Str::upper(Str::substr($word, 0, 1)))->implode('') }}</span>
                        </div>
                    </div>
                @endforeach
            </span>
        @endif
        <span>
            @if ($stats['count'] === 0)
                {{ __('kopling-discussions::messages.teaser_empty') }}
            @else
                {{ trans_choice('kopling-discussions::messages.teaser', $stats['people'], [
                    'people' => $stats['people'],
                    'words' => number_format($stats['words']),
                ]) }}
            @endif
        </span>
    </div>
@endif
