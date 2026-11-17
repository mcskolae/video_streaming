<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use HomeFlix\Catalog\Application\Command\ImportMovieFromTmdb;
use HomeFlix\Catalog\Application\Command\ImportMovieFromTmdbHandler;
use HomeFlix\Catalog\Application\Command\PublishTitle;
use HomeFlix\Catalog\Application\Command\PublishTitleHandler;
use HomeFlix\Catalog\Application\Exception\MetadataNotFound;
use HomeFlix\Catalog\Application\Exception\MetadataProviderUnavailable;
use HomeFlix\Catalog\Application\Port\MetadataProvider;
use HomeFlix\Catalog\Domain\Exception\TitleCannotBePublished;
use HomeFlix\Catalog\Domain\Model\TitleType;
use HomeFlix\Identity\Domain\Model\Role;
use HomeFlix\Media\Application\Command\CompleteUpload;
use HomeFlix\Media\Application\Command\CompleteUploadHandler;
use HomeFlix\Media\Application\Command\ReceiveChunk;
use HomeFlix\Media\Application\Command\ReceiveChunkHandler;
use HomeFlix\Media\Application\Command\StartUpload;
use HomeFlix\Media\Application\Command\StartUploadHandler;
use HomeFlix\Media\Application\Exception\InvalidUpload;
use HomeFlix\Media\Application\Upload\UploadRules;
use HomeFlix\Media\Domain\Model\PlayableKind;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Charge le catalogue de démonstration (voir config/demo.php et docs/DEMO.md).
 *
 * Cette commande est dans `app/` et non dans un contexte : elle assemble Identity, Catalog et Media,
 * qui ne se connaissent pas. Elle ne passe que par leurs cas d'usage.
 *
 * Elle peut être relancée : un compte, un film ou une vidéo déjà présents sont conservés, et un film
 * dont la vidéo est prête est publié.
 */
