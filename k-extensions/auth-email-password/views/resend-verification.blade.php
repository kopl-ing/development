{{-- Same `hx-boost` reasoning as login-form.blade.php's own comment. --}}
<form method="POST" action="{{ route('kopling-core::community/verification.resend') }}" hx-boost="true" class="flex flex-col gap-2">
    @csrf
    <label class="input w-full input-sm">
        <span class="label">{{ __('kopling-auth-email-password::messages.email') }}</span>
        <input type="email" name="email" value="{{ session('verification_email', old('email')) }}" required />
    </label>
    <button type="submit" class="btn btn-sm">{{ __('kopling-auth-email-password::messages.resend_verification') }}</button>
</form>
