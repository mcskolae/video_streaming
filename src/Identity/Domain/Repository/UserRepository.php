<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Domain\Repository;

use Closure;
use HomeFlix\Identity\Domain\Model\Email;
use HomeFlix\Identity\Domain\Model\User;
use HomeFlix\Identity\Domain\Model\UserId;

interface UserRepository
{
    public function findByEmail(Email $email): ?User;

    /** Crée ou met à jour le compte. */
    public function save(User $user): void;

    /**
     * Applique un changement à un compte de façon atomique : les administrateurs actifs sont verrouillés,
     * puis `$change(User $user, int $activeAdministrators)` s'exécute et le compte est enregistré.
     * Deux changements simultanés ne peuvent donc pas, ensemble, retirer le dernier administrateur.
     * Si `$change` lève une exception, rien n'est enregistré.
     *
     * Un compte inconnu lève `HomeFlix\Identity\Application\Exception\UserNotFound`.
     *
     * @param  Closure(User, int): mixed  $change
     */
    public function change(UserId $id, Closure $change): void;
}
