@php $status = $account->syncStatus(); @endphp

{{--
    Self-stopping poll: while syncing, this fragment carries its own hx-trigger and re-fetches
    itself every 3s. Once the status changes, the swapped-in fragment (this same view, re-rendered)
    no longer has that attribute at all -- htmx re-processes swapped content on settle, so polling
    simply stops rather than needing an explicit "cancel" signal.
--}}
<span
    id="mail-account-status-{{ $account->id }}"
    @if ($status === 'syncing')
        hx-get="{{ route('kopling-mail-client::mail/accounts.status', $account) }}"
        hx-trigger="every 3s"
        hx-swap="outerHTML"
    @endif
>
    @switch($status)
        @case('syncing')
            @php
                $total = $account->totalMessageCount();
                $synced = $account->syncedMessageCount();
            @endphp
            <div class="flex items-center gap-2">
                <progress class="progress progress-primary w-24" value="{{ $synced }}" max="{{ max($total, 1) }}"></progress>
                <span class="text-xs opacity-60">{{ __('kopling-mail-client::messages.syncing_progress', ['synced' => $synced, 'total' => $total]) }}</span>
            </div>
            @break

        @case('failed')
            <span class="badge badge-error badge-sm" title="{{ $account->last_sync_error }}">
                {{ __('kopling-mail-client::messages.sync_failed') }}
            </span>
            @break

        @case('synced')
            <span class="badge badge-success badge-sm">{{ __('kopling-mail-client::messages.synced') }}</span>
            @break

        @default
            <span class="badge badge-ghost badge-sm">{{ __('kopling-mail-client::messages.pending') }}</span>
    @endswitch
</span>
