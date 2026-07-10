<form method="POST" action="{{ route('core::community/login.attempt') }}" class="flex flex-col gap-3">
    @csrf

    <fieldset class="fieldset">
        <label class="input w-full {{ $errors->has('email') ? 'input-error' : '' }}">
            <span class="label">{{ __('kopling-auth-password::messages.email') }}</span>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus />
        </label>
        @error('email')
            <p class="label text-error">{{ $message }}</p>
        @enderror

        <label class="input w-full {{ $errors->has('password') ? 'input-error' : '' }}">
            <span class="label">{{ __('kopling-auth-password::messages.password') }}</span>
            <input type="password" name="password" required />
        </label>
        @error('password')
            <p class="label text-error">{{ $message }}</p>
        @enderror

        <label class="label gap-2">
            <input type="checkbox" name="remember" class="checkbox checkbox-sm" />
            {{ __('kopling-auth-password::messages.remember') }}
        </label>

        <button type="submit" class="btn btn-primary w-full">{{ __('kopling-auth-password::messages.submit') }}</button>
    </fieldset>
</form>
