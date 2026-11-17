@props(['title' => null, 'description' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>
    @if ($description)
        <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($description), 160) }}">
    @endif
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

                <nav aria-label="{{ __('Navigation principale') }}" class="hidden items-center gap-6 text-sm font-medium text-gray-700 md:flex">
                    <a href="{{ route('home') }}" class="hover:text-indigo-700">{{ __('Accueil') }}</a>

                    @if ($navGenres !== [])
                        <details class="relative">
                            <summary class="cursor-pointer list-none hover:text-indigo-700">{{ __('Genres') }}</summary>
                            <ul class="absolute left-0 z-20 mt-2 max-h-80 w-56 overflow-y-auto rounded-md bg-white py-1 shadow-lg ring-1 ring-gray-200">
                                @foreach ($navGenres as $genre)
                                    <li>
                                        <a href="{{ route('genres.show', $genre->tmdbId) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            {{ $genre->name }} <span class="text-gray-400">({{ $genre->titleCount }})</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </nav>

                <form role="search" method="GET" action="{{ route('search') }}" class="hidden md:block">
                    <label for="header-search" class="sr-only">{{ __('Rechercher un titre') }}</label>
                    <input id="header-search" name="q" type="search" maxlength="100" placeholder="{{ __('Rechercher…') }}"
                           class="w-56 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </form>

                <div class="hidden items-center gap-4 text-sm font-medium md:flex">
                    @auth
                        @can('administer')
                            <a href="{{ route('admin.titles.index') }}" class="text-gray-700 hover:text-indigo-700">{{ __('Administration') }}</a>
                        @endcan
                        <a href="{{ route('favorites.index') }}" class="text-gray-700 hover:text-indigo-700">{{ __('Mes favoris') }}</a>
                        <a href="{{ route('dashboard') }}" class="text-gray-700 hover:text-indigo-700">{{ __('Mon compte') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="text-gray-700 hover:text-indigo-700">{{ __('Connexion') }}</a>
                        <a href="{{ route('register') }}" class="rounded-md bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700">{{ __('Inscription') }}</a>
                    @endauth
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
                <a href="{{ route('home') }}" class="block rounded px-2 py-2 hover:bg-gray-100">{{ __('Accueil') }}</a>
                @foreach ($navGenres as $genre)
                    <a href="{{ route('genres.show', $genre->tmdbId) }}" class="block rounded px-2 py-2 hover:bg-gray-100">{{ $genre->name }}</a>
                @endforeach
                @auth
                    @can('administer')
                        <a href="{{ route('admin.titles.index') }}" class="block rounded px-2 py-2 hover:bg-gray-100">{{ __('Administration') }}</a>
                    @endcan
                    <a href="{{ route('favorites.index') }}" class="block rounded px-2 py-2 hover:bg-gray-100">{{ __('Mes favoris') }}</a>
                    <a href="{{ route('dashboard') }}" class="block rounded px-2 py-2 hover:bg-gray-100">{{ __('Mon compte') }}</a>
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
