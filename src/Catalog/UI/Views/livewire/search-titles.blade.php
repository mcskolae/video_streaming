<div>
    <form role="search" wire:submit.prevent class="mb-6">
        <label for="search-term" class="sr-only">{{ __('Titre à rechercher') }}</label>
        <input id="search-term" type="search" wire:model.live.debounce.300ms="term" maxlength="100" autofocus autocomplete="off"
               placeholder="{{ __('Titre d\'un film ou d\'une série…') }}"
               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    </form>

    <div aria-live="polite">
        @if ($normalized === '')
            <p class="text-gray-600">{{ __('Saisissez un titre pour lancer la recherche.') }}</p>
        @elseif ($cards === [])
            <p class="text-gray-600">{{ __('Aucun résultat pour « :term ».', ['term' => $normalized]) }}</p>
        @else
            <p class="mb-4 text-sm text-gray-500">{{ trans_choice(':count résultat|:count résultats', count($cards)) }}</p>
            <x-catalog::title-grid :cards="$cards" />

            @if ($limitReached)
                <p class="mt-6 text-sm text-gray-500">{{ __('Seuls les premiers résultats sont affichés : précisez votre recherche.') }}</p>
            @endif
        @endif
    </div>
</div>
