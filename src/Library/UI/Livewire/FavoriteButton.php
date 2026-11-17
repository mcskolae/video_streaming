<?php

declare(strict_types=1);

namespace HomeFlix\Library\UI\Livewire;

use HomeFlix\Library\Application\Command\AddFavorite;
use HomeFlix\Library\Application\Command\AddFavoriteHandler;
use HomeFlix\Library\Application\Command\RemoveFavorite;
use HomeFlix\Library\Application\Command\RemoveFavoriteHandler;
use HomeFlix\Library\Application\Exception\TitleNotAvailable;
use HomeFlix\Library\Application\Query\IsFavoriteHandler;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Bouton « Ajouter aux favoris » d'une fiche. Réservé aux membres connectés.
 *
 * Livewire expose les méthodes publiques à toute requête qui lui parvient : chaque point d'entrée vérifie
 * lui-même qu'un membre est connecté, et le membre est toujours celui de la session, jamais une valeur envoyée.
 */
final class FavoriteButton extends Component
{
    public string $titleId = '';

    public bool $isFavorite = false;

    public string $message = '';

    public function mount(string $titleId, IsFavoriteHandler $query): void
    {
        $this->titleId = $titleId;
        $this->isFavorite = $this->safely(fn (string $member) => $query->handle($member, $titleId), false);
    }

    public function toggle(AddFavoriteHandler $add, RemoveFavoriteHandler $remove): void
    {
        $member = $this->memberId();
        $this->message = '';

        try {
            if ($this->isFavorite) {
                $remove->handle(new RemoveFavorite($member, $this->titleId));
                $this->isFavorite = false;
            } else {
                $add->handle(new AddFavorite($member, $this->titleId));
                $this->isFavorite = true;
            }
        } catch (TitleNotAvailable|InvalidArgumentException) {
            $this->message = 'Ce titre n\'est plus disponible.';
        }
    }

    public function render(): View
    {
        return view('library::livewire.favorite-button');
    }

    private function memberId(): string
    {
        $id = auth()->id();

        abort_unless(is_string($id), 403);

        return $id;
    }

    /**
     * @template T
     *
     * @param  callable(string): T  $callback
     * @param  T  $default
     * @return T
     */
    private function safely(callable $callback, mixed $default): mixed
    {
        $id = auth()->id();

        if (! is_string($id)) {
            return $default;
        }

        try {
            return $callback($id);
        } catch (InvalidArgumentException) {
            return $default;
        }
    }
}
