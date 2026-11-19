<section class="mt-6 rounded-lg bg-indigo-50 p-4" aria-labelledby="reprendre-ici">
    <h2 id="reprendre-ici" class="text-lg font-semibold">{{ __('Reprendre où vous en êtes') }}</h2>

    <p class="mt-1 text-sm text-gray-700">
        @if ($item->isNext)
            {{ __('Épisode suivant : :label', ['label' => $item->episodeLabel]) }}
        @elseif ($item->episodeLabel !== null)
            {{ __('Épisode :label', ['label' => $item->episodeLabel]) }} · {{ __('à :time', ['time' => \HomeFlix\Library\UI\View\EpisodeProgressBar::duration($item->positionSeconds)]) }}
        @else
            {{ __('À :time', ['time' => \HomeFlix\Library\UI\View\EpisodeProgressBar::duration($item->positionSeconds)]) }}
        @endif
    </p>

    @unless ($item->isNext)
        <progress max="100" value="{{ $item->percent() }}" aria-label="{{ __('Progression') }}"
                  class="mt-2 block h-1 w-full max-w-xs appearance-none overflow-hidden rounded [&::-webkit-progress-bar]:bg-white [&::-webkit-progress-value]:bg-indigo-600 [&::-moz-progress-bar]:bg-indigo-600"></progress>
    @endunless

    <a href="{{ route('titles.show', $item->slug) }}?lire={{ $item->mediaId }}#lecture"
       class="mt-3 inline-block rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        {{ $item->isNext ? __('Lire la suite') : __('Reprendre') }}
    </a>
</section>
