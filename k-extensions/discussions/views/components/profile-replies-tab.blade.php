@props(['data' => [], 'context' => null, 'checked' => false])
@php use Kopling\Core\Ux\Context; use Kopling\Discussions\Reply; @endphp
@php
    $person = $context?->getSubject();
    // A distinct pageName from the Moments tab's default 'page' -- see `Context::getSubjectPaginator()`
    // docblock -- otherwise paging one tab would silently move the other too.
    $repliesContext = new Context(subject: Reply::forPerson($person), pageName: 'replies_page');
    $replies = $repliesContext->getSubjectPaginator();
@endphp
<label class="tab bg-base-200 hover:bg-base-300">
    <input type="radio" name="posts" @checked($checked) />
    @choice('kopling-discussions::messages.replies', $replies->total())
</label>
<div class="tab-content bg-base-200 border-base-300 p-6">
    <div id="profile-replies-feed" class="flex flex-col gap-4 sm:gap-8">
        @foreach ($replies as $reply)
            @if ($url = (new Context(subject: $reply->moment))->getSubjectUrl())
                <a href="{{ $url }}" class="text-sm opacity-70 hover:opacity-100 -mb-2">
                    &rarr; {{ $reply->moment->title }}
                </a>
            @endif
            @include('kopling-discussions::partials.reply', ['reply' => $reply])
        @endforeach

        <x-k::page.pagination :context="$repliesContext" target="#profile-replies-feed" />
    </div>
</div>
