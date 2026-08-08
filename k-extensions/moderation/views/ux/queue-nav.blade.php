{{-- No explicit hx-target: a boosted-without-target swap replaces the whole document.body (see
     CLAUDE.md gotchas, and kopling-docs::ux.sidebar's own identical choice) -- needed here
     because the active-status highlight below lives in this same sidebar, not just the queue
     content beside it, so the whole page has to re-render on every click, not only #main-content. --}}
<ul class="menu p-4 w-full" hx-boost:inherited="true">
    <li class="menu-title">{{ __('kopling-moderation::moderation.queue_title') }}</li>
    @foreach (['pending', 'actioned', 'dismissed', 'sanctioned'] as $tab)
        <li>
            <a href="{{ route('kopling-moderation::moderation/queue.index', ['status' => $tab]) }}"
               class="@if ($status === $tab) menu-active @endif">
                {{ __("kopling-moderation::moderation.status.$tab") }}
            </a>
        </li>
    @endforeach
</ul>
