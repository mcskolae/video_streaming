<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Racine de stockage des médias
    |--------------------------------------------------------------------------
    |
    | Les envois partent dans `incoming/`, les flux HLS dans `hls/`. En production, c'est le volume
    | monté sur /srv/media, que Nginx sert en lecture seule sous /hls/.
    */
    'root' => env('MEDIA_ROOT', storage_path('app/media')),

    /*
    | Supprimer le fichier source une fois le flux HLS prêt : sur un petit disque, garder les deux
    | prend vite trop de place. Mettre à false pour conserver les originaux.
    */
    'delete_source_after_transcoding' => (bool) env('MEDIA_DELETE_SOURCE', true),

    'ffmpeg' => env('FFMPEG_BINARY', 'ffmpeg'),
    'ffprobe' => env('FFPROBE_BINARY', 'ffprobe'),

    // Taille maximale d'un fichier envoyé, en mégaoctets (8 Go par défaut : le disque de la VM est limité).
    'max_upload_mb' => (int) env('MEDIA_MAX_UPLOAD_MB', 8192),

    // Durée maximale d'un transcodage (secondes) : 4 heures. Le serveur n'a pas d'encodage matériel.
    'transcode_timeout' => (int) env('MEDIA_TRANSCODE_TIMEOUT', 14400),

];
