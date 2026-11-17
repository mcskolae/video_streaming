<?php

declare(strict_types=1);

/*
 * Catalogue de démonstration : films libres de la Blender Foundation (licence Creative Commons Attribution 3.0).
 *
 * Le film complet est long : la démonstration utilise la bande-annonce officielle, courte, rattachée à la fiche
 * du film. La fiche (affiche, synopsis, genres) vient de TMDB. Les sources sont citées dans docs/DEMO.md.
 *
 * Seuls les fichiers de l'hôte `download.blender.org` sont téléchargés.
 */
return [
    'download_host' => 'download.blender.org',

    'films' => [
        [
            'slug' => 'big-buck-bunny',
            'search' => 'Big Buck Bunny',
            'year' => 2008,
            'license' => 'CC BY 3.0 — (c) Blender Foundation | peach.blender.org',
            'source' => 'https://peach.blender.org/',
            'file' => 'big-buck-bunny-trailer.mov',
            'download' => 'https://download.blender.org/peach/trailer/trailer_480p.mov',
        ],
        [
            'slug' => 'sintel',
            'search' => 'Sintel',
            'year' => 2010,
            'license' => 'CC BY 3.0 — (c) Blender Foundation | durian.blender.org',
            'source' => 'https://durian.blender.org/',
            'file' => 'sintel-trailer.mp4',
            'download' => 'https://download.blender.org/durian/trailer/sintel_trailer-480p.mp4',
        ],
    ],

    // Comptes de démonstration : le mot de passe est tiré au hasard à chaque chargement et affiché une seule fois.
    'accounts' => [
        ['name' => 'Démo Administrateur', 'email' => 'demo-admin@homeflix.test', 'admin' => true],
        ['name' => 'Démo Membre', 'email' => 'demo-membre@homeflix.test', 'admin' => false],
    ],
];
