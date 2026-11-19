<div class="mt-2 max-w-xs" data-episode-progress>
    <progress max="100" value="{{ $progress->percent() }}" aria-label="{{ __('Progression') }}"
              class="block h-1 w-full appearance-none overflow-hidden rounded [&::-webkit-progress-bar]:bg-gray-200 [&::-webkit-progress-value]:bg-indigo-600 [&::-moz-progress-bar]:bg-indigo-600"></progress>
    <p class="mt-1 text-xs text-gray-500">
        @if ($progress->completed)
            {{ __('Vu') }}
        @else
            {{ __(':seen sur :total', ['seen' => \HomeFlix\Library\UI\View\EpisodeProgressBar::duration($progress->positionSeconds), 'total' => \HomeFlix\Library\UI\View\EpisodeProgressBar::duration($progress->durationSeconds)]) }}
        @endif
    </p>
</div>
