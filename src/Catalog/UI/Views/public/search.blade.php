<x-catalog::public-layout :title="__('Recherche')" :description="__('Rechercher un film ou une série.')" noindex>
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <h1 class="mb-6 text-2xl font-bold">{{ __('Recherche') }}</h1>

        <livewire:catalog.search-titles />
    </div>
</x-catalog::public-layout>
