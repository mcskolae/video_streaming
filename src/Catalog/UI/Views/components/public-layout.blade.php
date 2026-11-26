@props(['title' => null, 'description' => null, 'canonical' => null, 'image' => null, 'type' => 'website', 'noindex' => false])

@php
    $pageTitle = $title ? $title.' · '.config('app.name') : config('app.name');
    $metaDescription = $description ? \Illuminate\Support\Str::limit(strip_tags($description), 160) : null;
    // L'adresse officielle de la page, sans paramètre de requête : évite que /?utm=… compte comme une autre page.
    $canonicalUrl = $canonical ?? url()->current();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }}</title>
    @if ($metaDescription)
        <meta name="description" content="{{ $metaDescription }}">
    @endif
    @if ($noindex)
        <meta name="robots" content="noindex, follow">
    @endif
    <link rel="canonical" href="{{ $canonicalUrl }}">

    {{-- Open Graph : l'aperçu affiché quand le lien est partagé (messageries, réseaux sociaux). --}}
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:type" content="{{ $type }}">
    <meta property="og:title" content="{{ $title ?? config('app.name') }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    @if ($metaDescription)
        <meta property="og:description" content="{{ $metaDescription }}">
    @endif
    @if ($image)
        <meta property="og:image" content="{{ $image }}">
    @endif
    <meta name="twitter:card" content="summary">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="flex min-h-screen flex-col bg-gray-50 font-sans text-gray-900 antialiased">
    <a href="#contenu" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2 focus:text-sm focus:shadow">
        {{ __('Aller au contenu') }}
    </a>

    <header class="border-b border-gray-200 bg-white" x-data="{ open: false }">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between gap-4">
                <a href="{{ route('home') }}" class="text-xl font-bold tracking-tight text-indigo-700">{{ config('app.name') }}</a>



                <div class="hidden flex-1 items-center justify-end gap-4 text-sm font-medium md:flex">
                    <form role="search" method="GET" action="{{ route('search') }}" class="relative w-64">
                        <label for="header-search" class="sr-only">{{ __('Rechercher un titre') }}</label>
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607z" />
                        </svg>
                        <input id="header-search" name="q" type="search" maxlength="100" placeholder="{{ __('Rechercher…') }}"
                               class="w-full rounded-full border-gray-300 py-1.5 pl-9 pr-4 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </form>
                    <div class="flex items-center gap-4">
                    @auth
                        @can('administer')
                            <a href="{{ route('admin.titles.index') }}" class="text-gray-700 hover:text-indigo-700">{{ __('Administration') }}</a>
                        @endcan
                        <a href="{{ route('profile.edit') }}" class="text-gray-700 hover:text-indigo-700">{{ __('Mon compte') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="text-gray-700 hover:text-indigo-700">{{ __('Connexion') }}</a>
                        <a href="{{ route('register') }}" class="rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700">{{ __('Inscription') }}</a>
                    @endauth
                    </div>
                </div>

                <button type="button" class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 md:hidden"
                        @click="open = ! open" :aria-expanded="open.toString()" aria-controls="menu-mobile" aria-label="{{ __('Menu') }}">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path x-show="! open" stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        <path x-show="open" x-cloak stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <div id="menu-mobile" x-show="open" x-cloak class="border-t border-gray-200 md:hidden">
            <nav aria-label="{{ __('Navigation mobile') }}" class="space-y-1 px-4 py-3 text-sm font-medium text-gray-700">
                <form role="search" method="GET" action="{{ route('search') }}" class="mb-3">
                    <label for="mobile-search" class="sr-only">{{ __('Rechercher un titre') }}</label>
                    <input id="mobile-search" name="q" type="search" maxlength="100" placeholder="{{ __('Rechercher…') }}"
                           class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </form>
                @auth
                    @can('administer')
                        <a href="{{ route('admin.titles.index') }}" class="block rounded px-2 py-2 hover:bg-gray-100">{{ __('Administration') }}</a>
                    @endcan
                    <a href="{{ route('profile.edit') }}" class="block rounded px-2 py-2 hover:bg-gray-100">{{ __('Mon compte') }}</a>
                @else
                    <a href="{{ route('login') }}" class="block rounded px-2 py-2 hover:bg-gray-100">{{ __('Connexion') }}</a>
                    <a href="{{ route('register') }}" class="block rounded px-2 py-2 hover:bg-gray-100">{{ __('Inscription') }}</a>
                @endauth
            </nav>
        </div>
    </header>

    <main id="contenu" class="flex-1">
        {{ $slot }}
    </main>

    <footer class="border-t border-gray-200 bg-white">
        <div class="mx-auto max-w-7xl space-y-2 px-4 py-6 text-xs text-gray-500 sm:px-6 lg:px-8">
            <p>
                {{ __('Ce produit utilise l\'API TMDB mais n\'est ni approuvé ni certifié par TMDB.') }}
                <span lang="en">This product uses the TMDB API but is not endorsed or certified by TMDB.</span>
            </p>
            <p>
                {{ __('Métadonnées et affiches fournies par') }}
                <a href="https://www.themoviedb.org/" class="font-medium text-indigo-700 hover:underline" rel="noopener noreferrer" target="_blank">The Movie Database (TMDB)</a>.
            </p>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
