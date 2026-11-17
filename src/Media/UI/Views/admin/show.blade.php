@php
    use HomeFlix\Media\Domain\Model\MediaStatus;

    $statusLabels = [
        'uploaded' => __('Reçue'),
        'queued' => __('En attente de conversion'),
        'processing' => __('Conversion en cours'),
        'ready' => __('Prête'),
        'failed' => __('Échec'),
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Vidéo') }} : {{ $overview->label }}</h2>
    </x-slot>

    @vite('resources/js/media-upload.js')

    <div class="py-12">
        <div class="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
            <section class="rounded-lg bg-white p-6 shadow-sm" aria-labelledby="etat-video">
                <h3 id="etat-video" class="mb-3 text-lg font-semibold text-gray-900">{{ __('État de la vidéo') }}</h3>

                @if ($overview->status === null)
                    <p class="text-gray-700">{{ __('Aucune vidéo n\'a encore été envoyée.') }}</p>
                @else
                    <p class="text-gray-700">
                        <span class="font-medium">{{ $statusLabels[$overview->status->value] }}</span>
                        @if ($overview->durationSeconds)
                            · {{ gmdate($overview->durationSeconds >= 3600 ? 'G \h i \m\i\n' : 'i \m\i\n s \s', $overview->durationSeconds) }}
                        @endif
                        @if ($overview->renditionLabels !== [])
                            · {{ implode(', ', $overview->renditionLabels) }}
                        @endif
                    </p>

                    @if ($overview->errorMessage)
                        <p class="mt-3 rounded-md bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $overview->errorMessage }}</p>
                    @endif

                    @if (in_array($overview->status, [MediaStatus::Queued, MediaStatus::Processing], true))
                        <p class="mt-3 text-sm text-gray-600">{{ __('La conversion peut durer plusieurs minutes. Rechargez cette page pour suivre l\'avancement.') }}</p>
                    @endif
                @endif
            </section>

            @if ($overview->canUpload())
                <section class="rounded-lg bg-white p-6 shadow-sm" aria-labelledby="envoi-video">
                    <h3 id="envoi-video" class="mb-3 text-lg font-semibold text-gray-900">
                        {{ $overview->status === null ? __('Envoyer une vidéo') : __('Remplacer la vidéo en échec') }}
                    </h3>

                    <form data-upload-form
                          data-kind="{{ $overview->kind->value }}"
                          data-target="{{ $overview->targetId }}"
                          data-start-url="{{ route('admin.media.uploads.start') }}"
                          data-status-url="{{ str_replace('00000000-0000-7000-8000-000000000000', '__ID__', route('admin.media.uploads.status', '00000000-0000-7000-8000-000000000000')) }}"
                          data-chunk-url="{{ str_replace(['00000000-0000-7000-8000-000000000000', '/chunks/0'], ['__ID__', '/chunks/__INDEX__'], route('admin.media.uploads.chunk', ['upload' => '00000000-0000-7000-8000-000000000000', 'index' => 0])) }}"
                          data-complete-url="{{ str_replace('00000000-0000-7000-8000-000000000000', '__ID__', route('admin.media.uploads.complete', '00000000-0000-7000-8000-000000000000')) }}">
                        <label for="video-file" class="mb-2 block text-sm font-medium text-gray-700">
                            {{ __('Fichier vidéo (mp4, m4v, mov, mkv, webm, avi)') }}
                        </label>
                        <input id="video-file" type="file" accept=".mp4,.m4v,.mov,.mkv,.webm,.avi,video/*"
                               class="block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">

                        <progress data-upload-progress value="0" max="100" hidden class="mt-4 h-3 w-full" aria-label="{{ __('Progression de l\'envoi') }}"></progress>
                        <p data-upload-message class="mt-3 text-sm text-gray-700" role="status" aria-live="polite"></p>

                        <div class="mt-4">
                            <x-primary-button type="submit">{{ __('Envoyer') }}</x-primary-button>
                        </div>
                    </form>

                    <p class="mt-4 text-xs text-gray-500">
                        {{ __('Le fichier est envoyé par morceaux de 5 Mo : en cas de coupure, relancez l\'envoi avec le même fichier, il reprendra où il s\'est arrêté.') }}
                    </p>
                </section>
            @endif

            <p><a href="{{ route('admin.titles.index') }}" class="text-sm text-gray-600 hover:underline">{{ __('Retour au catalogue') }}</a></p>
        </div>
    </div>
</x-app-layout>
