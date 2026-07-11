<x-k::portal.layout>
    <div class="min-h-screen flex items-center justify-center">
        <div class="card card-border bg-base-100 w-full max-w-sm">
            <div class="card-body">
                <h1 class="card-title">{{ __('kopling-core::auth.register') }}</h1>
                <x-k::portal.slot name="kopling-core::auth.registration-form" />
            </div>
        </div>
    </div>
</x-k::portal.layout>
