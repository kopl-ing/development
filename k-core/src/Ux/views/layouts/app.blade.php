<!DOCTYPE html>
<html lang="en" data-theme="kopling">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Kopling')</title>
    @vite(['k-core/src/Ux/css/app.css', 'k-core/src/Ux/js/app.js'])
</head>
<body class="bg-base-200 text-base-content min-h-screen">
    @yield('content')
</body>
</html>
