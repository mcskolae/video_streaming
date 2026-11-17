<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Suivi des vidéos') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <livewire:media.admin-list />

            <p class="mt-4 text-xs text-gray-500">{{ __('La liste se met à jour toute seule tant qu\'une conversion est en cours.') }}</p>
        </div>
    </div>
</x-app-layout>
