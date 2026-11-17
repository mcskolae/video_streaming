#!/usr/bin/env bash
# Déploie la dernière version de la branche courante sur le VPS.
set -euo pipefail
cd "$(dirname "$0")/.."

compose="docker compose -f compose.prod.yml --env-file .env.prod"

git pull --ff-only
$compose build
$compose up -d --remove-orphans
# nginx résout l'adresse du conteneur app au démarrage : un app recréé a une nouvelle adresse, nginx doit la relire.
$compose restart nginx
# Le conteneur app vient de démarrer : on attend qu'il accepte les commandes avant de migrer.
for attempt in 1 2 3 4 5 6 7 8 9 10; do
    if $compose exec -T app php artisan --version >/dev/null 2>&1; then
        break
    fi
    sleep 2
done
$compose exec -T app php artisan migrate --force
# Pas de config:cache : il écrirait les secrets en clair dans bootstrap/cache/config.php.
$compose exec -T app php artisan route:cache
$compose exec -T app php artisan view:cache
$compose exec -T app php artisan event:cache
docker image prune -f
echo "Déploiement terminé."
