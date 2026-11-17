<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Exception;

use RuntimeException;

/**
 * Le fournisseur de métadonnées ne peut pas répondre : clé absente ou refusée, quota atteint,
 * service indisponible ou réponse inattendue.
 *
 * Les messages ne contiennent jamais d'URL ni de clé : ils peuvent finir dans les journaux.
 */
final class MetadataProviderUnavailable extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('The metadata provider is not configured: the TMDB credential is missing.');
    }

    public static function invalidCredentials(): self
    {
        return new self('The metadata provider refused the credential (invalid or revoked TMDB key).');
    }

    public static function rateLimited(): self
    {
        return new self('The metadata provider rate limit was reached. Try again in a moment.');
    }

    public static function unreachable(): self
    {
        return new self('The metadata provider could not be reached.');
    }

    public static function unexpectedStatus(int $status): self
    {
        return new self(sprintf('The metadata provider answered with an unexpected status (%d).', $status));
    }

    public static function unexpectedResponse(): self
    {
        return new self('The metadata provider sent a response that could not be understood.');
    }
}
