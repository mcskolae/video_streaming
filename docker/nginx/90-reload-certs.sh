#!/bin/sh
# Exécuté par l'entrypoint de l'image nginx avant le démarrage du serveur.
# Recharge la configuration toutes les 6 heures pour prendre en compte un certificat renouvelé.
# On ne remplace pas la commande de démarrage : l'entrypoint ne génère la configuration
# à partir du modèle que si la commande commence par "nginx".
(
    while :; do
        sleep 6h
        nginx -s reload
    done
) >/dev/null 2>&1 &