final class LoadDemoCatalogCommand extends Command
{
    protected $signature = 'homeflix:demo
        {--download : Télécharge les vidéos libres manquantes (depuis download.blender.org uniquement)}
        {--videos= : Dossier des vidéos (storage/app/demo par défaut)}
        {--force : Autorise l\'exécution en production}';

    protected $description = 'Charge un catalogue de démonstration : vidéos libres, comptes de démonstration, publication';

    public function handle(
        MetadataProvider $metadata,
        ImportMovieFromTmdbHandler $import,
        StartUploadHandler $start,
        ReceiveChunkHandler $receive,
        CompleteUploadHandler $complete,
        PublishTitleHandler $publish,
    ): int {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->components->error('Environnement de production : ajoutez --force si c\'est voulu.');

            return self::FAILURE;
        }

        $directory = (string) ($this->option('videos') ?: storage_path('app/demo'));
        $failures = 0;

        $this->createAccounts();

        foreach ((array) config('demo.films') as $film) {
            $this->components->info($film['search']);

            try {
                $titleId = $this->importFilm($metadata, $import, $film);
            } catch (MetadataNotFound|MetadataProviderUnavailable) {
                $this->components->error('TMDB est indisponible ou la clé TMDB manque (TMDB_API_KEY).');

                return self::FAILURE;
            }

            if ($titleId === null) {
                $this->components->error('Introuvable sur TMDB.');
                $failures++;

                continue;
            }

            $file = $directory.'/'.$film['file'];

            if (! is_file($file) && $this->option('download') && ! $this->download($film, $file)) {
                $failures++;

                continue;
            }

            if (is_file($file)) {
                $this->upload($start, $receive, $complete, $titleId, $file);
            } else {
                $this->components->warn("Pas de vidéo : placez {$film['file']} dans {$directory} ou ajoutez --download.");
            }

            $this->publishWhenReady($publish, $titleId);
        }

        $this->newLine();
        $this->line('La conversion se fait dans le worker (docker compose up -d worker). Relancez cette commande ensuite : les films dont la vidéo est prête sont publiés.');

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function createAccounts(): void
    {
        $rows = [];

        foreach ((array) config('demo.accounts') as $account) {
            if (User::query()->where('email', $account['email'])->exists()) {
                $rows[] = [$account['email'], 'déjà présent', '—'];

                continue;
            }

            $password = Str::password(20, symbols: false);
            $user = new User(['name' => $account['name'], 'email' => $account['email'], 'password' => $password]);
            $user->role = $account['admin'] ? Role::Admin : Role::User;
            $user->save();

            $rows[] = [$account['email'], $account['admin'] ? 'administrateur' : 'membre', $password];
        }

        $this->table(['Compte de démonstration', 'Rôle', 'Mot de passe (affiché une seule fois)'], $rows);
    }

    /** @param  array<string, mixed>  $film */
    private function importFilm(MetadataProvider $metadata, ImportMovieFromTmdbHandler $import, array $film): ?string
    {
        $results = $metadata->search((string) $film['search'], TitleType::Movie);

        // Le premier résultat de la bonne année, sinon le premier résultat.
        $match = null;

        foreach ($results as $result) {
            if ($result->releaseYear === $film['year']) {
                $match = $result;
                break;
            }
        }

        $match ??= $results[0] ?? null;

        if ($match === null) {
            return null;
        }

        return $import->handle(new ImportMovieFromTmdb($match->tmdbId->value))->titleId->value;
    }

    /** @param  array<string, mixed>  $film */
    private function download(array $film, string $file): bool
    {
        $host = parse_url((string) $film['download'], PHP_URL_HOST);

        // Aucun téléchargement hors de l'hôte des vidéos libres.
        if ($host !== config('demo.download_host') || parse_url((string) $film['download'], PHP_URL_SCHEME) !== 'https') {
            $this->components->error('Adresse de téléchargement refusée.');

            return false;
        }

        @mkdir(dirname($file), 0755, true);
        $this->line("Téléchargement de {$film['download']} …");

        try {
            $response = Http::timeout(300)->withOptions(['sink' => $file])->get((string) $film['download']);
        } catch (Throwable $exception) {
            @unlink($file);
            $this->components->error('Téléchargement impossible : '.$exception->getMessage());

            return false;
        }

        if (! $response->successful() || ! is_file($file) || filesize($file) === 0) {
            @unlink($file);
            $this->components->error('Téléchargement refusé par le serveur (code '.$response->status().').');

            return false;
        }

        return true;
    }

    private function upload(StartUploadHandler $start, ReceiveChunkHandler $receive, CompleteUploadHandler $complete, string $titleId, string $file): void
    {
        $size = (int) filesize($file);
        $chunks = (int) ceil($size / UploadRules::CHUNK_SIZE_BYTES);

        try {
            $session = $start->handle(new StartUpload(PlayableKind::Title, $titleId, basename($file), $size, $chunks));

            $source = fopen($file, 'rb');

            for ($index = 0; $index < $chunks; $index++) {
                $chunk = fopen('php://temp', 'w+b');
                stream_copy_to_stream($source, $chunk, UploadRules::CHUNK_SIZE_BYTES);
                rewind($chunk);
                $receive->handle(new ReceiveChunk($session->uploadId, $index, $chunk));
                fclose($chunk);
            }

            fclose($source);
            $complete->handle(new CompleteUpload($session->uploadId));
            $this->components->info('Vidéo envoyée : conversion en file d\'attente.');
        } catch (InvalidUpload $exception) {
            // « une vidéo existe déjà » n'est pas une erreur : la commande est relançable.
            $this->components->warn($exception->getMessage());
        }
    }

    private function publishWhenReady(PublishTitleHandler $publish, string $titleId): void
    {
        try {
            $publish->handle(new PublishTitle($titleId));
            $this->components->info('Publié.');
        } catch (TitleCannotBePublished) {
            $this->components->warn('Pas encore publié : la vidéo n\'est pas prête.');
        }
    }
}
