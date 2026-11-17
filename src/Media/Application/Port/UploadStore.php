<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Port;

use HomeFlix\Media\Application\Exception\InvalidUpload;
use HomeFlix\Media\Application\Upload\UploadMeta;

/** Stockage temporaire des morceaux d'un envoi, le temps de les assembler. */
interface UploadStore
{
    public function begin(UploadMeta $meta): void;

    public function meta(string $uploadId): ?UploadMeta;

    /**
     * Enregistre un morceau, en remplaçant celui du même numéro (un renvoi est donc sans danger).
     *
     * Le morceau doit faire exactement la taille attendue : sinon il est refusé et rien n'est conservé.
     * Un morceau déjà enregistré est donc toujours complet, ce qui rend la reprise d'un envoi fiable.
     *
     * @param  resource  $stream
     * @return int taille enregistrée, égale à `$expectedBytes`
     *
     * @throws InvalidUpload si le morceau est plus court ou plus long que prévu
     */
    public function storeChunk(string $uploadId, int $index, $stream, int $expectedBytes): int;

    /** @return list<int> numéros des morceaux déjà reçus, triés */
    public function receivedChunks(string $uploadId): array;

    /**
     * Assemble les morceaux dans l'ordre vers un fichier. Retourne la taille obtenue en octets.
     *
     * @throws InvalidUpload s'il manque un morceau
     */
    public function assemble(string $uploadId, string $destinationAbsolutePath): int;

    public function discard(string $uploadId): void;

    /**
     * Supprime les envois sans activité depuis avant la date donnée (envois abandonnés).
     *
     * @return int nombre d'envois supprimés
     */
    public function purgeInactiveSince(\DateTimeImmutable $before): int;
}
