#!/usr/bin/env bash
# Déploiement continu. Appelé par scripts/ci-entrypoint.sh une fois le dépôt placé sur main.
#
# 1. déploie (scripts/deploy.sh) ;
# 2. vérifie que le site répond ;
# 3. en cas d'échec, revient au code du déploiement précédent.
#
# Le retour arrière ne concerne que le code : les migrations déjà appliquées ne sont pas annulées.
set -euo pipefail
cd "$(dirname "$0")/.."

compose="docker compose -f compose.prod.yml --env-file .env.prod"
state=".last-deployed"
new="$(git rev-parse HEAD)"
previous="$(cat "$state" 2>/dev/null || true)"

set -a
. ./.env.prod
set +a
: "${DOMAIN:?DOMAIN manquant dans .env.prod}"

success=0

rollback() {
    echo "ÉCHEC du déploiement de ${new}." >&2
    if [ -n "$previous" ] && [ "$previous" != "$new" ]; then
        echo "Retour au code ${previous}. Les migrations déjà appliquées ne sont pas annulées." >&2
        git checkout --quiet --detach "$previous"
        $compose up -d --build --remove-orphans || echo "Le retour arrière a aussi échoué : intervention manuelle nécessaire." >&2
    else
        echo "Aucun déploiement précédent connu : pas de retour arrière." >&2
    fi
}
trap 'if [ "$success" != 1 ]; then rollback; fi' EXIT

echo "Déploiement de ${new} (précédent : ${previous:-aucun})."

scripts/deploy.sh

# Vérification locale, sur le vrai certificat : le nom de domaine est résolu vers cette machine.
healthy=0
for attempt in 1 2 3 4 5 6; do
    if curl -fsS --max-time 10 --resolve "${DOMAIN}:443:127.0.0.1" "https://${DOMAIN}/up" >/dev/null; then
        healthy=1
        break
    fi
    echo "Le site ne répond pas encore (essai ${attempt}/6)." >&2
    sleep 5
done

if [ "$healthy" != 1 ]; then
    exit 1
fi

echo "$new" > "$state"
success=1
echo "Déploiement réussi : ${new}."
