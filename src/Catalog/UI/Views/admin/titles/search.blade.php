@php($posterBase = config('services.tmdb.image_base_url').'/w185')

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Importer un film depuis TMDB') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @include('catalog::admin.titles._flash')

            @if ($error)
                <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $error }}</div>
            @endif

            <form method="GET" action="{{ route('admin.titles.search') }}" class="mb-6 flex gap-3">
                <label for="query" class="sr-only">{{ __('Titre du film') }}</label>
                <input id="query" name="query" type="search" value="{{ $query }}" maxlength="200" required autofocus
                       placeholder="{{ __('Titre du film') }}"
                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <x-primary-button>{{ __('Rechercher') }}</x-primary-button>
            </form>

            @error('query')
                <p class="mb-4 text-sm text-red-700">{{ $message }}</p>
            @enderror

            @if ($query !== '' && ! $error)
                @if ($hits === [])
                    <p class="text-gray-600">{{ __('Aucun film trouvé.') }}</p>
                @else
                    <ul class="space-y-4">
                        @foreach ($hits as $hit)
                            <li class="flex gap-4 rounded-lg bg-white p-4 shadow-sm">
                                @if ($hit->result->posterPath)
                                    <img src="{{ $posterBase.$hit->result->posterPath }}" alt="" class="h-36 w-24 flex-none rounded object-cover" loading="lazy">
                                @else
                                    <div class="h-36 w-24 flex-none rounded bg-gray-100"></div>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-semibold text-gray-900">
                                        {{ $hit->result->name }}
                                        @if ($hit->result->releaseYear)
                                            <span class="font-normal text-gray-500">({{ $hit->result->releaseYear }})</span>
                                        @endif
                                    </h3>
                                    @if ($hit->result->originalName && $hit->result->originalName !== $hit->result->name)
                                        <p class="text-sm text-gray-500">{{ $hit->result->originalName }}</p>
                                    @endif
                                    <p class="mt-2 text-sm text-gray-700">{{ \Illuminate\Support\Str::limit($hit->result->overview, 280) }}</p>
                                    <div class="mt-3 flex items-center gap-3">
                                        <a href="{{ route('admin.titles.preview', $hit->result->tmdbId->value) }}" class="text-sm font-medium text-indigo-700 hover:underline">{{ __('Aperçu') }}</a>
                                        @if ($hit->imported)
                                            <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800">{{ __('Déjà au catalogue') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
