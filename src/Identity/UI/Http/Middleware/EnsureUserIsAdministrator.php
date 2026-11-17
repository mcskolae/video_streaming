<?php

declare(strict_types=1);

namespace HomeFlix\Identity\UI\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse (403) tout utilisateur qui n'est pas administrateur.
 * À placer après le middleware `auth` : un visiteur est d'abord redirigé vers la connexion.
 */
final class EnsureUserIsAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Gate::allows('administer'), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
