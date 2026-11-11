<x-k::portal.auth-layout page="verify-email" :title="__('kopling-auth-email-password::messages.check_email')">
    <p class="text-sm opacity-70">{{ __('kopling-auth-email-password::messages.check_email_help') }}</p>
    @if (session('status'))
        <p role="status" class="alert alert-success text-sm">{{ session('status') }}</p>
    @endif
    @include('kopling-auth-email-password::resend-verification')
    <a href="{{ route('kopling-core::community/login') }}" class="link link-hover text-sm">{{ __('kopling-auth-email-password::messages.back_to_login') }}</a>
</x-k::portal.auth-layout>
