@php
    use Kopling\Core\Ux\Context;
    $url = (new Context(subject: $flaggable))->getSubjectUrl();
@endphp
<div class="flex items-start gap-3 {{ $flaggable->trashed() ? 'opacity-60' : '' }}">
    <x-k::person.avatar :context="new Context(subject: $flaggable)" size="w-8" />
    <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 text-sm opacity-70">
            <span class="font-semibold">{{ $flaggable->person?->name ?? __('kopling-moderation::moderation.unknown_author') }}</span>
            <span>{{ $flaggable->created_at?->diffForHumans() }}</span>
            @if ($flaggable->trashed())
                <span class="badge badge-error badge-outline">{{ __('kopling-moderation::moderation.hidden') }}</span>
            @endif
        </div>
        <p class="font-medium">{{ $flaggable->title }}</p>
        <p class="opacity-70 line-clamp-3">{{ Illuminate\Support\Str::limit(strip_tags((string) $flaggable->body_html), 200) }}</p>
        @if ($url)
            <a href="{{ $url }}" class="link text-sm">{{ __('kopling-moderation::moderation.view') }}</a>
        @endif
    </div>
</div>
