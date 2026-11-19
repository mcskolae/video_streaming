<section aria-labelledby="reprendre">
    <h2 id="reprendre" class="mb-4 text-xl font-semibold">{{ __('Reprendre la lecture') }}</h2>

    <ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
        @foreach ($items as $item)
            <li class="min-w-0">
                <a href="{{ route('titles.show', $item->slug) }}?lire={{ $item->mediaId }}#lecture" class="group block focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded-lg">
                    <x-catalog::poster :path="$item->posterPath" size="w342" :alt="__('Affiche de :title', ['title' => $item->name])"
                                       class="aspect-[2/3] w-full rounded-lg shadow group-hover:shadow-md" />
                    <progress max="100" value="{{ $item->percent() }}" aria-label="{{ __('Progression') }}"
                              class="mt-1 block h-1 w-full appearance-none overflow-hidden rounded [&::-webkit-progress-bar]:bg-gray-200 [&::-webkit-progress-value]:bg-indigo-600 [&::-moz-progress-bar]:bg-indigo-600"></progress>
                    <p class="mt-1 truncate text-sm font-medium group-hover:text-indigo-700">{{ $item->name }}</p>
                    @if ($item->episodeLabel !== null)
                        <p class="truncate text-xs text-gray-500">{{ $item->isNext ? __('Épisode suivant : :label', ['label' => $item->episodeLabel]) : __('Épisode :label', ['label' => $item->episodeLabel]) }}</p>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
</section>
