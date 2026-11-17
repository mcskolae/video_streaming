<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Domain\Model;

use DateTimeImmutable;
use HomeFlix\Identity\Domain\Exception\CannotRemoveLastAdministrator;
use InvalidArgumentException;

/**
 * Compte utilisateur. L'inscription et la connexion passent par Laravel Breeze (couche UI) ;
 * ce domaine porte les règles de rôle et la création des administrateurs.
 *
 * Le mot de passe n'est connu que sous forme de condensat : le domaine ne voit jamais le clair.
 */
final class User
{
    private function __construct(
        private readonly UserId $id,
        private string $name,
        private readonly Email $email,
        private readonly string $passwordHash,
        private Role $role,
        private ?DateTimeImmutable $suspendedAt = null,
    ) {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 255) {
            throw new InvalidArgumentException('A user name must be 1 to 255 characters long.');
        }

        if ($passwordHash === '') {
            throw new InvalidArgumentException('A user needs a password hash.');
        }

        $this->name = $name;
    }

    public static function registerAdministrator(UserId $id, string $name, Email $email, string $passwordHash): self
    {
        return new self($id, $name, $email, $passwordHash, Role::Admin);
    }

    public static function reconstitute(UserId $id, string $name, Email $email, string $passwordHash, Role $role, ?DateTimeImmutable $suspendedAt = null): self
    {
        return new self($id, $name, $email, $passwordHash, $role, $suspendedAt);
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function role(): Role
    {
        return $this->role;
    }

    public function isAdministrator(): bool
    {
        return $this->role->isAdministrator();
    }

    /** Donne le rôle d'administrateur. Retourne faux si le compte l'était déjà. */
    public function promoteToAdministrator(): bool
    {
        if ($this->isAdministrator()) {
            return false;
        }

        $this->role = Role::Admin;

        return true;
    }

    public function suspendedAt(): ?DateTimeImmutable
    {
        return $this->suspendedAt;
    }

    public function isSuspended(): bool
    {
        return $this->suspendedAt !== null;
    }

    /** Un administrateur suspendu ne peut plus rien faire : il ne compte pas parmi les administrateurs actifs. */
    public function isActiveAdministrator(): bool
    {
        return $this->isAdministrator() && ! $this->isSuspended();
    }

    /**
     * Change le rôle. Retourne faux s'il n'y a rien à changer.
     *
     * @param  int  $activeAdministrators  nombre d'administrateurs actifs, ce compte compris
     *
     * @throws CannotRemoveLastAdministrator si ce compte est le dernier administrateur actif et perd son rôle
     */
    public function changeRole(Role $role, int $activeAdministrators): bool
    {
        if ($this->role === $role) {
            return false;
        }

        if (! $role->isAdministrator()) {
            $this->assertNotLastAdministrator($activeAdministrators);
        }

        $this->role = $role;

        return true;
    }

    /**
     * Suspend le compte. Retourne faux s'il l'était déjà (la date d'origine est conservée).
     *
     * @param  int  $activeAdministrators  nombre d'administrateurs actifs, ce compte compris
     *
     * @throws CannotRemoveLastAdministrator si ce compte est le dernier administrateur actif
     */
    public function suspend(DateTimeImmutable $at, int $activeAdministrators): bool
    {
        if ($this->isSuspended()) {
            return false;
        }

        $this->assertNotLastAdministrator($activeAdministrators);
        $this->suspendedAt = $at;

        return true;
    }

    /** Réactive le compte. Retourne faux s'il n'était pas suspendu. */
    public function reactivate(): bool
    {
        if (! $this->isSuspended()) {
            return false;
        }

        $this->suspendedAt = null;

        return true;
    }

    private function assertNotLastAdministrator(int $activeAdministrators): void
    {
        if ($this->isActiveAdministrator() && $activeAdministrators <= 1) {
            throw CannotRemoveLastAdministrator::create();
        }
    }
}
