<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Upload;

/** Règles d'un envoi : tailles et formats acceptés. */
final readonly class UploadRules
{
    public const CHUNK_SIZE_BYTES = 5 * 1024 * 1024;

    /** @var array<string, list<string>> extension acceptée => types MIME acceptés pour son contenu réel */
    public const FORMATS = [
        'mp4' => ['video/mp4'],
        'm4v' => ['video/mp4', 'video/x-m4v'],
        'mov' => ['video/quicktime', 'video/mp4'],
        'mkv' => ['video/x-matroska', 'video/webm'],
        'webm' => ['video/webm', 'video/x-matroska'],
        'avi' => ['video/x-msvideo', 'video/avi'],
    ];

    public function __construct(public int $maxSizeBytes) {}

    public function chunksFor(int $sizeBytes): int
    {
        return (int) ceil($sizeBytes / self::CHUNK_SIZE_BYTES);
    }

    /** Extension acceptée d'après le nom de fichier annoncé, ou null. */
    public static function extensionOf(string $filename): ?string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return array_key_exists($extension, self::FORMATS) ? $extension : null;
    }

    public function acceptsMime(string $extension, string $mime): bool
    {
        return in_array(strtolower($mime), self::FORMATS[$extension] ?? [], true);
    }
}
