<x-k::portal.layout>
    <div class="min-h-screen flex items-center justify-center">
        <div class="card card-border bg-base-100 w-full max-w-sm">
            <div class="card-body">
                <h1 class="card-title">{{ __('kopling-auth-email-password::messages.check_email') }}</h1>
                <p class="text-sm opacity-70">{{ __('kopling-auth-email-password::messages.check_email_help') }}</p>
                @if (session('status'))
                    <p role="status" class="alert alert-success text-sm">{{ session('status') }}</p>
                @endif
                @include('kopling-auth-email-password::resend-verification')
                <a href="{{ route('kopling-core::community/login') }}" class="link link-hover text-sm">{{ __('kopling-auth-email-password::messages.back_to_login') }}</a>
            </div>
        </div>
    </div>
</x-k::portal.layout>
