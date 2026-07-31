{{-- Both the failure and success paths are already plain redirects, so `hx-boost` needs no controller changes. --}}
<form method="POST" action="{{ route('kopling-core::community/login.attempt') }}" hx-boost="true" class="flex flex-col gap-3">
    @csrf

    <fieldset class="fieldset">
        <label class="input w-full {{ $errors->has('email') ? 'input-error' : '' }}">
            <span class="label">{{ __('kopling-auth-email-password::messages.email') }}</span>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus />
        </label>
        @error('email')
            <p class="label text-error">{{ $message }}</p>
        @enderror

        <label class="input w-full {{ $errors->has('password') ? 'input-error' : '' }}">
            <span class="label">{{ __('kopling-auth-email-password::messages.password') }}</span>
            <input type="password" name="password" required />
        </label>
        @error('password')
            <p class="label text-error">{{ $message }}</p>
        @enderror

        <label class="label gap-2">
            <input type="checkbox" name="remember" class="checkbox checkbox-sm" />
            {{ __('kopling-auth-email-password::messages.remember') }}
        </label>

        <button type="submit" class="btn btn-primary w-full">{{ __('kopling-auth-email-password::messages.submit') }}</button>
    </fieldset>
</form>
