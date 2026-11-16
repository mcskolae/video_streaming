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

## Qualité

```sh
docker compose exec app vendor/bin/pint --test
docker compose exec app vendor/bin/phpstan analyse --memory-limit=512M
docker compose exec app vendor/bin/pest
```

## Workflow Git

Voir [`CONTRIBUTING.md`](CONTRIBUTING.md).
