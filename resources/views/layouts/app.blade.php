<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="stylesheet" href="https://use.typekit.net/pdd8jng.css">

        <!-- Favicon -->
        <link rel="icon" type="image/png" href="{{ asset('favicon/favicon-96x96.png') }}" sizes="96x96" />
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon/favicon.svg') }}" />
        <link rel="shortcut icon" href="{{ asset('favicon/favicon.ico') }}" />
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon/apple-touch-icon.png') }}" />
        <meta name="apple-mobile-web-app-title" content="Intranet" />
        <link rel="manifest" href="{{ asset('favicon/site.webmanifest') }}" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="text-sm leading-relaxed text-(--color-text) antialiased [font-family:gotham,sans-serif] selection:bg-(--color-primary) selection:text-(--color-background) [&_:focus-visible]:outline-2 [&_:focus-visible]:outline-offset-4 [&_:focus-visible]:outline-(--color-primary) [&_button:not(:disabled)]:cursor-pointer [&_select]:cursor-pointer [&_*]:motion-reduce:transition-none bg-(--color-background) antialiased">

        <x-setting/>

        <div class="min-h-screen bg-(--color-background)">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-transparent pt-[22px]">
                    <div class="max-w-7xl mx-auto py-3.5 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="pb-16">

                <x-form-feedback />

                {{ $slot }}
            </main>
        </div>
    </body>
</html>
