<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ajoute l'en-tête Content-Security-Policy. Les scripts et styles en ligne ne s'exécutent que
 * s'ils portent le nonce de la requête (Vite et Livewire le lisent via Vite::cspNonce()).
 *
 * `unsafe-eval` reste nécessaire : Alpine évalue les expressions de x-data (menus Breeze). Les scripts
 * injectés dans une page (XSS) restent bloqués, faute de nonce, et aucun script externe n'est permis.
 */
final class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $this->policy($nonce));
        }

        return $response;
    }

    private function policy(string $nonce): string
    {
        $tmdbImages = 'https://image.tmdb.org';
        $fonts = 'https://fonts.bunny.net';
        // La feuille de bunny.net désigne des fichiers de police hébergés sur le CDN de fontsource.
        $fontFiles = 'https://cdn.jsdelivr.net';
        // Serveur de développement Vite : uniquement quand il tourne.
        $dev = Vite::isRunningHot() ? ' '.rtrim((string) file_get_contents(Vite::hotFile())) : '';

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'{$dev}",
            "style-src 'self' 'nonce-{$nonce}' {$fonts}{$dev}",
            "style-src-attr 'unsafe-inline'",
            "img-src 'self' data: {$tmdbImages}",
            "font-src 'self' {$fonts} {$fontFiles}",
            "media-src 'self' blob:",
            "connect-src 'self'{$dev}",
            "worker-src 'self' blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
