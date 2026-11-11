<x-k::portal.auth-layout page="login" :title="__('kopling-core::auth.log_in')">
    @if (session('status'))
        <p role="status" class="alert alert-success text-sm">{{ session('status') }}</p>
    @endif
    <x-k::portal.slot name="kopling-core::auth.login-form" />
</x-k::portal.auth-layout>
