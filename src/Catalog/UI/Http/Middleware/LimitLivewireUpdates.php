<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\UI\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limite les requêtes de mise à jour de Livewire (une par frappe dans la recherche) par adresse IP.
 *
 * Livewire expose plusieurs routes de mise à jour (`livewire/update` et `livewire-<hash>/update`) :
 * limiter une seule laisserait l'autre ouverte. Ce middleware s'applique donc par chemin, à toutes.
 */
final readonly class LimitLivewireUpdates
{
    public const MAX_PER_MINUTE = 180;

    public function __construct(private ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST') || ! $request->is('livewire*/update')) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, self::MAX_PER_MINUTE, 1, 'livewire-update');
    }
}
