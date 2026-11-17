# HomeFlix

Site de streaming vidéo pour homelab (films et séries), en HLS.
Projet annuel ESGI, 3e année Ingénierie du Web.

Stack : Laravel, Blade, Livewire, Alpine, PostgreSQL, Redis, ffmpeg, Docker, Nginx.
Architecture : DDD et hexagonale.

| Document | Contenu |
|---|---|
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | contextes, couches, pipeline vidéo, choix du front |
| [`docs/BASE-DE-DONNEES.md`](docs/BASE-DE-DONNEES.md) | schéma entité-relation (vérifié par un test), contraintes, choix |
| [`docs/DEPLOY.md`](docs/DEPLOY.md) | déploiement sur le serveur, déploiement continu |
| [`docs/DEMO.md`](docs/DEMO.md) | catalogue de démonstration de films libres |
| [`docs/SOUTENANCE.md`](docs/SOUTENANCE.md) | scénario de démonstration et défense du modèle |
| [`docs/PLAN.md`](docs/PLAN.md) | plan initial du projet |

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

1. **Importer un film ou une série** : recherche sur TMDB (onglets Films et Séries), aperçu, puis import. Le titre arrive en **brouillon** ; une série vient avec toutes ses saisons et ses épisodes. Réimporter met la fiche à jour sans créer de doublon et garde les vidéos déjà envoyées.
2. **Publier** : possible seulement quand un média est prêt (le film, ou au moins un épisode). Sinon le bouton affiche « Publication impossible ». La page d'une série (`/admin/titles/<id>`) liste ses épisodes, chacun avec son lien d'envoi de vidéo.
3. **Dépublier** : repasse le titre en brouillon.

Les comptes se gèrent dans `/admin/users` : liste, changement de rôle, suspension (le dernier administrateur actif ne peut ni perdre son rôle ni être suspendu).

L'import demande la clé TMDB (`secrets/tmdb_api_key`, voir `docs/DEPLOY.md`).

Pour une démonstration sans contenu protégé : `php artisan homeflix:demo --download` charge deux films libres de la Blender Foundation (voir [`docs/DEMO.md`](docs/DEMO.md)).

## Vidéos : de l'envoi à la lecture

1. L'administrateur ouvre la page « Vidéo » d'un film (`/admin/media/title/<id>`) et envoie un fichier. Le script le découpe en morceaux de 5 Mo (reprise possible après une coupure) ; le serveur contrôle le contenu réel (type MIME, `ffprobe`) et le met en file.
2. Le **worker** (`php artisan queue:work`, un seul conteneur) convertit la vidéo en HLS avec `ffmpeg` : H.264 et AAC, 1080p et 720p selon la source, segments de 6 secondes, `master.m3u8`. Un seul transcodage à la fois : le serveur n'a pas d'encodage matériel, un long film peut prendre plusieurs heures.
3. Quand le flux est prêt, le film devient **publiable** (`MediaAssetReady` rend le titre lisible). L'administrateur le publie depuis le catalogue.
4. Nginx sert `/hls/` ; la fiche publique du titre affiche le lecteur (hls.js, lecture native sur Safari).

Suivi et relance des conversions : `/admin/media`. Les envois abandonnés sont supprimés chaque nuit (`media:purge-uploads`, tâche planifiée par le service `scheduler`).

## Espace membre

Un compte peut mettre des titres en **favoris** (`/favoris`) ; la lecture reprend là où elle s'est arrêtée et l'accueil propose « Reprendre la lecture ». Les comptes administrateurs se créent avec `php artisan homeflix:make-admin <email>`.

## Architecture

DDD et architecture hexagonale, cinq contextes dans `src/` : `Catalog`, `Media`, `Library`, `Identity` et `Shared`. Chacun a ses couches `Domain` (sans framework), `Application` (cas d'usage et ports), `Infrastructure` (adaptateurs) et `UI`. Les contextes ne se connaissent que par leurs événements de domaine ; des **tests d'architecture** (`tests/Arch`) le vérifient. Voir [`docs/PLAN.md`](docs/PLAN.md).

## Qualité

```sh
docker compose exec app vendor/bin/pint --test
docker compose exec app vendor/bin/phpstan analyse --memory-limit=512M
docker compose exec app vendor/bin/pest
docker compose exec app php -d pcov.enabled=1 vendor/bin/pest --coverage-clover=coverage.xml
php scripts/check-domain-coverage.php coverage.xml 90   # seuil de la CI : domaine à 90 %
pnpm run test:js        # scripts d'envoi et de lecture, avec le lanceur de tests de Node
```

## Workflow Git

Voir [`CONTRIBUTING.md`](CONTRIBUTING.md).

## Déploiement

Voir [`docs/DEPLOY.md`](docs/DEPLOY.md).
