<div>
    <button type="button" wire:click="toggle" wire:loading.attr="disabled" aria-pressed="{{ $isFavorite ? 'true' : 'false' }}"
            class="inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium ring-1 ring-inset focus:outline-none focus:ring-2 focus:ring-indigo-500
                   {{ $isFavorite ? 'bg-indigo-600 text-white ring-indigo-600 hover:bg-indigo-700' : 'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50' }}">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="{{ $isFavorite ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
        </svg>
        {{ $isFavorite ? __('Retirer des favoris') : __('Ajouter aux favoris') }}
    </button>

    @if ($message !== '')
        <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
    @endif
</div>
