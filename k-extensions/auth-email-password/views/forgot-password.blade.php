<x-k::portal.layout>
    <div class="min-h-screen flex items-center justify-center">
        <div class="card card-border bg-base-100 w-full max-w-sm">
            <div class="card-body">
                <h1 class="card-title">{{ __('kopling-auth-email-password::messages.forgot_password') }}</h1>
                <p class="text-sm opacity-70">{{ __('kopling-auth-email-password::messages.forgot_password_help') }}</p>
                @if (session('status'))
                    <p role="status" class="alert alert-success text-sm">{{ session('status') }}</p>
                @endif
                {{-- Same `hx-boost` reasoning as login-form.blade.php's own comment. --}}
                <form method="POST" action="{{ route('kopling-core::community/password.email') }}" hx-boost="true" class="flex flex-col gap-3">
                    @csrf
                    <fieldset class="fieldset">
                        <label class="input w-full {{ $errors->has('email') ? 'input-error' : '' }}">
                            <span class="label">{{ __('kopling-auth-email-password::messages.email') }}</span>
                            <input type="email" name="email" value="{{ old('email') }}" required autofocus />
                        </label>
                        @error('email')
                            <p class="label text-error">{{ $message }}</p>
                        @enderror
                        <button type="submit" class="btn btn-primary w-full">{{ __('kopling-auth-email-password::messages.send_reset_link') }}</button>
                    </fieldset>
                </form>
                <a href="{{ route('kopling-core::community/login') }}" class="link link-hover text-sm">{{ __('kopling-auth-email-password::messages.back_to_login') }}</a>
            </div>
        </div>
    </div>
</x-k::portal.layout>
