@if ($calls->isNotEmpty())
    <details class="collapse collapse-arrow card-border bg-base-100">
        <summary class="collapse-title font-semibold">{{ __('kopling-mcp::messages.team_calls.heading') }}</summary>
        <div class="collapse-content flex flex-col gap-2">
            <p class="text-sm opacity-60">{{ __('kopling-mcp::messages.team_calls.help') }}</p>
            <ul class="list">
                @foreach ($calls as $call)
                    <li class="list-row items-center">
                        <div class="list-col-grow min-w-0">
                            <div class="truncate font-mono text-sm">{{ \Illuminate\Support\Str::after($call->tool, '__') }}</div>
                            <div class="text-xs opacity-60">
                                {{ $call->created_at->translatedFormat('j M Y H:i') }}
                                &middot; {{ $call->person?->name ?? __('kopling-mcp::messages.admin.removed') }}
                                @if ($call->client)
                                    &middot; {{ $call->client->name }}
                                @endif
                            </div>
                        </div>
                        <span class="badge badge-sm {{ ['ok' => 'badge-success', 'denied' => 'badge-warning', 'error' => 'badge-error'][$call->outcome] ?? 'badge-ghost' }}">{{ __('kopling-mcp::messages.outcome.'.$call->outcome) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </details>
@endif
