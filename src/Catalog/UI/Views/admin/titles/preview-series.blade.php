@php
    $details = $preview->series->details;
    $posterBase = config('services.tmdb.image_base_url').'/w342';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Aperçu de la série') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @include('catalog::admin.titles._flash')

            <div class="flex gap-6 rounded-lg bg-white p-6 shadow-sm">
                @if ($details->posterPath)
                    <img src="{{ $posterBase.$details->posterPath }}" alt="" class="h-72 w-48 flex-none rounded object-cover">
                @else
                    <div class="h-72 w-48 flex-none rounded bg-gray-100"></div>
                @endif

                <div class="min-w-0 flex-1">
                    <h3 class="text-2xl font-semibold text-gray-900">
                        {{ $details->name }}
                        @if ($details->releaseYear)
                            <span class="font-normal text-gray-500">({{ $details->releaseYear }})</span>
                        @endif
                    </h3>

                    @if ($details->originalName && $details->originalName !== $details->name)
                        <p class="text-gray-500">{{ $details->originalName }}</p>
                    @endif

                    <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600">
                        <div><dt class="inline font-medium">{{ __('Saisons') }} :</dt> <dd class="inline">{{ count($preview->series->seasons) }}</dd></div>
                        <div><dt class="inline font-medium">{{ __('Épisodes') }} :</dt> <dd class="inline">{{ $preview->episodeCount() }}</dd></div>
                        @if ($details->genres !== [])
                            <div>
                                <dt class="inline font-medium">{{ __('Genres') }} :</dt>
                                <dd class="inline">{{ collect($details->genres)->map(fn ($genre) => $genre->name)->implode(', ') }}</dd>
                            </div>
                        @endif
                    </dl>

                    <p class="mt-4 text-gray-700">{{ $details->overview !== '' ? $details->overview : __('Pas de synopsis.') }}</p>

                    <div class="mt-6 flex items-center gap-4">
                        <form method="POST" action="{{ route('admin.titles.import-series') }}">
                            @csrf
                            <input type="hidden" name="tmdb_id" value="{{ $preview->series->tmdbId->value }}">
                            <x-primary-button>
                                {{ $preview->imported ? __('Mettre à jour la série') : __('Importer en brouillon') }}
                            </x-primary-button>
                        </form>
                        <a href="{{ route('admin.titles.search', ['type' => 'series']) }}" class="text-sm text-gray-600 hover:underline">{{ __('Retour à la recherche') }}</a>
                    </div>

                    @if ($preview->imported)
                        <p class="mt-3 text-sm text-green-700">{{ __('Cette série est déjà au catalogue : l\'import met à jour ses saisons et ses épisodes sans créer de doublon, et garde les vidéos déjà envoyées.') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
