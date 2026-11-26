@php($posterBase = config('services.tmdb.image_base_url').'/w92')

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Catalogue') }}</h2>
            <div class="flex gap-2">
                @foreach ([['movie', __('Importer un film')], ['series', __('Importer une série')]] as [$importType, $label])
                    <a href="{{ route('admin.titles.search', ['type' => $importType]) }}"
                       class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @include('catalog::admin.titles._flash')

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @if ($titles->isEmpty())
                    <p class="p-6 text-gray-600">{{ __('Le catalogue est vide. Importez un film ou une série depuis TMDB.') }}</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-3">{{ __('Titre') }}</th>
                                <th class="px-4 py-3">{{ __('Type') }}</th>
                                <th class="px-4 py-3">{{ __('État') }}</th>
                                <th class="px-4 py-3">{{ __('Média') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($titles as $title)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            @if ($title->posterPath)
                                                <img src="{{ $posterBase.$title->posterPath }}" alt="" class="h-14 w-10 rounded object-cover" loading="lazy">
                                            @else
                                                <div class="h-14 w-10 rounded bg-gray-100"></div>
                                            @endif
                                            <div>
                                                <div class="font-medium text-gray-900">{{ $title->name }}</div>
                                                @if ($title->releaseYear)
                                                    <div class="text-gray-500">{{ $title->releaseYear }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ $title->type->value === 'movie' ? __('Film') : __('Série') }}</td>
                                    <td class="px-4 py-3">
                                        @if ($title->status->value === 'published')
                                            <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800">{{ __('Publié') }}</span>
                                        @else
                                            <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700">{{ __('Brouillon') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ $title->playable ? __('Prêt') : __('Aucun') }}</td>
                                    <td class="px-4 py-3 text-right">
                                        @if ($title->type->value === 'movie')
                                            <a href="{{ route('admin.media.show', ['kind' => 'title', 'target' => $title->id]) }}" class="mr-3 text-sm font-medium text-gray-700 hover:underline">{{ __('Vidéo') }}</a>
                                        @else
                                            <a href="{{ route('admin.titles.show', $title->id) }}" class="mr-3 text-sm font-medium text-gray-700 hover:underline">{{ __('Épisodes') }}</a>
                                        @endif
                                        @if ($title->status->value === 'published')
                                            <form method="POST" action="{{ route('admin.titles.unpublish', $title->id) }}" class="inline" data-confirm="{{ __('Dépublier ce titre ? Il ne sera plus visible du public.') }}">
                                                @csrf
                                                <button type="submit" class="text-sm font-medium text-red-700 hover:underline">{{ __('Dépublier') }}</button>
                                            </form>
                                        @elseif ($title->playable)
                                            <form method="POST" action="{{ route('admin.titles.publish', $title->id) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="text-sm font-medium text-indigo-700 hover:underline">{{ __('Publier') }}</button>
                                            </form>
                                        @else
                                            <span class="text-sm text-gray-400" title="{{ __('Un titre ne peut être publié que lorsqu\'un média est prêt.') }}">{{ __('Publication impossible') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="px-4 py-3">{{ $titles->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
