<x-catalog::public-layout :description="__('Votre vidéothèque personnelle : films et séries en streaming.')">
    <section class="bg-gradient-to-b from-indigo-700 to-indigo-600 text-white">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ config('app.name') }}</h1>
            <p class="mt-2 max-w-2xl text-indigo-100">{{ __('Votre vidéothèque personnelle : films et séries en streaming, depuis n\'importe quel appareil.') }}</p>
        </div>
    </section>

    <div class="mx-auto max-w-7xl space-y-10 px-4 py-10 sm:px-6 lg:px-8">
        @auth
            <x-library-continue-watching />
            <x-library-my-list />
        @endauth

        <section aria-labelledby="derniers-ajouts">
            <h2 id="derniers-ajouts" class="mb-4 text-xl font-semibold">{{ __('Derniers ajouts') }}</h2>

            @if ($latest === [])
                <p class="rounded-lg bg-white p-6 text-gray-600 shadow-sm ring-1 ring-gray-200">
                    {{ __('Le catalogue est vide pour le moment. Revenez bientôt !') }}
                </p>
            @else
                <x-catalog::title-grid :cards="$latest" />
            @endif
        </section>

        @foreach ($genreRows as $row)
            <section aria-labelledby="genre-{{ $row['genre']->tmdbId }}">
                <h2 id="genre-{{ $row['genre']->tmdbId }}" class="mb-4 text-xl font-semibold">{{ $row['genre']->name }}</h2>
                <x-catalog::title-grid :cards="$row['cards']" />
            </section>
        @endforeach
    </div>
</x-catalog::public-layout>
