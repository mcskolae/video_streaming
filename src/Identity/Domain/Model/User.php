<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Domain\Model;

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

    public static function reconstitute(UserId $id, string $name, Email $email, string $passwordHash, Role $role): self
    {
        return new self($id, $name, $email, $passwordHash, $role);
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
}
