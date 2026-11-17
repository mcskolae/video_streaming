<x-catalog::public-layout :title="__('Mes favoris')" :description="__('Les films et séries que vous avez mis de côté.')">
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <h1 class="mb-6 text-2xl font-bold">{{ __('Mes favoris') }}</h1>

        @if ($favorites === [])
            <p class="rounded-lg bg-white p-6 text-gray-600 shadow-sm ring-1 ring-gray-200">
                {{ __('Vous n\'avez pas encore de favori. Ouvrez la fiche d\'un film ou d\'une série et choisissez « Ajouter aux favoris ».') }}
            </p>
        @else
            <p class="mb-4 text-sm text-gray-500">{{ trans_choice(':count favori|:count favoris', count($favorites)) }}</p>
            <x-catalog::title-grid :cards="$favorites" />
        @endif
    </div>
</x-catalog::public-layout>
