<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Infrastructure\Tmdb;

use RuntimeException;

/** TMDB a répondu 404. Détail interne : l'adaptateur le convertit en exception applicative. */
final class TmdbResourceNotFound extends RuntimeException {}
