@extends('kopling-admin::layouts.admin')
@section('title', __('kopling-mcp::messages.admin.nav'))

@section('content')
    <div class="max-w-4xl flex flex-col gap-6">
        <div>
            <h1 class="text-2xl font-bold">{{ __('kopling-mcp::messages.admin.nav') }}</h1>
            <p class="text-sm opacity-60">{{ __('kopling-mcp::messages.admin.endpoint', ['url' => route('kopling-mcp::endpoint')]) }}</p>
        </div>

        <section class="card card-border bg-base-100">
            <div class="card-body gap-3">
                <h2 class="card-title">{{ __('kopling-mcp::messages.admin.tools') }}</h2>
                @forelse ($toolsets as $toolset)
                    <div class="flex flex-col gap-1">
                        <h3 class="font-semibold">{{ $toolset->label ?: $toolset->id }}</h3>
                        @foreach ($toolset->scopes as $scope => $declared)
                            <p class="text-sm"><span class="badge badge-sm badge-ghost font-mono">{{ $scope }}</span> {{ $declared['description'] }}</p>
                            <ul class="text-sm font-mono opacity-80 ps-4">
                                @foreach ($declared['tools'] as $class)
                                    <li>{{ $tools[$class]['name'] ?? $class }}</li>
                                @endforeach
                            </ul>
                        @endforeach
                    </div>
                @empty
                    <p class="opacity-60">{{ __('kopling-mcp::messages.admin.no_tools') }}</p>
                @endforelse
            </div>
        </section>

        <section class="card card-border bg-base-100">
            <div class="card-body gap-3">
                <h2 class="card-title">{{ __('kopling-mcp::messages.admin.calls') }}</h2>
                @php $calls = $context->getSubjects(); @endphp
                @if ($calls->isEmpty())
                    <p class="opacity-60">{{ __('kopling-mcp::messages.admin.no_calls') }}</p>
                @else
                    <ul class="list">
                        @foreach ($calls as $call)
                            <li class="list-row items-center">
                                <div class="list-col-grow min-w-0">
                                    <div class="truncate"><span class="font-mono text-sm">{{ $call->tool }}</span></div>
                                    <div class="text-xs opacity-60">
                                        {{ $call->created_at->translatedFormat('j M Y H:i:s') }}
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
                    <x-k::page.pagination :context="$context" />
                @endif
            </div>
        </section>
    </div>
@endsection
