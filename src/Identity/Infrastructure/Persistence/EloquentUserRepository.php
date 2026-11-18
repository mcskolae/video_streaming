<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Infrastructure\Persistence;

use App\Models\User as UserRecord;
use Closure;
use DateTimeImmutable;
use HomeFlix\Identity\Application\Exception\UserNotFound;
use HomeFlix\Identity\Domain\Model\Email;
use HomeFlix\Identity\Domain\Model\User;
use HomeFlix\Identity\Domain\Model\UserId;
use HomeFlix\Identity\Domain\Repository\UserRepository;
use Illuminate\Support\Facades\DB;

/**
 * Adaptateur Eloquent. Le modèle `App\Models\User` est celui de Laravel (authentification) :
 * il sert ici de ligne de table, le domaine ne le voit jamais.
 */
final class EloquentUserRepository implements UserRepository
{
    public function findByEmail(Email $email): ?User
    {
        $record = UserRecord::query()->where('email', $email->value)->first();

        return $record === null ? null : $this->toDomain($record);
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
            'suspended_at' => $user->suspendedAt(),
        ])->save();
    }

    public function change(UserId $id, Closure $change): void
    {
        DB::transaction(function () use ($id, $change): void {
            // Les administrateurs actifs sont verrouillés dans un ordre fixe (par id) : deux changements
            // simultanés s'attendent au lieu de retirer ensemble le dernier administrateur.
            $administrators = UserRecord::query()
                ->where('role', 'admin')
                ->whereNull('suspended_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');

            $record = UserRecord::query()->whereKey($id->value)->lockForUpdate()->first() ?? throw UserNotFound::withId($id->value);
            $user = $this->toDomain($record);

            $change($user, $administrators->count());

            $this->save($user);
        });
    }

    private function toDomain(UserRecord $record): User
    {
        return User::reconstitute(
            UserId::fromString($record->id),
            $record->name,
            Email::fromString($record->email),
            $record->password,
            $record->role,
            $record->suspended_at === null ? null : DateTimeImmutable::createFromInterface($record->suspended_at),
        );
    }
}
