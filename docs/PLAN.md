# Plan du projet annuel : HomeFlix (nom provisoire)

Site de streaming vidéo pour homelab. Laravel, PostgreSQL, HLS, DDD et architecture hexagonale, Docker, déploiement sur Oracle Cloud (Ampere ARM).
Date de rédaction : 16/11/2026. Durée disponible : 3 semaines, en solo.

## 1. Périmètre

### 1.1 MVP (obligatoire)

Identité et accès
- Inscription, connexion, déconnexion (Breeze).
- Trois rôles : visiteur (sans compte), utilisateur, admin.
- L'admin gère les utilisateurs (liste, changement de rôle, suspension).

Catalogue
- Un titre est un film ou une série. Une série a des saisons et des épisodes.
- L'admin importe les métadonnées depuis TMDB (recherche, puis import par identifiant TMDB), puis publie ou dépublie.
- Navigation publique : accueil, liste par genre, recherche, fiche d'un titre.

Média
- L'admin uploade un fichier vidéo par l'interface web (envoi par morceaux).
- Un worker transcode en HLS (ffmpeg). L'admin voit le statut : en attente, en cours, prêt, échec.
- Le lecteur (hls.js) lit un titre prêt. Le visiteur sans compte peut regarder.

Bibliothèque personnelle (utilisateur connecté)
- Favoris (ajout et retrait).
- Reprise de lecture et liste « en cours » (position sauvegardée toutes les 10 s).
- Un seul profil par compte.

### 1.2 Hors périmètre

Notes, playlists, commentaires, sous-titres, multi-audio, temps réel (WebSocket interdit), maquettes (point sacrifié), bonus.

### 1.3 Bonus si le temps le permet

Ordre conseillé : observabilité légère (Sentry, ou Prometheus et Grafana), puis OAuth Google ou GitHub, puis SEO, puis Ansible. Pas de clusterisation sur un seul VPS.

### 1.4 Ordre de coupe si le retard s'accumule

1. Séries (garder les films seuls). Le modèle de données les prévoit déjà.
2. Gestion des utilisateurs côté admin (garder la liste simple).
3. Résolution 1080p (garder 720p seul).
Ne jamais couper : déploiement, sécurité, tests du domaine, Docker.

## 2. Contextes bornés (DDD)

| Contexte | Responsabilité | Agrégats | Langage omniprésent |
|---|---|---|---|
| Catalog | Décrire ce qu'on peut regarder | `Title` (racine, contient `Season` puis `Episode`), `Genre` | titre, saison, épisode, publier, dépublier |
| Media | Recevoir, transcoder et stocker les vidéos | `MediaAsset` (contient `Rendition`) | asset, upload, transcodage, rendition, prêt |
| Library | Ce que fait un utilisateur avec le catalogue | `Favorite`, `WatchProgress` | favori, progression, reprise, terminé |
| Identity | Qui est l'utilisateur et ce qu'il peut faire | `User` (rôle) | compte, rôle, suspension |
| Shared | Noyau partagé minimal | `Uuid`, `DomainEvent`, `Clock` | n/a |

Règles métier à tester dans le domaine :
- Un titre ne peut être publié que s'il a au moins un média prêt (film) ou un épisode prêt (série).
- Un `MediaAsset` suit des transitions strictes : `uploaded`, `queued`, `processing`, `ready` ou `failed`. Un `failed` peut être relancé. Un `ready` ne repasse pas en `processing`.
- Une progression est `completed` quand la position dépasse 90 % de la durée. Une position ne peut pas dépasser la durée.
- Un favori est unique par couple (utilisateur, titre). L'ajout est idempotent.
- Un admin ne peut pas se retirer lui-même le rôle admin s'il est le dernier admin.

Communication entre contextes par événements de domaine, jamais par import direct :
- `MediaAssetReady` (Media) vers Catalog : marque le film ou l'épisode comme lisible.
- `MediaAssetFailed` (Media) vers Catalog : retire l'état lisible si besoin.
- `TitleUnpublished` (Catalog) vers Library : les favoris restent mais le titre est masqué dans les listes.

Les références entre contextes se font par identifiant (`titleId`, `episodeId`, `userId`), jamais par objet.

## 3. Architecture hexagonale sous Laravel

