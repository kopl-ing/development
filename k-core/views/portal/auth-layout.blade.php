@php use Kopling\Core\Ux\Portal\AuthLayout; @endphp
<x-k::portal.layout>
    <main data-kopling-auth="{{ $page }}" {{ $attributes->class('min-h-screen flex flex-col items-center justify-center gap-6 p-4') }}>
        <x-k::portal.slot :name="AuthLayout::HEADER" />
        <div class="flex w-full flex-col items-center justify-center gap-8 lg:flex-row">
            <div class="card card-border bg-base-100 w-full max-w-sm">
                <div class="card-body">
                    <h1 class="card-title">{{ $title }}</h1>
                    {{ $slot }}
                </div>
            </div>
            <x-k::portal.slot :name="AuthLayout::ASIDE" />
        </div>
        <x-k::portal.slot :name="AuthLayout::FOOTER" />
    </main>
</x-k::portal.layout>
