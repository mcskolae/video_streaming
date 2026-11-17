#!/usr/bin/env bash
# Crée les fichiers de secrets de production dans ./secrets (ignoré par Git).
# Les secrets déjà présents ne sont jamais écrasés.
set -euo pipefail
cd "$(dirname "$0")/.."

umask 077
mkdir -p secrets

if [ ! -f secrets/app_key ]; then
  printf 'base64:%s' "$(openssl rand -base64 32)" > secrets/app_key
  echo "secrets/app_key créé"
fi

if [ ! -f secrets/db_password ]; then
  openssl rand -hex 24 | tr -d '\n' > secrets/db_password
  echo "secrets/db_password créé"
fi

if [ ! -f secrets/tmdb_api_key ]; then
  read -r -s -p "Jeton d'accès en lecture TMDB (ou clé API v3) : " tmdb
  echo
  printf '%s' "$tmdb" > secrets/tmdb_api_key
  echo "secrets/tmdb_api_key créé"
fi

# Lisibles par les conteneurs : les fichiers sont montés en lecture seule par Docker secrets.
chmod 644 secrets/*
chmod 700 secrets
