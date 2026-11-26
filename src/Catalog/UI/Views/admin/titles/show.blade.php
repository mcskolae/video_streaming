<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $series->name }}</h2>
            <a href="{{ route('admin.titles.index') }}" class="text-sm text-gray-600 hover:underline">{{ __('Retour au catalogue') }}</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('catalog::admin.titles._flash')

            <div class="flex items-center justify-between rounded-lg bg-white p-4 shadow-sm">
                <p class="text-sm text-gray-700">
                    {{ $series->status->value === 'published' ? __('Série publiée.') : __('Série en brouillon.') }}
                </p>
                @if ($series->status->value === 'published')
                    <form method="POST" action="{{ route('admin.titles.unpublish', $series->id) }}" data-confirm="{{ __('Dépublier ce titre ? Il ne sera plus visible du public.') }}">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-red-700 hover:underline">{{ __('Dépublier') }}</button>
                    </form>
                @elseif ($series->hasPlayableEpisode())
                    <form method="POST" action="{{ route('admin.titles.publish', $series->id) }}">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-indigo-700 hover:underline">{{ __('Publier') }}</button>
                    </form>
                @else
                    <span class="text-sm text-gray-400">{{ __('Envoyez la vidéo d\'au moins un épisode pour pouvoir publier.') }}</span>
                @endif
            </div>

            @forelse ($series->seasons as $season)
                <section class="rounded-lg bg-white shadow-sm" aria-labelledby="season-{{ $season->number }}">
                    <h3 id="season-{{ $season->number }}" class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-900">
                        {{ $season->number === 0 ? __('Épisodes spéciaux') : ($season->name !== '' ? $season->name : __('Saison :n', ['n' => $season->number])) }}
                    </h3>

                    @if ($season->episodes === [])
                        <p class="px-4 py-3 text-sm text-gray-500">{{ __('Aucun épisode renseigné.') }}</p>
                    @else
                        <ul class="divide-y divide-gray-100">
                            @foreach ($season->episodes as $episode)
                                <li class="flex items-center gap-4 px-4 py-2 text-sm">
                                    <span class="w-8 flex-none text-right font-medium text-gray-400">{{ $episode->number }}</span>
                                    <span class="min-w-0 flex-1 truncate text-gray-900">{{ $episode->name !== '' ? $episode->name : __('Épisode :n', ['n' => $episode->number]) }}</span>
                                    @if ($episode->playable)
                                        <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800">{{ __('Prêt') }}</span>
                                    @else
                                        <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700">{{ __('Sans vidéo') }}</span>
                                    @endif
                                    <a href="{{ route('admin.media.show', ['kind' => 'episode', 'target' => $episode->id]) }}" class="font-medium text-indigo-700 hover:underline">{{ __('Vidéo') }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @empty
                <p class="text-gray-600">{{ __('Cette série n\'a aucune saison.') }}</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
