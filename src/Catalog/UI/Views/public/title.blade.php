@php
    $isSeries = $title->type->value === 'series';
    $minutes = $title->runtimeMinutes;
@endphp

<x-catalog::public-layout :title="$title->name" :description="$title->overview !== '' ? $title->overview : $title->name">
    <article class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
        <nav aria-label="{{ __('Fil d\'Ariane') }}" class="mb-4 text-sm text-gray-500">
            <a href="{{ route('home') }}" class="hover:text-indigo-700">{{ __('Accueil') }}</a>
            <span aria-hidden="true">/</span> {{ $isSeries ? __('Série') : __('Film') }}
        </nav>

        <div class="flex flex-col gap-6 sm:flex-row">
            <x-catalog::poster :path="$title->posterPath" size="w500" :alt="__('Affiche de :title', ['title' => $title->name])"
                               class="aspect-[2/3] w-48 flex-none self-center rounded-lg shadow sm:self-start" />

            <div class="min-w-0 flex-1">
                <header>
                    <h1 class="text-3xl font-bold tracking-tight">
                        {{ $title->name }}
                        @if ($title->releaseYear)
                            <span class="font-normal text-gray-500">({{ $title->releaseYear }})</span>
                        @endif
                    </h1>

                    @if ($title->originalName && $title->originalName !== $title->name)
                        <p class="text-gray-500">{{ $title->originalName }}</p>
                    @endif
                </header>

                <dl class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-600">
                    <div><dt class="inline font-medium">{{ __('Type') }} :</dt> <dd class="inline">{{ $isSeries ? __('Série') : __('Film') }}</dd></div>
                    @if ($minutes)
                        <div><dt class="inline font-medium">{{ __('Durée') }} :</dt> <dd class="inline">{{ $minutes }} min</dd></div>
                    @endif
                    @if ($isSeries && $title->seasons !== [])
                        <div><dt class="inline font-medium">{{ __('Saisons') }} :</dt> <dd class="inline">{{ count($title->seasons) }}</dd></div>
                    @endif
                </dl>

                @if ($title->genres !== [])
                    <ul class="mt-4 flex flex-wrap gap-2" aria-label="{{ __('Genres') }}">
                        @foreach ($title->genres as $genre)
                            <li>
                                <a href="{{ route('genres.show', $genre->tmdbId) }}"
                                   class="inline-block rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100">{{ $genre->name }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <section class="mt-6" aria-labelledby="synopsis">
                    <h2 id="synopsis" class="mb-2 text-lg font-semibold">{{ __('Synopsis') }}</h2>
                    <p class="leading-relaxed text-gray-700">{{ $title->overview !== '' ? $title->overview : __('Pas de synopsis disponible.') }}</p>
                </section>
            </div>
        </div>

        @if ($isSeries && $title->seasons !== [])
            <section class="mt-10" aria-labelledby="saisons">
                <h2 id="saisons" class="mb-4 text-xl font-semibold">{{ __('Saisons et épisodes') }}</h2>

                <div class="space-y-3">
                    @foreach ($title->seasons as $season)
                        <details class="rounded-lg bg-white shadow-sm ring-1 ring-gray-200" @if ($loop->first) open @endif>
                            <summary class="cursor-pointer px-4 py-3 font-medium">
                                {{ $season->number === 0 ? __('Épisodes spéciaux') : ($season->name !== '' ? $season->name : __('Saison :n', ['n' => $season->number])) }}
                                <span class="text-sm font-normal text-gray-500">· {{ trans_choice(':count épisode|:count épisodes', count($season->episodes)) }}</span>
                            </summary>

                            @if ($season->overview !== '')
                                <p class="px-4 pb-2 text-sm text-gray-600">{{ $season->overview }}</p>
                            @endif

                            @if ($season->episodes === [])
                                <p class="px-4 pb-4 text-sm text-gray-500">{{ __('Aucun épisode renseigné.') }}</p>
                            @else
                                <ol class="divide-y divide-gray-100 border-t border-gray-100">
                                    @foreach ($season->episodes as $episode)
                                        <li class="flex gap-4 px-4 py-3">
                                            <span class="w-8 flex-none text-right text-sm font-medium text-gray-400">{{ $episode->number }}</span>
                                            <div class="min-w-0 flex-1">
                                                <p class="font-medium">
                                                    {{ $episode->name !== '' ? $episode->name : __('Épisode :n', ['n' => $episode->number]) }}
                                                    @if ($episode->runtimeMinutes)
                                                        <span class="text-sm font-normal text-gray-500">· {{ $episode->runtimeMinutes }} min</span>
                                                    @endif
                                                </p>
                                                @if ($episode->overview !== '')
                                                    <p class="mt-0.5 text-sm text-gray-600">{{ $episode->overview }}</p>
                                                @endif
                                            </div>
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                        </details>
                    @endforeach
                </div>
            </section>
        @endif
    </article>
</x-catalog::public-layout>
