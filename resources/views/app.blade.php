<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#070b12">
    <meta name="description" content="{{ __('meta.description') }}">
    <title inertia>{{ config('app.name', 'XNova') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/hud/css/vendor.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/hud/css/app.min.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="app-init">
    @inertia
</body>
</html>
