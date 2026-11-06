@extends('kopling-oauth::layouts.oauth')
@section('title', __('kopling-oauth::messages.authorize.title', ['client' => $client->name]))
@php
    $person = \Kopling\OAuth\Person::find($user->getAuthIdentifier());
@endphp

@section('content')
    <section class="card card-border bg-base-100">
        <div class="card-body gap-4">
            <h1 class="card-title text-xl">{{ __('kopling-oauth::messages.authorize.title', ['client' => $client->name]) }}</h1>
            <p class="opacity-80">{{ __('kopling-oauth::messages.authorize.intro', ['client' => $client->name, 'name' => $user->name]) }}</p>

            @if ($scopes !== [])
                <ul class="list">
                    @foreach ($scopes as $scope)
                        @php $allowed = \Kopling\OAuth\Scopes::grantableBy($person, $scope->id); @endphp
                        <li class="list-row items-center {{ $allowed ? '' : 'opacity-60' }}">
                            <x-k::icon :name="$allowed ? 'kopling-oauth::allowed' : 'kopling-oauth::not-allowed'" />
                            <span class="list-col-grow">{{ $scope->description }}</span>
                            @unless ($allowed)
                                <span class="badge badge-sm badge-ghost">{{ __('kopling-oauth::messages.authorize.not_allowed') }}</span>
                            @endunless
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm opacity-60">{{ __('kopling-oauth::messages.authorize.no_scopes') }}</p>
            @endif

            @php $returnTo = parse_url((string) ($request->input('redirect_uri') ?? ($client->redirect_uris[0] ?? '')), PHP_URL_HOST); @endphp
            @if ($returnTo)
                <p class="text-sm">{!! __('kopling-oauth::messages.authorize.return_to', ['host' => '<span class="font-mono font-semibold">'.e($returnTo).'</span>']) !!}</p>
            @endif
            <p class="text-sm opacity-60">{{ __('kopling-oauth::messages.authorize.revoke_hint') }}</p>

            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('kopling-oauth::oauth/approve') }}">
                    @csrf
                    <input type="hidden" name="state" value="{{ $request->state }}">
                    <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit" class="btn btn-primary">{{ __('kopling-oauth::messages.authorize.approve') }}</button>
                </form>
                <form method="POST" action="{{ route('kopling-oauth::oauth/deny') }}">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="state" value="{{ $request->state }}">
                    <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit" class="btn btn-ghost">{{ __('kopling-oauth::messages.authorize.deny') }}</button>
                </form>
            </div>
        </div>
    </section>
@endsection
