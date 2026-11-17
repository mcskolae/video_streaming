#!/usr/bin/env bash
# Obtient le premier certificat Let's Encrypt.
# Usage : scripts/init-letsencrypt.sh <email>
# Principe : Nginx ne démarre pas sans certificat, donc on en crée un factice,
# on démarre Nginx, puis on le remplace par le vrai.
set -euo pipefail
cd "$(dirname "$0")/.."

EMAIL="${1:?Usage: $0 <email>}"
set -a; . ./.env.prod; set +a
: "${DOMAIN:?DOMAIN manquant dans .env.prod}"

compose="docker compose -f compose.prod.yml --env-file .env.prod"
live="/etc/letsencrypt/live/${DOMAIN}"

echo "1/4 Certificat factice"
$compose run --rm --entrypoint sh certbot -c "
  mkdir -p ${live} &&
  openssl req -x509 -nodes -newkey rsa:2048 -days 1 \
    -keyout ${live}/privkey.pem -out ${live}/fullchain.pem -subj '/CN=localhost'"

echo "2/4 Démarrage de Nginx"
$compose up -d --build nginx

echo "3/4 Suppression du certificat factice puis demande du vrai"
$compose run --rm --entrypoint sh certbot -c "
  rm -rf ${live} /etc/letsencrypt/archive/${DOMAIN} /etc/letsencrypt/renewal/${DOMAIN}.conf"
$compose run --rm --entrypoint certbot certbot certonly --webroot -w /var/www/certbot \
  -d "${DOMAIN}" --email "${EMAIL}" --agree-tos --no-eff-email

echo "4/4 Rechargement de Nginx"
$compose exec nginx nginx -s reload
echo "Certificat installé pour ${DOMAIN}"
