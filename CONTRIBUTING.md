# Workflow de contribution

## Branches (Gitflow)

| Branche | Rôle |
|---|---|
| `main` | Production. Chaque commit est déployable et porte un tag de version. |
| `develop` | Intégration. Base de toutes les branches de travail. |
| `feature/<carte>-<sujet>` | Une fonctionnalité, créée depuis `develop`. Exemple : `feature/c2-tmdb-adapter`. |
| `release/<version>` | Préparation d'une livraison, créée depuis `develop`, fusionnée dans `main` et `develop`. |
| `hotfix/<sujet>` | Correction urgente en production, créée depuis `main`, fusionnée dans `main` et `develop`. |

## Règles

- Aucun commit direct sur `main` ni sur `develop`.
- Une pull request par branche, vers `develop`. La CI doit être verte avant la fusion.
- Une carte du tableau Kanban par branche. Le numéro de la carte figure dans le nom de la branche.
- Fusion de `feature/*` avec un squash. Fusion de `release/*` et `hotfix/*` avec un commit de fusion, pour garder l'historique des livraisons.

## Commits (Conventional Commits)

Format : `type(portée): résumé à l'impératif`.

Types : `feat`, `fix`, `refactor`, `test`, `docs`, `chore`, `ci`, `build`, `perf`.
Portées : `catalog`, `media`, `library`, `identity`, `shared`, `infra`.

Exemples :
- `feat(catalog): import a movie from TMDB`
- `fix(media): reject uploads with an unknown MIME type`
- `chore(infra): add compose.prod.yml`

## Avant de pousser

```sh
docker compose exec app vendor/bin/pint
docker compose exec app vendor/bin/phpstan analyse --memory-limit=512M
docker compose exec app vendor/bin/pest
```

## Secrets

Ne jamais committer de secret (`.env`, clés d'API, mots de passe). Le dossier `secrets/` est ignoré par Git.
