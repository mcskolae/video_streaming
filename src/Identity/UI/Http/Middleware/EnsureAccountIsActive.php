<?php

declare(strict_types=1);

namespace HomeFlix\Identity\UI\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ferme la session d'un compte suspendu dès sa prochaine requête, quel que soit le moyen de connexion
 * (session ou cookie « se souvenir de moi »). Le compte est relu à chaque requête par le garde d'authentification.
 */
final class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->suspended_at !== null) {
            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(Response::HTTP_UNAUTHORIZED, 'Compte suspendu.');
            }

            return redirect()->route('login')->withErrors(['email' => 'Ce compte est suspendu.']);
        }

        return $next($request);
    }
}