Principe : le domaine et l'application ne dépendent d'aucune classe `Illuminate\`. Laravel vit dans l'infrastructure et l'UI.

```
app/                    Colle Laravel minimale (providers, console, handler d'exceptions)
bootstrap/  config/  routes/  database/migrations/  resources/  (Laravel standard)
src/
  Shared/
    Domain/             Uuid, DomainEvent, AggregateRoot, Clock (interface)
    Application/        EventDispatcher (port)
    Infrastructure/     LaravelEventDispatcher, SystemClock
  Catalog/
    Domain/
      Model/            Title, TitleId, TitleType, Season, Episode, Genre, Synopsis
      Event/            TitlePublished, TitleUnpublished
      Repository/       TitleRepository (port)
      Exception/
    Application/
      Command/          ImportTitleFromTmdb, PublishTitle, UnpublishTitle (chaque commande + son handler)
      Query/            ListTitles, SearchTitles, GetTitleDetails
      Port/             MetadataProvider (port sortant vers TMDB)
      Listener/         MarkPlayableOnMediaReady
    Infrastructure/
      Persistence/      Modèles Eloquent, EloquentTitleRepository, mappers entité <-> modèle
      Tmdb/             TmdbMetadataProvider (HTTP client, cache)
    UI/
      Http/             Controllers, FormRequests, Resources
      Livewire/         Composants (recherche, bouton favori)
      Views/            Blade du contexte
      routes.php
  Media/
    Domain/             MediaAsset, MediaStatus, Rendition, Duration, PlayableRef
    Application/
      Command/          RegisterUpload, StartTranscoding, MarkReady, MarkFailed, RetryTranscoding
      Port/             VideoTranscoder, MediaStorage, UploadStore, MediaProbe
    Infrastructure/     FfmpegTranscoder, FfprobeMediaProbe, LocalMediaStorage, ChunkedUploadStore, TranscodeMediaJob
    UI/
  Library/
    Domain/             Favorite, WatchProgress
    Application/        AddFavorite, RemoveFavorite, SaveProgress, ListContinueWatching, ListFavorites
    Infrastructure/
    UI/
  Identity/
    Domain/             UserId, Role, Email
    Application/        ChangeUserRole, SuspendUser, ListUsers
    Infrastructure/     Modèle Eloquent User (Authenticatable), adaptateur Breeze
    UI/
tests/
  Unit/                 Domaine pur, sans framework ni base
  Application/          Handlers avec dépôts en mémoire
  Integration/          Dépôts Eloquent, TMDB (HTTP fake), ffmpeg sur un petit fichier
  Feature/              Parcours HTTP complets
  Arch/                 Tests d'architecture (Pest)
```

Règles à faire respecter par des tests d'architecture (Pest `arch()`) :
- `Domain` n'utilise ni `Illuminate`, ni `Eloquent`, ni `Carbon`.
- `Application` ne dépend que de `Domain` et de ses propres ports.
- Aucun contexte n'importe le `Domain` d'un autre contexte (sauf `Shared`).

Choix pragmatiques à justifier à l'oral :
- Modèles Eloquent séparés des entités du domaine, avec mappers. Plus verbeux, mais le domaine reste testable sans base.
- Commandes et requêtes (CQS) appelées directement par les contrôleurs via les handlers injectés. Pas de bus de commandes : l'abstraction n'apporterait rien ici.
- Les requêtes de lecture (listes, recherche) peuvent lire directement en SQL. On ne charge pas les agrégats pour afficher une liste.

Outils de qualité : Pest, Larastan (niveau 6 ou plus), Laravel Pint, Conventional Commits.

## 4. Modèle de données (PostgreSQL)

Clés primaires : UUID v7. Dates en `timestamptz`. Contraintes d'intégrité portées par la base, pas seulement par le code.

```
users
  id uuid PK, name, email UNIQUE, password, role (enum user|admin) DEFAULT user,
  suspended_at timestamptz NULL, email_verified_at, remember_token, timestamps

genres
  id uuid PK, tmdb_id int UNIQUE, name

titles
  id uuid PK, type (enum movie|series), tmdb_id int, slug UNIQUE,
  title, original_title, overview, release_year smallint, runtime_minutes smallint NULL,
  poster_path, backdrop_path,
  status (enum draft|published) DEFAULT draft, published_at NULL,
  is_playable bool DEFAULT false   -- film : média prêt ; toujours faux pour une série
  timestamps
  UNIQUE (type, tmdb_id)
  INDEX (status, type)  +  index GIN pg_trgm sur title pour la recherche
  CHECK : année entre 1870 et 2100, durée entre 1 et 10000 minutes,
          (status = 'published') = (published_at IS NOT NULL),
          un film publié est lisible, une série n'a jamais is_playable = true

title_genre
  title_id FK, genre_id FK, PK (title_id, genre_id)

seasons
  id uuid PK, title_id FK ON DELETE CASCADE, number smallint, name, overview
  UNIQUE (title_id, number)  +  CHECK (number BETWEEN 0 AND 999)   -- 0 = épisodes spéciaux

episodes
  id uuid PK, season_id FK ON DELETE CASCADE, number smallint, name, overview,
  runtime_minutes smallint NULL, still_path, is_playable bool DEFAULT false
  UNIQUE (season_id, number)  +  CHECK (number >= 1)

media_assets
  id uuid PK,
  title_id uuid NULL FK  (film)       UNIQUE
  episode_id uuid NULL FK (épisode)   UNIQUE
  CHECK ((title_id IS NULL) <> (episode_id IS NULL))   -- exactement un des deux
  source_path, status (enum uploaded|queued|processing|ready|failed),
  duration_seconds int NULL, error_message NULL, hls_master_path NULL, timestamps

renditions
  id uuid PK, media_asset_id FK ON DELETE CASCADE, label (720p|1080p),
  width, height, video_bitrate_kbps, playlist_path
  UNIQUE (media_asset_id, label)

favorites
  user_id FK, title_id FK, created_at, PK (user_id, title_id)

watch_progress
  id uuid PK, user_id FK, media_asset_id FK, position_seconds int, duration_seconds int,
  completed bool DEFAULT false, updated_at
  UNIQUE (user_id, media_asset_id)  +  CHECK (position_seconds <= duration_seconds)
  INDEX (user_id, updated_at DESC)   -- liste "en cours"

+ tables Laravel : sessions, jobs, failed_jobs, cache, password_reset_tokens
```

Justifications pour la soutenance :
- `media_assets` avec deux clés étrangères nullables et un `CHECK` plutôt qu'une relation polymorphique : l'intégrité référentielle est garantie par la base, ce qu'un `morphTo` ne permet pas.
- `watch_progress` référence l'asset et non le titre : une série a une progression par épisode. « En cours » se calcule par dernier `updated_at` non terminé.
- `renditions` séparée : ajouter une qualité ne change pas le schéma.
- `pg_trgm` : recherche tolérante aux fautes de frappe, sans moteur externe.
- UUID : pas d'énumération d'identifiants dans les URLs publiques.

## 5. Pipeline vidéo HLS

1. Upload par morceaux de 5 Mo (JS natif, endpoint `PATCH`), stockés dans `media/incoming/{assetId}/`. Reprise possible après coupure.
2. À la fin de l'upload : validation (type MIME réel via `finfo`, extension, taille maximale) puis `ffprobe` pour vérifier que c'est une vidéo et lire la durée.
3. `MediaAsset` passe à `queued` et un `TranscodeMediaJob` est mis en file (Redis).
4. Le worker lance ffmpeg : H.264 (`libx264`, preset `veryfast`, CRF 23) et AAC, segments de 6 s, une playlist par qualité et un `master.m3u8`. Sortie dans `media/hls/{assetId}/`.
5. Succès : statut `ready`, durée enregistrée, source supprimée (configurable), événement `MediaAssetReady`. Échec : statut `failed` avec message, relance possible depuis l'admin.
6. Nginx sert `media/hls/` en statique, avec le bon `Content-Type` et du cache pour les segments.

Contraintes Ampere ARM : pas d'encodage matériel, donc tout est logiciel sur CPU. Un film de 2 h peut prendre plusieurs heures. Limite à 1 job à la fois (`--max-jobs`, `nice`). Pour développer et démontrer, utiliser des vidéos courtes (Big Buck Bunny, 10 min).
Toutes les images Docker doivent exister en `linux/arm64`.

## 6. Docker et déploiement

Services (`compose.yml` pour le dev, `compose.prod.yml` pour la production) :

| Service | Image | Rôle |
|---|---|---|
| `nginx` | nginx (arm64) | Reverse proxy, TLS, statique, HLS, seul exposé (80 et 443) |
| `app` | Image maison PHP-FPM (multi-stage : Composer, Vite, runtime) | Laravel |
| `worker` | Même image, commande `php artisan queue:work` + ffmpeg | Transcodage |
| `scheduler` | Même image, `schedule:work` | Tâches planifiées (nettoyage des uploads orphelins) |
| `postgres` | postgres (arm64) | Base, volume nommé, non exposée |
| `redis` | redis (arm64) | File, cache, sessions, non exposé |
| `certbot` | certbot | Certificat Let's Encrypt, renouvellement automatique |

Stockage : volume bloc Oracle de 100 à 150 Go monté sur l'hôte (`/srv/media`) et monté en bind dans `app`, `worker` et `nginx`. Les sauvegardes de la base (`pg_dump` quotidien) vont sur le volume du disque système.

Réseau Oracle : liste de sécurité qui n'ouvre que 22 (limité à ton IP si possible), 80 et 443. Pare-feu `ufw` en plus.

Secrets (exigence du sujet : aucun secret dans les variables d'environnement ni dans un fichier de config versionné) :
- Fichiers `secrets/*.txt` hors dépôt (`.gitignore`), montés via Docker secrets dans `/run/secrets/`.
- Postgres utilise `POSTGRES_PASSWORD_FILE`. Laravel lit `DB_PASSWORD`, `APP_KEY` et `TMDB_API_KEY` depuis les fichiers (petit helper dans `config/`).
- Le dépôt ne contient que `.env.example` sans valeur sensible.

Intégration continue (GitHub Actions) : Pint, Larastan, Pest, construction de l'image arm64. Le déploiement initial se fait à la main (`git pull` puis `docker compose -f compose.prod.yml up -d --build`). Un script `deploy.sh` suffit pour l'instant.

## 7. Sécurité (1 point, à ne pas rater)

- XSRF : middleware CSRF Laravel, `@csrf` partout, cookies `SameSite=Lax`, `Secure`, `HttpOnly`.
- Injection SQL : Eloquent et query builder avec paramètres liés uniquement. Aucun `DB::raw` avec une entrée utilisateur.
- XSS : `{{ }}` partout dans Blade. Aucun `{!! !!}` sur du contenu TMDB ou utilisateur. En-tête `Content-Security-Policy` strict.
- Autorisation : policies et gates sur toutes les routes admin, testées en Feature.
- Authentification : limitation de débit sur la connexion et l'inscription, hash `bcrypt` ou `argon2id`, session régénérée à la connexion.
- Uploads : validation MIME réelle, noms de fichiers générés (jamais le nom d'origine), stockage hors de la racine web, taille limitée côté Nginx et côté PHP.
- TMDB : hôte fixe (pas de SSRF), clé via secret, réponses mises en cache.
- Transport : HTTPS forcé, HSTS, en-têtes de sécurité (`X-Content-Type-Options`, `Referrer-Policy`).
- Conteneurs : utilisateur non root dans l'image `app`, aucun port interne publié, pas de `APP_DEBUG` en production.
- Mentions TMDB : afficher l'attribution exigée par leurs conditions d'utilisation dans le pied de page.

## 8. Gestion de projet

- GitHub Projects en Kanban : colonnes Backlog, Ready, In progress, Review, Done. Limite de 2 cartes en cours.
- Gitflow : `main` (production), `develop`, `feature/*`, `release/*`, `hotfix/*`. Même en solo, tout passe par une pull request vers `develop`, avec la CI verte.
- Commits en Conventional Commits.
- Chaque carte a un critère d'acceptation. Les cartes sont créées depuis le backlog ci-dessous.

## 9. Backlog initial

Taille : S (moins d'une demi-journée), M (environ une journée), L (plus d'une journée).

Fondations
- F1 (S) Dépôt GitHub, tableau Kanban, Gitflow, modèle de PR.
- F2 (M) Docker de dev (app, nginx, postgres, redis), Laravel installé, Pest, Pint, Larastan.
- F3 (S) Squelette `src/` par contexte, tests d'architecture, CI GitHub Actions.
- F4 (M) VPS Oracle : volume bloc, Docker, sous-domaine, Nginx, Let's Encrypt, premier déploiement d'une page vide.
- F5 (S) Rédiger le document métier de 2 pages (contexte, problème, solution, acteurs).

Identity
- I1 (M) Breeze, modèle `User`, rôle, middleware et policies admin.
- I2 (M) Admin : liste des utilisateurs, changement de rôle, suspension, règle du dernier admin.

Catalog
- C1 (M) Domaine `Title`, `Season`, `Episode`, dépôt, migrations, tests unitaires.
- C2 (M) Port `MetadataProvider`, adaptateur TMDB, cache, tests avec HTTP fake.
- C3 (M) Admin : recherche TMDB, import film, publication et dépublication.
- C4 (M) Import des séries avec saisons et épisodes.
- C5 (M) Pages publiques : accueil, par genre, recherche (pg_trgm), fiche titre.

Media
- M1 (M) Domaine `MediaAsset` et machine d'états, tests unitaires.
- M2 (L) Upload par morceaux, validation, `ffprobe`.
- M3 (L) Transcodage ffmpeg en HLS via le job, statuts, relance.
- M4 (M) Nginx sert le HLS, lecteur hls.js sur la fiche titre.
- M5 (S) Écran admin : statut des médias et erreurs.

Library
- L1 (S) Favoris : domaine, handler, bouton Livewire, page « Mes favoris ».
- L2 (M) Progression : endpoint de sauvegarde, reprise automatique, liste « en cours ».

Finition
- Q1 (M) Passe sécurité complète (section 7) avec tests.
- Q2 (M) Couverture de tests : domaine à plus de 90 %, parcours Feature principaux.
- Q3 (S) Données de démo (seeders et quelques films libres).
- Q4 (S) README, schéma d'architecture, schéma de base.
- Q5 (M) Préparation de la démonstration et de la défense du modèle de données.

## 10. Planning sur 3 semaines

Les dates supposent un démarrage aujourd'hui. Ajuste selon la date de soutenance, qui reste à fixer.

Semaine 1 (16 au 22 novembre) : fondations et catalogue
- Jours 1 et 2 : F1, F2, F3, F5.
- Jour 3 : F4. Déployer tôt une page vide en HTTPS, c'est ce qui réduit le risque le plus gros.
- Jour 4 : I1, C1.
- Jours 5 à 7 : C2, C3, C5 (films seulement).
- Fin de semaine : un admin importe un film depuis TMDB et un visiteur le voit sur le site déployé.

Semaine 2 (23 au 29 novembre) : média et bibliothèque
- Jours 1 à 3 : M1, M2, M3.
- Jour 4 : M4, M5. Premier film lisible de bout en bout.
- Jour 5 : L1, L2.
- Jours 6 et 7 : C4 (séries), I2.
- Fin de semaine : toutes les fonctions du MVP sont déployées en production. Cette échéance est exigée par le sujet.

Semaine 3 (30 novembre au 6 décembre) : qualité et soutenance
- Jours 1 à 3 : Q1, Q2.
- Jour 4 : Q3, Q4.
- Jour 5 : Q5, répétition de la démonstration.
- Jours 6 et 7 : tampon, corrections, et bonus seulement si tout est vert.
- Gel du code deux jours avant la soutenance. Ensuite, seulement des correctifs.

## 11. Risques

| Risque | Probabilité | Parade |
|---|---|---|
| Transcodage trop lent sur ARM | Haute | Vidéos de démo courtes, 1 job à la fois, 720p seul si besoin |
| Upload volumineux bloqué par Nginx ou PHP | Moyenne | Envoi par morceaux de 5 Mo, `client_max_body_size` adapté |
| Retard sur le déploiement | Moyenne | F4 dès le jour 3, déploiement à chaque fin de semaine |
| Images Docker absentes en arm64 | Faible | Vérifier chaque image dès F2, tester la construction sur le VPS |
| Trop de temps passé sur la pureté DDD | Moyenne | Arbitrer en faveur du fonctionnel, voir la section 3 pour les compromis déjà décidés |
| Dépassement de coût Oracle (Pay As You Go) | Faible | Rester dans l'Always Free, budget avec alerte à 1 € |
| Sous-domaine gratuit refusé par l'école | Faible | Demander dès cette semaine à l'enseignant |

## 12. Points à vérifier au démarrage

- Dernière version de Laravel et PHP supportée, et version de PHP disponible dans l'image Docker arm64.
- Breeze reste installable et recommandé pour Blade avec cette version de Laravel.
- Le sous-domaine gratuit (par exemple DuckDNS) est accepté comme nom de domaine public.
- Date de soutenance fixée, pour recaler le planning de la semaine 3.
