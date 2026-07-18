@props(['data' => [], 'context' => null])
{{--
    "Latest / Top" feed sort toggle, filling Community's `content-top` slot (the same one
    Pin's own pinned section uses). Plain links, not htmx -- the feed itself is a full-page
    render (see IndexController), so a normal navigation is all `?sort=top` needs. Self-hides
    when no tag configures upvoting yet -- same soft-dependency-on-tags convention
    `Reaction::voteConfigFor` already established, so it's a no-op without the tags extension.
--}}
@php
    $hasVoting = class_exists(\Kopling\Tags\Tag::class)
        && \Kopling\Tags\Tag::query()->whereNotNull('upvote_emoji')->exists();
    $sort = request()->query('sort');
@endphp
@if ($hasVoting)
    <div class="join mb-4">
        <a href="{{ url()->current() }}" class="btn btn-sm join-item {{ $sort !== 'top' ? 'btn-active' : '' }}">
            {{ __('kopling-reactions::messages.sort_latest') }}
        </a>
        <a href="{{ url()->current() }}?sort=top" class="btn btn-sm join-item {{ $sort === 'top' ? 'btn-active' : '' }}">
            {{ __('kopling-reactions::messages.sort_top') }}
        </a>
    </div>
@endif
