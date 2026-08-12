@php
    $activeView = request()->routeIs('kopling-mail-client::mail/index') ? 'all' : request()->route('view');
@endphp

{{-- No target: boosted-without-target swaps `document.body` (see CLAUDE.md gotchas), same as
     Docs's own sidebar -- these are full navigations, unlike a message row's own narrower swap. --}}
<ul class="menu w-full" hx-boost:inherited="true">
    <li class="menu-title">{{ __('kopling-mail-client::messages.mailboxes') }}</li>
    <li>
        <a href="{{ route('kopling-mail-client::mail/index') }}" class="{{ $activeView === 'all' ? 'menu-active' : '' }}">
            {{ __('kopling-mail-client::messages.all_inboxes') }}
        </a>
    </li>
    <li>
        <a href="{{ route('kopling-mail-client::mail/view', 'flagged') }}" class="{{ $activeView === 'flagged' ? 'menu-active' : '' }}">
            {{ __('kopling-mail-client::messages.flagged') }}
        </a>
    </li>
    <li>
        <a href="{{ route('kopling-mail-client::mail/view', 'sent') }}" class="{{ $activeView === 'sent' ? 'menu-active' : '' }}">
            {{ __('kopling-mail-client::messages.sent') }}
        </a>
    </li>
</ul>

@if ($accounts->isEmpty())
    <div class="p-4">
        <p class="text-sm opacity-60 mb-2">{{ __('kopling-mail-client::messages.no_accounts') }}</p>
        <a href="{{ route('kopling-mail-client::mail/accounts') }}" class="btn btn-sm btn-primary btn-block">
            {{ __('kopling-mail-client::messages.connect_mailbox') }}
        </a>
    </div>
@else
    <ul class="menu w-full" hx-boost:inherited="true">
        <li class="menu-title">{{ __('kopling-mail-client::messages.accounts') }}</li>
        @foreach ($accounts as $account)
            <li>
                <details {{ request()->route('account')?->is($account) ? 'open' : '' }}>
                    <summary>{{ $account->label ?: $account->email_address }}</summary>
                    <ul>
                        @forelse ($account->folders as $folder)
                            <li>
                                <a href="{{ route('kopling-mail-client::mail/folder', [$account, $folder]) }}"
                                   class="{{ request()->route('folder')?->is($folder) ? 'menu-active' : '' }}">
                                    {{ $folder->name }}
                                </a>
                            </li>
                        @empty
                            <li><span class="opacity-60 text-sm">{{ __('kopling-mail-client::messages.no_folders_synced') }}</span></li>
                        @endforelse
                    </ul>
                </details>
            </li>
        @endforeach
        <li>
            <a href="{{ route('kopling-mail-client::mail/accounts') }}">{{ __('kopling-mail-client::messages.manage_accounts') }}</a>
        </li>
    </ul>
@endif
