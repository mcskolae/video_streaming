<?php

declare(strict_types=1);

namespace HomeFlix\Identity\UI\Console;

use HomeFlix\Identity\Application\Command\CreateAdministrator;
use HomeFlix\Identity\Application\Command\CreateAdministratorHandler;
use HomeFlix\Identity\Application\Command\CreateAdministratorResult;
use HomeFlix\Identity\Application\Exception\PasswordRequired;
use HomeFlix\Identity\Application\Exception\PasswordTooWeak;
use Illuminate\Console\Command;
use InvalidArgumentException;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Crée le premier administrateur, ou promeut un compte existant.
 * Le mot de passe est saisi de façon masquée : il n'apparaît ni dans l'historique du shell,
 * ni dans les variables d'environnement, ni dans le dépôt.
 */
final class MakeAdministratorCommand extends Command
{
    protected $signature = 'homeflix:make-admin {email : Adresse e-mail du compte}';

    protected $description = 'Crée un administrateur, ou promeut le compte existant portant cette adresse';

    public function handle(CreateAdministratorHandler $handler): int
    {
        $email = (string) $this->argument('email');

        try {
            try {
                $result = $handler->handle(new CreateAdministrator($email));
            } catch (PasswordRequired) {
                $this->components->info("Aucun compte pour {$email} : création.");

                $name = text(label: 'Nom', required: true);
                $plainPassword = password(
                    label: 'Mot de passe (12 caractères minimum)',
                    required: true,
                    validate: fn (string $value) => mb_strlen($value) < CreateAdministratorHandler::MINIMUM_PASSWORD_LENGTH
                        ? 'Au moins '.CreateAdministratorHandler::MINIMUM_PASSWORD_LENGTH.' caractères.'
                        : null,
                );
                $confirmation = password(label: 'Confirmez le mot de passe', required: true);

                if ($confirmation !== $plainPassword) {
                    $this->components->error('Les mots de passe ne correspondent pas.');

                    return self::FAILURE;
                }

                $result = $handler->handle(new CreateAdministrator($email, $name, $plainPassword));
            }
        } catch (PasswordTooWeak|InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        match ($result) {
            CreateAdministratorResult::Created => $this->components->info("Administrateur créé : {$email}."),
            CreateAdministratorResult::Promoted => $this->components->info("Le compte {$email} est maintenant administrateur."),
            CreateAdministratorResult::AlreadyAdministrator => $this->components->warn("{$email} est déjà administrateur : rien à faire."),
        };

        return self::SUCCESS;
    }
}
