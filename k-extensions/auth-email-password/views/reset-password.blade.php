<x-k::portal.layout>
    <div class="min-h-screen flex items-center justify-center">
        <div class="card card-border bg-base-100 w-full max-w-sm">
            <div class="card-body">
                <h1 class="card-title">{{ __('kopling-auth-email-password::messages.choose_new_password') }}</h1>
                {{-- Same `hx-boost` reasoning as login-form.blade.php's own comment. --}}
                <form method="POST" action="{{ route('kopling-core::community/password.update', $token) }}" hx-boost="true" autocomplete="off" class="flex flex-col gap-3">
                    @csrf
                    <fieldset class="fieldset">
                        <label class="input w-full {{ $errors->has('email') ? 'input-error' : '' }}">
                            <span class="label">{{ __('kopling-auth-email-password::messages.email') }}</span>
                            <input type="email" name="email" value="{{ old('email', $email) }}" required />
                        </label>
                        @error('email')
                            <p class="label text-error">{{ $message }}</p>
                        @enderror

                        <label class="input w-full {{ $errors->has('password') ? 'input-error' : '' }}">
                            <span class="label">{{ __('kopling-auth-email-password::messages.new_password') }}</span>
                            <input type="password" name="password" required autofocus autocomplete="new-password" />
                        </label>
                        @error('password')
                            <p class="label text-error">{{ $message }}</p>
                        @enderror

                        <label class="input w-full">
                            <span class="label">{{ __('kopling-auth-email-password::messages.password_confirmation') }}</span>
                            <input type="password" name="password_confirmation" required autocomplete="new-password" />
                        </label>

                        <button type="submit" class="btn btn-primary w-full">{{ __('kopling-auth-email-password::messages.save_password') }}</button>
                    </fieldset>
                </form>
            </div>
        </div>
    </div>
</x-k::portal.layout>
