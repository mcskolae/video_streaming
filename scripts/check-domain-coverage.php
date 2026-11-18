<?php

declare(strict_types=1);

// Échoue si la couverture des dossiers `Domain` passe sous le seuil.
// Usage : php scripts/check-domain-coverage.php coverage.xml [seuil en %]
//
// La couverture est celle des lignes exécutables des fichiers `src/**/Domain/**` (rapport Clover de Pest).

$report = $argv[1] ?? 'coverage.xml';
$threshold = (float) ($argv[2] ?? 90);

if (! is_file($report)) {
    fwrite(STDERR, "Rapport introuvable : {$report}\n");
    exit(2);
}

$xml = simplexml_load_file($report);

if ($xml === false) {
    fwrite(STDERR, "Rapport illisible : {$report}\n");
    exit(2);
}

$total = 0;
$covered = 0;
$uncovered = [];

foreach ($xml->xpath('//file') ?: [] as $file) {
    $name = (string) $file['name'];

    if (! str_contains($name, '/src/') || ! str_contains($name, '/Domain/')) {
        continue;
    }

    foreach ($file->line as $line) {
        if ((string) $line['type'] !== 'stmt') {
            continue;
        }

        $total++;

        if ((int) $line['count'] > 0) {
            $covered++;
        } else {
            $uncovered[] = substr($name, (int) strpos($name, '/src/') + 1).':'.$line['num'];
        }
    }
}

if ($total === 0) {
    fwrite(STDERR, "Aucune ligne de domaine dans le rapport : la couverture n'a pas été mesurée.\n");
    exit(2);
}

$percent = 100 * $covered / $total;
printf("Couverture du domaine : %.2f %% (%d / %d lignes), seuil %.0f %%.\n", $percent, $covered, $total, $threshold);

if ($percent < $threshold) {
    fwrite(STDERR, "Sous le seuil. Lignes non couvertes :\n  ".implode("\n  ", $uncovered)."\n");
    exit(1);
}
