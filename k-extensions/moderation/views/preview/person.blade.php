@php
    use Kopling\Core\Ux\Context;
    $url = (new Context(subject: $flaggable))->getSubjectUrl();
@endphp
<div class="flex items-center gap-3">
    <x-k::person.avatar :context="new Context(subject: $flaggable)" size="w-8" />
    <div class="flex-1 min-w-0">
        <p class="font-semibold">{{ $flaggable->name }}</p>
        <p class="text-sm opacity-70">{{ $flaggable->email }}</p>
        @if ($url)
            <a href="{{ $url }}" class="link text-sm">{{ __('kopling-moderation::moderation.view') }}</a>
        @endif
    </div>
</div>
