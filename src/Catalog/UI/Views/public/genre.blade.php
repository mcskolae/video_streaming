<x-catalog::public-layout :title="$genre->name" :description="__('Films et séries du genre :genre.', ['genre' => $genre->name])">
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <nav aria-label="{{ __('Fil d\'Ariane') }}" class="mb-4 text-sm text-gray-500">
            <a href="{{ route('home') }}" class="hover:text-indigo-700">{{ __('Accueil') }}</a>
            <span aria-hidden="true">/</span> {{ __('Genres') }}
        </nav>

        <h1 class="text-2xl font-bold">{{ $genre->name }}</h1>
        <p class="mb-6 text-sm text-gray-500">{{ trans_choice(':count titre|:count titres', $genre->titleCount) }}</p>

        <x-catalog::title-grid :cards="$titles->items()" />

        <div class="mt-8">{{ $titles->links() }}</div>
    </div>
</x-catalog::public-layout>
