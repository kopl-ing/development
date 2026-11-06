@extends('kopling-admin::layouts.admin')
@section('title', __('kopling-oauth::messages.admin.nav'))
@php
    $describe = fn (?array $ids) => collect($ids ?? [])->map(fn (string $id) => \Kopling\OAuth\Scopes::find($id)?->description ?? $id)->join(', ');
@endphp

@section('content')
    <div class="max-w-4xl flex flex-col gap-6">
        <h1 class="text-2xl font-bold">{{ __('kopling-oauth::messages.admin.nav') }}</h1>

        @if (session('status'))
            <p class="text-success text-sm">{{ session('status') }}</p>
        @endif

        @unless ($keys)
            <div role="alert" class="alert alert-warning alert-soft flex flex-wrap items-center gap-3">
                <span class="flex-1">{{ __('kopling-oauth::messages.admin.keys_missing') }}</span>
                <form method="POST" action="{{ route('kopling-admin::admin/oauth.keys') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-warning">{{ __('kopling-oauth::messages.admin.create_keys') }}</button>
                </form>
            </div>
        @endunless

        <section class="card card-border bg-base-100">
            <div class="card-body gap-3">
                <h2 class="card-title">{{ __('kopling-oauth::messages.admin.clients') }}</h2>
                @if ($clients->isEmpty())
                    <p class="opacity-60">{{ __('kopling-oauth::messages.admin.no_clients') }}</p>
                @else
                    <ul class="list">
                        @foreach ($clients as $row)
                            @php $client = $row['client']; @endphp
                            <li class="list-row items-center {{ $client->revoked ? 'opacity-60' : '' }}">
                                <div class="list-col-grow min-w-0">
                                    <div class="font-semibold truncate">{{ $client->name }}</div>
                                    <div class="text-sm opacity-60 truncate">{{ implode(', ', $client->redirect_uris) }}</div>
                                    <div class="text-xs opacity-60">
                                        {{ __('kopling-oauth::messages.admin.registered', ['date' => $client->created_at?->translatedFormat('j F Y')]) }}
                                        &middot; {{ trans_choice('kopling-oauth::messages.admin.people', $row['people']) }}
                                    </div>
                                </div>
                                @if ($client->revoked)
                                    <span class="badge badge-sm badge-ghost">{{ __('kopling-oauth::messages.admin.revoked') }}</span>
                                @else
                                    <form method="POST" action="{{ route('kopling-admin::admin/oauth.clients.revoke', $client->id) }}"
                                          hx-boost="true" hx-confirm="{{ __('kopling-oauth::messages.admin.confirm_revoke_client', ['client' => $client->name]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-error btn-outline">{{ __('kopling-oauth::messages.revoke') }}</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        <section class="card card-border bg-base-100">
            <div class="card-body gap-3">
                <h2 class="card-title">{{ __('kopling-oauth::messages.admin.tokens') }}</h2>
                @if ($tokens->isEmpty())
                    <p class="opacity-60">{{ __('kopling-oauth::messages.admin.no_tokens') }}</p>
                @else
                    <ul class="list">
                        @foreach ($tokens as $token)
                            <li class="list-row items-center">
                                <div class="list-col-grow min-w-0">
                                    <div class="truncate">
                                        <span class="font-semibold">{{ $token->person?->name }}</span>
                                        &middot; {{ \Kopling\OAuth\Tokens::isPersonal($token) ? $token->name : $token->client?->name }}
                                    </div>
                                    <div class="text-sm opacity-60 truncate">{{ $describe($token->scopes) }}</div>
                                    <div class="text-xs opacity-60">
                                        {{ $token->created_at->translatedFormat('j M Y H:i') }}
                                        @if ($token->expires_at)
                                            &ndash; {{ $token->expires_at->translatedFormat('j M Y H:i') }}
                                        @endif
                                    </div>
                                </div>
                                <form method="POST" action="{{ route('kopling-admin::admin/oauth.tokens.revoke', $token->id) }}" hx-boost="true">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-ghost">{{ __('kopling-oauth::messages.revoke') }}</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    </div>
@endsection
