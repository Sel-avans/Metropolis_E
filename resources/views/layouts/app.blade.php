<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/grid.css', 'resources/css/app.css', 'resources/js/effects.js', 'resources/js/app.js', 'resources/js/grid.js'])
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    </head>
    
    <body class="font-sans antialiased bg-gray-100 dark:bg-gray-900 xl:h-screen xl:overflow-hidden">
        
        <div class="flex flex-col min-h-screen xl:h-full">
            
            <div class="shrink-0">
                @include('layouts.navigation')
            </div>

            @isset($header)
                <header class="bg-white dark:bg-gray-800 shadow shrink-0">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main class="flex-1 flex flex-col w-full xl:overflow-hidden">
                {{ $slot }}
            </main>
        </div>
    </body>