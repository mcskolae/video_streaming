@props(['card'])

<a href="{{ route('titles.show', $card->slug) }}"
   class="group block overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200 transition hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
    <x-catalog::poster :path="$card->posterPath" size="w342" :alt="__('Affiche de :title', ['title' => $card->name])" class="aspect-[2/3] w-full" />
    <div class="p-3">
        <h3 class="truncate text-sm font-semibold text-gray-900 group-hover:text-indigo-700">{{ $card->name }}</h3>
        <p class="mt-0.5 text-xs text-gray-500">
            {{ $card->type->value === 'movie' ? __('Film') : __('Série') }}@if ($card->releaseYear) · {{ $card->releaseYear }}@endif
        </p>
    </div>
</a>
