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

Crée un compte sur themoviedb.org, puis demande une clé d'API dans *Paramètres, API*. Deux valeurs sont acceptées, au choix :

- le **jeton d'accès en lecture** (« API Read Access Token », il contient des points) : à préférer, car il est envoyé dans un en-tête et n'apparaît jamais dans une URL ;
- la **clé d'API v3** (« API Key », 32 caractères hexadécimaux), envoyée en paramètre `api_key`.

L'application reconnaît le type tout seule. La valeur va dans `secrets/tmdb_api_key` (saisie masquée par `scripts/generate-secrets.sh`) et n'est lue que par `secret('TMDB_API_KEY')`.

Pour changer la clé sur la VM : `printf '%s' "<valeur>" > ~/video_streaming/secrets/tmdb_api_key`, puis redémarrer l'application (`docker compose -f compose.prod.yml --env-file .env.prod up -d --force-recreate app`).

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

## 6 bis. Créer le premier administrateur

Aucun compte administrateur n'existe après le premier déploiement. Il se crée avec une commande Artisan, sur la VM, dans un terminal interactif (`-t`) : le mot de passe est saisi de façon masquée, il ne passe ni par l'historique du shell, ni par les variables d'environnement, ni par le dépôt.

```sh
ssh -t ubuntu@<ip> 'cd ~/video_streaming && docker compose -f compose.prod.yml --env-file .env.prod exec app php artisan homeflix:make-admin ton@adresse'
```

- Si l'adresse n'a pas de compte, la commande demande un nom et un mot de passe (12 caractères minimum, avec confirmation).
- Si l'adresse a déjà un compte (inscrit via le site), il est promu administrateur sans toucher à son mot de passe.
- Un compte déjà administrateur n'est pas modifié.

Il n'y a ni réinitialisation de mot de passe par e-mail ni vérification d'adresse : le projet n'a pas de serveur d'e-mail. Un mot de passe perdu se remplace en recréant le compte ou depuis `php artisan tinker`.

## 7. Sauvegardes

Planifie la sauvegarde de la base avec `crontab -e` :

```
0 3 * * * /home/<utilisateur>/video_streaming/scripts/backup-db.sh
```

Les sauvegardes sont conservées 14 jours dans `~/backups`. Les vidéos ne sont pas sauvegardées : elles se re-téléversent.

## 8. Déploiement continu (GitHub Actions)

Chaque push sur `main` dont la CI est verte déploie automatiquement sur la VM. Avec Gitflow, `main` ne reçoit que les livraisons venant de `develop` : un déploiement est donc une décision volontaire, la pull request `develop` vers `main`.

Fonctionnement :

1. Le job `deploy` de `.github/workflows/ci.yml` s'exécute uniquement sur un push sur `main`, après le job `quality`. Les pull requests, y compris celles des forks, ne le déclenchent jamais et n'accèdent à aucun secret.
2. Il se connecte en SSH à la VM avec une clé dédiée. Côté serveur, cette clé est restreinte dans `~/.ssh/authorized_keys` par `restrict,command="/usr/local/bin/homeflix-deploy"` : quelle que soit la commande demandée, seul ce script s'exécute. Pas de shell, pas de terminal, pas de redirection de ports.
3. `scripts/ci-entrypoint.sh` (installé en `/usr/local/bin/homeflix-deploy`, propriété de root) prend un verrou, récupère `origin/main`, refuse de continuer si `scripts/ci-deploy.sh` n'existe pas encore sur `main`, puis place le dépôt sur `main`.
4. `scripts/ci-deploy.sh` sauvegarde la base, déploie (`scripts/deploy.sh`) et vérifie que `/up` répond sur le vrai certificat. En cas d'échec, il revient au code du déploiement précédent. Les migrations déjà appliquées ne sont pas annulées : écris des migrations compatibles avec l'ancien code.
5. Le workflow vérifie ensuite depuis Internet que le site répond.

Configuration en place (environnement GitHub `production`, limité à la branche `main`) :

| Nom | Type | Contenu |
|---|---|---|
| `DEPLOY_SSH_KEY` | secret | clé privée de déploiement |
| `DEPLOY_KNOWN_HOSTS` | secret | clé d'hôte de la VM, pour ne pas faire confiance au premier contact |
| `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_URL` | variables | adresse de la VM, utilisateur, URL publique |

Opérations courantes :

- **Réinstaller le point d'entrée** après l'avoir modifié (la copie installée n'est pas mise à jour toute seule) :
  `scp scripts/ci-entrypoint.sh ubuntu@<ip>:/tmp/homeflix-deploy.new && ssh ubuntu@<ip> 'sudo install -m 755 -o root -g root /tmp/homeflix-deploy.new /usr/local/bin/homeflix-deploy'`
- **Changer la clé de déploiement** : générer une nouvelle paire, remplacer la ligne `homeflix-ci-deploy` dans `authorized_keys` en gardant les options `restrict,command=...`, puis mettre à jour le secret `DEPLOY_SSH_KEY`.
- **Déployer à la main** reste possible : `scripts/deploy.sh` sur la VM.
- **Premier déploiement automatique** : il faut d'abord une pull request `develop` vers `main`, car `main` doit contenir `scripts/ci-deploy.sh`. Tant que ce n'est pas le cas, le point d'entrée refuse de toucher à la VM.

Limites connues :

- Le port 22 de la VM doit être joignable depuis les machines de GitHub, dont les adresses changent. Il ne peut donc pas être limité à ta seule adresse IP.
- Le journal du workflow d'un dépôt public est visible de tous. Les scripts n'affichent aucun secret : ne jamais en ajouter.
- Quiconque peut pousser sur `main` peut exécuter du code sur la VM. La protection de `main` (pull request obligatoire) fait partie de la sécurité du déploiement.

## Choix de conception à connaître pour la soutenance

- **Secrets** : ils sont montés comme fichiers dans `/run/secrets/` (Docker secrets) et lus par la fonction `secret()` de `app/helpers.php`. Ils n'apparaissent ni dans l'environnement du conteneur, ni dans le dépôt. Contrôle : `docker compose exec app env` n'en affiche aucun.
- **`config:cache` n'est pas utilisé** : il écrirait les secrets en clair dans `bootstrap/cache/config.php`. Le gain de performance est faible face au risque.
- **Réseaux Docker** : PostgreSQL et Redis sont sur un réseau interne sans accès à Internet et sans port publié. Seul Nginx expose les ports 80 et 443. Le conteneur `app` a un réseau de sortie en plus, pour joindre l'API TMDB.
- **Utilisateur non root** dans l'image `app`, debug désactivé, cookies de session `Secure`.
- **Certificat** : obtenu via Let's Encrypt (challenge HTTP), renouvelé toutes les 12 heures si nécessaire par le conteneur `certbot`, et Nginx recharge sa configuration toutes les 6 heures.
- **À faire en carte Q1** : en-tête `Content-Security-Policy` avec nonce, géré côté Laravel (Livewire injecte du JavaScript en ligne).
