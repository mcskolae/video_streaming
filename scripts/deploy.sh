#!/usr/bin/env bash
# Déploie la dernière version de la branche courante sur le VPS.
set -euo pipefail
cd "$(dirname "$0")/.."

compose="docker compose -f compose.prod.yml --env-file .env.prod"

git pull --ff-only
$compose build
$compose up -d --remove-orphans
$compose exec -T app php artisan migrate --force
# Pas de config:cache : il écrirait les secrets en clair dans bootstrap/cache/config.php.
$compose exec -T app php artisan route:cache
$compose exec -T app php artisan view:cache
$compose exec -T app php artisan event:cache
docker image prune -f
echo "Déploiement terminé."
