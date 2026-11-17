<?php

declare(strict_types=1);

namespace HomeFlix\Media\UI\Console;

use HomeFlix\Media\Application\Command\PurgeStaleUploadsHandler;
use Illuminate\Console\Command;

final class PurgeStaleUploadsCommand extends Command
{
    protected $signature = 'media:purge-uploads';

    protected $description = 'Supprime les envois de vidéo abandonnés depuis plus de 24 heures';

    public function handle(PurgeStaleUploadsHandler $handler): int
    {
        $count = $handler->handle();

        $this->components->info(trans_choice(':count envoi abandonné supprimé.|:count envois abandonnés supprimés.', $count));

        return self::SUCCESS;
    }
}
