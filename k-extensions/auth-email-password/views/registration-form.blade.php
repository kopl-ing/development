<form method="POST" action="{{ route('kopling-core::community/register.attempt') }}" class="flex flex-col gap-3">
    @csrf

    <fieldset class="fieldset">
        <label class="input w-full {{ $errors->has('name') ? 'input-error' : '' }}">
            <span class="label">{{ __('kopling-auth-email-password::messages.name') }}</span>
            <input type="text" name="name" value="{{ old('name') }}" required autofocus />
        </label>
        @error('name')
            <p class="label text-error">{{ $message }}</p>
        @enderror

        <label class="input w-full {{ $errors->has('email') ? 'input-error' : '' }}">
            <span class="label">{{ __('kopling-auth-email-password::messages.email') }}</span>
            <input type="email" name="email" value="{{ old('email') }}" required />
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

        <label class="input w-full">
            <span class="label">{{ __('kopling-auth-email-password::messages.password_confirmation') }}</span>
            <input type="password" name="password_confirmation" required />
        </label>

        <button type="submit" class="btn btn-primary w-full">{{ __('kopling-auth-email-password::messages.create_account') }}</button>
    </fieldset>
</form>
