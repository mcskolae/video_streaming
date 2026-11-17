# Déploiement en production (Oracle Cloud)

Ce guide couvre la carte F4 du backlog. Les étapes marquées **[toi]** nécessitent ton compte Oracle, ton domaine ou ton compte GitHub. Le reste est déjà scripté dans le dépôt.

## 1. Créer la machine Oracle [toi]

1. Instance : forme **VM.Standard.A1.Flex** (Ampere ARM), image **Canonical Ubuntu 24.04 (aarch64)**, 2 à 4 OCPU et 12 à 24 Go de RAM. Reste dans les limites Always Free.
2. Disque de démarrage : 50 Go.
3. Volume bloc : crée un volume de 100 à 150 Go et attache-le à l'instance. Le total gratuit est de 200 Go, volume de démarrage compris.
4. IP publique : réserve une IP publique et associe-la à l'instance. Une IP éphémère change si l'instance est recréée, et ton sous-domaine casserait.
5. Liste de sécurité du sous-réseau : règles entrantes TCP sur les ports **22** (limité à ton IP si possible), **80** et **443**. Rien d'autre.
6. Budget : dans la console, crée un budget avec une alerte à 1 € (compte Pay As You Go).

## 2. Préparer le serveur [toi, en SSH]

Les images Ubuntu d'Oracle ont des règles `iptables` qui bloquent le trafic même quand la liste de sécurité l'autorise. Ouvre les ports 80 et 443 sur la machine elle-même :

```sh
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
sudo apt-get update && sudo apt-get install -y iptables-persistent
sudo netfilter-persistent save
```

Installe Docker en suivant https://docs.docker.com/engine/install/ubuntu/ (dépôt officiel), puis :

```sh
sudo usermod -aG docker "$USER"   # puis se reconnecter
```

Monte le volume bloc sur `/srv/media` (remplace `/dev/sdb` par le périphérique réel, visible avec `lsblk`) :

```sh
sudo mkfs.ext4 /dev/sdb                       # une seule fois : efface le volume
sudo mkdir -p /srv/media
echo "UUID=$(sudo blkid -s UUID -o value /dev/sdb) /srv/media ext4 defaults,nofail 0 2" | sudo tee -a /etc/fstab
sudo mount -a
sudo mkdir -p /srv/media/incoming /srv/media/hls
sudo chown -R 1000:1000 /srv/media            # l'utilisateur "app" des conteneurs a l'UID 1000
```

## 3. Nom de domaine [toi]

Crée un sous-domaine gratuit (par exemple sur DuckDNS) qui pointe vers l'IP publique réservée. Vérifie auprès de ton enseignant qu'un sous-domaine gratuit est accepté comme « nom de domaine public ». Contrôle la résolution avant de continuer : `dig +short ton-domaine`.

## 4. Clé TMDB [toi]

Crée un compte sur themoviedb.org, puis demande une clé d'API dans *Paramètres, API*. Tu auras besoin de la clé « API Key » (v3).

## 5. GitHub [toi]

- Fusionne la pull request de F3, puis celle de F4, vers `develop`.
- Protège `main` et `develop` : pull request obligatoire et contrôle de CI requis.
- Crée le tableau Kanban (GitHub Projects) à partir du backlog de `docs/PLAN.md`.
- Pour que le VPS puisse cloner le dépôt privé, génère une clé sur le serveur (`ssh-keygen -t ed25519`) et ajoute la partie publique comme **Deploy key** en lecture seule (*Settings, Deploy keys*).

## 6. Premier déploiement [toi, sur le VPS]

```sh
git clone git@github.com:mcskolae/video_streaming.git && cd video_streaming
git checkout main     # après la première livraison ; avant, utiliser develop

cp .env.prod.example .env.prod
nano .env.prod                          # DOMAIN, APP_URL : ton sous-domaine

scripts/generate-secrets.sh             # crée les secrets, demande la clé TMDB
scripts/init-letsencrypt.sh ton@email   # certificat HTTPS
scripts/deploy.sh                       # build, démarrage, migrations
```

Vérifie ensuite que `https://ton-domaine` répond avec un cadenas valide.

## 7. Sauvegardes

Planifie la sauvegarde de la base avec `crontab -e` :

```
0 3 * * * /home/<utilisateur>/video_streaming/scripts/backup-db.sh
```

Les sauvegardes sont conservées 14 jours dans `~/backups`. Les vidéos ne sont pas sauvegardées : elles se re-téléversent.

## Choix de conception à connaître pour la soutenance

- **Secrets** : ils sont montés comme fichiers dans `/run/secrets/` (Docker secrets) et lus par la fonction `secret()` de `app/helpers.php`. Ils n'apparaissent ni dans l'environnement du conteneur, ni dans le dépôt. Contrôle : `docker compose exec app env` n'en affiche aucun.
- **`config:cache` n'est pas utilisé** : il écrirait les secrets en clair dans `bootstrap/cache/config.php`. Le gain de performance est faible face au risque.
- **Réseaux Docker** : PostgreSQL et Redis sont sur un réseau interne sans accès à Internet et sans port publié. Seul Nginx expose les ports 80 et 443. Le conteneur `app` a un réseau de sortie en plus, pour joindre l'API TMDB.
- **Utilisateur non root** dans l'image `app`, debug désactivé, cookies de session `Secure`.
- **Certificat** : obtenu via Let's Encrypt (challenge HTTP), renouvelé toutes les 12 heures si nécessaire par le conteneur `certbot`, et Nginx recharge sa configuration toutes les 6 heures.
- **À faire en carte Q1** : en-tête `Content-Security-Policy` avec nonce, géré côté Laravel (Livewire injecte du JavaScript en ligne).
