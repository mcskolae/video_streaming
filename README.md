# HomeFlix

Site de streaming vidéo pour homelab (films et séries), en HLS.
Projet annuel ESGI, 3e année Ingénierie du Web.

Stack : Laravel, Blade, Livewire, Alpine, PostgreSQL, Redis, ffmpeg, Docker, Nginx.
Architecture : DDD et hexagonale. Voir [`docs/PLAN.md`](docs/PLAN.md).

## Démarrage en local

```sh
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Le site répond sur http://localhost:8080. Le conteneur `worker` traite les conversions vidéo ; `ffmpeg` est dans l'image.

## Administration du catalogue

Réservée aux administrateurs (`/admin/titles`, lien « Catalogue » dans la navigation).

1. **Importer un film** : recherche sur TMDB, aperçu (affiche, synopsis, genres, durée), puis import. Le film arrive en **brouillon**. Importer deux fois le même film met sa fiche à jour sans créer de doublon.
2. **Publier** : possible seulement quand un média est prêt pour le titre. Tant que l'envoi et le transcodage des vidéos ne sont pas faits (cartes M2 et M3), aucun titre n'est publiable : le bouton affiche « Publication impossible ».
3. **Dépublier** : repasse le titre en brouillon.

L'import demande la clé TMDB (`secrets/tmdb_api_key`, voir `docs/DEPLOY.md`).

## Vidéos : de l'envoi à la lecture

1. L'administrateur ouvre la page « Vidéo » d'un film (`/admin/media/title/<id>`) et envoie un fichier. Le script le découpe en morceaux de 5 Mo (reprise possible après une coupure) ; le serveur contrôle le contenu réel (type MIME, `ffprobe`) et le met en file.
2. Le **worker** (`php artisan queue:work`, un seul conteneur) convertit la vidéo en HLS avec `ffmpeg` : H.264 et AAC, 1080p et 720p selon la source, segments de 6 secondes, `master.m3u8`. Un seul transcodage à la fois : le serveur n'a pas d'encodage matériel, un long film peut prendre plusieurs heures.
3. Quand le flux est prêt, le film devient **publiable** (`MediaAssetReady` rend le titre lisible). L'administrateur le publie depuis le catalogue.
4. Nginx sert `/hls/` ; la fiche publique du titre affiche le lecteur (hls.js, lecture native sur Safari).

Suivi et relance des conversions : `/admin/media`. Les envois abandonnés sont supprimés chaque nuit (`media:purge-uploads`, tâche planifiée par le service `scheduler`).

## Espace membre

Un compte peut mettre des titres en **favoris** (`/favoris`). Les comptes administrateurs se créent avec `php artisan homeflix:make-admin <email>`.

## Architecture

DDD et architecture hexagonale, cinq contextes dans `src/` : `Catalog`, `Media`, `Library`, `Identity` et `Shared`. Chacun a ses couches `Domain` (sans framework), `Application` (cas d'usage et ports), `Infrastructure` (adaptateurs) et `UI`. Les contextes ne se connaissent que par leurs événements de domaine ; des **tests d'architecture** (`tests/Arch`) le vérifient. Voir [`docs/PLAN.md`](docs/PLAN.md).

## Qualité

```sh
docker compose exec app vendor/bin/pint --test
docker compose exec app vendor/bin/phpstan analyse --memory-limit=512M
docker compose exec app vendor/bin/pest
pnpm run test:js        # scripts d'envoi et de lecture, avec le lanceur de tests de Node
```

## Workflow Git

Voir [`CONTRIBUTING.md`](CONTRIBUTING.md).

## Déploiement

Voir [`docs/DEPLOY.md`](docs/DEPLOY.md).
