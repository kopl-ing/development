@php
    use Kopling\Core\Ux\Context;
    // The parent Moment might itself be hidden/missing (SoftDeletes' global scope excludes it
    // from the belongsTo lookup too) -- Context::getSubjectUrl() throws on a null subject, so
    // this guards rather than passing $flaggable->moment straight through.
    $url = $flaggable->moment ? (new Context(subject: $flaggable->moment))->getSubjectUrl() : null;
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
        <p class="opacity-70 line-clamp-3">{{ Illuminate\Support\Str::limit(strip_tags((string) $flaggable->body_html), 200) }}</p>
        @if ($url)
            <a href="{{ $url }}" class="link text-sm">{{ __('kopling-moderation::moderation.view_in_context') }}</a>
        @endif
    </div>
</div>
