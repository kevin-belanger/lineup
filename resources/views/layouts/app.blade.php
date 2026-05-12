<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ app(\App\Services\ApplicationSettings::class)->displayName() }}</title>
        <link rel="icon" type="image/png" href="{{ asset(app(\App\Services\ApplicationSettings::class)->faviconPath()) }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @livewireStyles
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="flex min-h-screen flex-col bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="flex-1">
                {{ $slot }}
            </main>

            <footer class="py-4 text-center text-xs text-gray-500">
                <a
                    href="{{ config('app.repository_url') }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="hover:underline hover:text-gray-700"
                >
                    Powered by LineUp · {{ config('app.version') }}
                </a>
            </footer>

        </div>
        <x-toast-stack />
        @livewireScripts
    </body>
</html>
