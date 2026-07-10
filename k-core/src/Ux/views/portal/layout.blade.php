<!DOCTYPE html>
<html lang="en" data-theme="kopling">
<head>
    @include('core::layouts.partials.head')
</head>
<body class="bg-base-200 text-base-content min-h-screen">
    <div class="flex min-h-screen">
        <x-k::portal.navigation.side :portal="$portal" />
        <div class="flex-1">
            <header class="navbar bg-base-100 border-b border-base-300">
                <span class="text-lg font-semibold px-4">{{ $portal->label }}</span>
            </header>
            <main class="p-6">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
