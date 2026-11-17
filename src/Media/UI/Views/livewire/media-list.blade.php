@php
    $labels = [
        'uploaded' => __('Reçue'),
        'queued' => __('En attente'),
        'processing' => __('Conversion en cours'),
        'ready' => __('Prête'),
        'failed' => __('Échec'),
    ];
    $colors = [
        'uploaded' => 'bg-gray-100 text-gray-700',
        'queued' => 'bg-yellow-100 text-yellow-800',
        'processing' => 'bg-blue-100 text-blue-800',
        'ready' => 'bg-green-100 text-green-800',
        'failed' => 'bg-red-100 text-red-800',
    ];
@endphp

{{-- Interrogation toutes les 10 s, seulement tant qu'une conversion est en cours. --}}
<div @if ($hasActive) wire:poll.10s @endif>
    @if ($notice !== '')
        <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800" role="status">{{ $notice }}</div>
    @endif

    @if ($failure !== '')
        <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $failure }}</div>
    @endif

    <div class="overflow-hidden rounded-lg bg-white shadow-sm">
        @if ($rows === [])
            <p class="p-6 text-gray-600">{{ __('Aucune vidéo envoyée pour le moment.') }}</p>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('Titre') }}</th>
                        <th class="px-4 py-3">{{ __('État') }}</th>
                        <th class="px-4 py-3">{{ __('Durée') }}</th>
                        <th class="px-4 py-3">{{ __('Qualités') }}</th>
                        <th class="px-4 py-3">{{ __('Mise à jour') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($rows as $row)
                        <tr wire:key="media-{{ $row->id }}">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.media.show', ['kind' => $row->kind->value, 'target' => $row->targetId]) }}" class="font-medium text-gray-900 hover:underline">{{ $row->label }}</a>
                                @if ($row->errorMessage)
                                    <p class="mt-1 max-w-md text-xs text-red-700">{{ $row->errorMessage }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-medium {{ $colors[$row->status->value] }}">{{ $labels[$row->status->value] }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $row->durationSeconds ? gmdate($row->durationSeconds >= 3600 ? 'G \h i \m\i\n' : 'i \m\i\n s \s', $row->durationSeconds) : '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $row->renditionLabels === [] ? '—' : implode(', ', $row->renditionLabels) }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ \Illuminate\Support\Carbon::instance($row->updatedAt)->diffForHumans() }}</td>
                            <td class="px-4 py-3 text-right">
                                @if ($row->canRetry())
                                    <button type="button" wire:click="retry('{{ $row->id }}')" wire:loading.attr="disabled"
                                            class="text-sm font-medium text-indigo-700 hover:underline">{{ __('Relancer') }}</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
