<?php

declare(strict_types=1);

namespace HomeFlix\Media\UI\Livewire;

use HomeFlix\Media\Application\Command\RetryMedia;
use HomeFlix\Media\Application\Command\RetryMediaHandler;
use HomeFlix\Media\Application\Exception\MediaNotFound;
use HomeFlix\Media\Application\Exception\SourceMissing;
use HomeFlix\Media\Application\Query\ListMediaForAdmin;
use HomeFlix\Media\Domain\Exception\InvalidMediaTransition;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Suivi des vidéos : état de chaque conversion, relance d'un échec.
 *
 * La liste se rafraîchit toutes les 10 secondes (`wire:poll`, de simples requêtes HTTP : pas de WebSocket),
 * et seulement tant qu'une conversion est en cours.
 *
 * Livewire expose les méthodes publiques à toute requête qui lui parvient : chaque point d'entrée vérifie donc
 * lui-même que l'utilisateur est administrateur, sans se reposer sur la protection de la page.
 */
final class MediaList extends Component
{
    public string $notice = '';

    public string $failure = '';

    public function mount(): void
    {
        Gate::authorize('administer');
    }

    public function retry(string $mediaAssetId, RetryMediaHandler $handler): void
    {
        Gate::authorize('administer');

        $this->notice = '';
        $this->failure = '';

        try {
            $handler->handle(new RetryMedia($mediaAssetId));
            $this->notice = 'La vidéo est remise en file de conversion.';
        } catch (MediaNotFound|SourceMissing $exception) {
            $this->failure = $exception->getMessage();
        } catch (InvalidMediaTransition|InvalidArgumentException) {
            $this->failure = 'Seule une vidéo en échec peut être relancée.';
        }
    }

    public function render(ListMediaForAdmin $query): View
    {
        Gate::authorize('administer');

        $rows = $query->handle();

        return view('media::livewire.media-list', [
            'rows' => $rows,
            'hasActive' => array_any($rows, fn ($row) => $row->isActive()),
        ]);
    }
}
