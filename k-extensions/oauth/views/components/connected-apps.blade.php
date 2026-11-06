@props(['context'])
@php
    use Kopling\OAuth\Scopes;
    use Kopling\OAuth\Tokens;

    $person = $context->getSubject();
    [$personal, $apps] = Tokens::active($person)->partition(fn ($token) => Tokens::isPersonal($token));
    $apps = $apps->groupBy('client_id');
    $grantable = Scopes::grantable($person);
    $describe = fn (?array $ids) => collect($ids ?? [])->map(fn (string $id) => Scopes::find($id)?->description ?? $id)->join(', ');
@endphp
@if ($apps->isNotEmpty() || $personal->isNotEmpty() || $grantable !== [] || session('oauth_new_token'))
    <section id="connected-apps" class="card card-border bg-base-100 scroll-mt-20">
        <div class="card-body gap-4">
            <div>
                <h2 class="card-title">{{ __('kopling-oauth::messages.apps.heading') }}</h2>
                <p class="text-sm opacity-60">{{ __('kopling-oauth::messages.apps.help') }}</p>
            </div>

            @if (session('oauth_status'))
                <p class="text-success text-sm">{{ session('oauth_status') }}</p>
            @endif

            @if (session('oauth_new_token'))
                <div role="alert" class="alert alert-warning alert-soft flex-col items-stretch gap-2">
                    <span class="font-semibold">{{ __('kopling-oauth::messages.apps.new_token') }}</span>
                    <input type="text" readonly value="{{ session('oauth_new_token') }}" x-data x-on:focus="$el.select()"
                           class="input input-sm w-full font-mono" aria-label="{{ __('kopling-oauth::messages.apps.new_token') }}">
                </div>
            @endif

            @if ($apps->isNotEmpty())
                <ul class="list">
                    @foreach ($apps as $tokens)
                        @php $client = $tokens->first()->client; @endphp
                        <li class="list-row items-center">
                            <x-k::icon name="kopling-oauth::app" />
                            <div class="list-col-grow min-w-0">
                                <div class="font-semibold truncate">{{ $client->name }}</div>
                                <div class="text-sm opacity-60">{{ $describe($tokens->pluck('scopes')->flatten()->unique()->all()) }}</div>
                                <div class="text-xs opacity-60">{{ __('kopling-oauth::messages.apps.since', ['date' => $tokens->min('created_at')->translatedFormat('j F Y')]) }}</div>
                            </div>
                            <form method="POST" action="{{ route('kopling-core::community/oauth.clients.revoke', $client->id) }}"
                                  hx-boost="true" hx-confirm="{{ __('kopling-oauth::messages.apps.confirm_revoke', ['client' => $client->name]) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-error btn-outline">{{ __('kopling-oauth::messages.revoke') }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($personal->isNotEmpty() || $grantable !== [])
                <div class="flex flex-col gap-2">
                    <h3 class="font-semibold">{{ __('kopling-oauth::messages.apps.personal') }}</h3>
                    <p class="text-sm opacity-60">{{ __('kopling-oauth::messages.apps.personal_help') }}</p>
                    @if ($personal->isNotEmpty())
                        <ul class="list">
                            @foreach ($personal as $token)
                                <li class="list-row items-center">
                                    <div class="list-col-grow min-w-0">
                                        <div class="truncate">{{ $token->name }}</div>
                                        <div class="text-sm opacity-60">{{ $describe($token->scopes) }}</div>
                                        @if ($token->expires_at)
                                            <div class="text-xs opacity-60">{{ __('kopling-oauth::messages.apps.expires', ['date' => $token->expires_at->translatedFormat('j F Y')]) }}</div>
                                        @endif
                                    </div>
                                    <form method="POST" action="{{ route('kopling-core::community/oauth.tokens.revoke', $token->id) }}" hx-boost="true">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-ghost">{{ __('kopling-oauth::messages.revoke') }}</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($grantable !== [])
                        <form method="POST" action="{{ route('kopling-core::community/oauth.tokens.store') }}" class="flex flex-col gap-3" autocomplete="off">
                            @csrf
                            <x-k::form.input :data="['name' => 'name', 'label' => __('kopling-oauth::messages.apps.token_name'), 'required' => true]" />
                            <fieldset class="fieldset">
                                <legend class="fieldset-legend">{{ __('kopling-oauth::messages.apps.token_scopes') }}</legend>
                                @foreach ($grantable as $scope)
                                    <label class="label">
                                        <input type="checkbox" name="scopes[]" value="{{ $scope->id }}" class="checkbox checkbox-sm">
                                        {{ $scope->description }}
                                    </label>
                                @endforeach
                            </fieldset>
                            @if ($errors->hasAny(['name', 'scopes', 'scopes.*']))
                                <p class="text-error text-sm">{{ $errors->first('name') ?: $errors->first('scopes') ?: $errors->first('scopes.*') }}</p>
                            @endif
                            <button type="submit" class="btn btn-sm self-start">{{ __('kopling-oauth::messages.apps.create_token') }}</button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    </section>
@endif
