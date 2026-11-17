<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Infrastructure\Persistence;

use App\Models\User as UserRecord;
use HomeFlix\Identity\Domain\Model\Email;
use HomeFlix\Identity\Domain\Model\User;
use HomeFlix\Identity\Domain\Model\UserId;
use HomeFlix\Identity\Domain\Repository\UserRepository;

/**
 * Adaptateur Eloquent. Le modèle `App\Models\User` est celui de Laravel (authentification) :
 * il sert ici de ligne de table, le domaine ne le voit jamais.
 */
final class EloquentUserRepository implements UserRepository
{
    public function findByEmail(Email $email): ?User
    {
        $record = UserRecord::query()->where('email', $email->value)->first();

        if ($record === null) {
            return null;
        }

        return User::reconstitute(
            UserId::fromString($record->id),
            $record->name,
            Email::fromString($record->email),
            $record->password,
            $record->role,
        );
    }

    public function save(User $user): void
    {
        $record = UserRecord::query()->findOrNew($user->id()->value);

        // forceFill : le rôle et l'identifiant ne sont volontairement pas assignables en masse.
        $record->forceFill([
            'id' => $user->id()->value,
            'name' => $user->name(),
            'email' => $user->email()->value,
            'password' => $user->passwordHash(),
            'role' => $user->role(),
        ])->save();
    }
}
