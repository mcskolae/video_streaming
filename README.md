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

Le site répond sur http://localhost:8080.

## Administration du catalogue

Réservée aux administrateurs (`/admin/titles`, lien « Catalogue » dans la navigation).

1. **Importer un film** : recherche sur TMDB, aperçu (affiche, synopsis, genres, durée), puis import. Le film arrive en **brouillon**. Importer deux fois le même film met sa fiche à jour sans créer de doublon.
2. **Publier** : possible seulement quand un média est prêt pour le titre. Tant que l'envoi et le transcodage des vidéos ne sont pas faits (cartes M2 et M3), aucun titre n'est publiable : le bouton affiche « Publication impossible ».
3. **Dépublier** : repasse le titre en brouillon.

L'import demande la clé TMDB (`secrets/tmdb_api_key`, voir `docs/DEPLOY.md`).

## Qualité

```sh
docker compose exec app vendor/bin/pint --test
docker compose exec app vendor/bin/phpstan analyse --memory-limit=512M
docker compose exec app vendor/bin/pest
```

## Workflow Git

Voir [`CONTRIBUTING.md`](CONTRIBUTING.md).

## Déploiement

Voir [`docs/DEPLOY.md`](docs/DEPLOY.md).
