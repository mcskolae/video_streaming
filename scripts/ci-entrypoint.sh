#!/bin/sh
# Point d'entrée unique de la clé SSH utilisée par GitHub Actions.
#
# Installé en tant que /usr/local/bin/homeflix-deploy (propriété de root) et référencé dans
# ~/.ssh/authorized_keys par l'option `command=`. Quelle que soit la commande que le client SSH
# demande, seul ce script s'exécute : la clé ne donne pas de shell.
#
# Il vit dans le dépôt pour être versionné, mais la copie installée n'est pas mise à jour
# automatiquement : après une modification, la réinstaller à la main (voir docs/DEPLOY.md).
set -eu

REPO="${HOMEFLIX_REPO:-/home/ubuntu/video_streaming}"

# Un seul déploiement à la fois. Le descripteur 9 reste ouvert dans les processus enfants,
# donc le verrou est tenu jusqu'à la fin du déploiement.
exec 9>/tmp/homeflix-deploy.lock
flock -n 9 || {
    echo "Un déploiement est déjà en cours." >&2
    exit 1
}

cd "$REPO"
git fetch --quiet origin main

# Garde-fou : tant que main n'a pas le script de déploiement, on ne touche à rien.
if ! git cat-file -e origin/main:scripts/ci-deploy.sh 2>/dev/null; then
    echo "origin/main ne contient pas encore scripts/ci-deploy.sh : déploiement annulé, rien n'a été modifié." >&2
    exit 1
fi

git checkout --quiet -B main origin/main
exec ./scripts/ci-deploy.sh
