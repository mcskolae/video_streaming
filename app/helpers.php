<?php

declare(strict_types=1);

use Illuminate\Support\Env;

if (! function_exists('secret')) {
    /**
     * Lit un secret depuis un fichier Docker secret (/run/secrets/<nom en minuscules>),
     * avec repli sur la variable d'environnement correspondante en développement.
     *
     * En production, les secrets ne passent donc jamais par l'environnement du processus.
     */
    function secret(string $name, ?string $default = null): ?string
    {
        $directory = getenv('SECRETS_PATH') ?: '/run/secrets';
        $path = rtrim($directory, '/').'/'.strtolower($name);

        if (is_file($path) && is_readable($path)) {
            $value = trim((string) file_get_contents($path));

            if ($value !== '') {
                return $value;
            }
        }

        $fromEnvironment = Env::get($name);

        return is_string($fromEnvironment) && $fromEnvironment !== '' ? $fromEnvironment : $default;
    }
}
