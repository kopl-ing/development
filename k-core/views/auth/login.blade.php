<x-k::portal.layout>
    <div class="min-h-screen flex items-center justify-center">
        <div class="card card-border bg-base-100 w-full max-w-sm">
            <div class="card-body">
                <h1 class="card-title">{{ __('kopling-core::auth.log_in') }}</h1>
                @if (session('status'))
                    <p role="status" class="alert alert-success text-sm">{{ session('status') }}</p>
                @endif
                <x-k::portal.slot name="kopling-core::auth.login-form" />
            </div>
        </div>
    </div>
</x-k::portal.layout>
