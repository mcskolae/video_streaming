# Catalogue de démonstration

La démonstration ne dépend ni d'un film protégé par le droit d'auteur, ni de la conversion d'un long métrage : elle charge deux films libres de la Blender Foundation, avec leur **bande-annonce officielle** (33 s et 52 s) comme vidéo.

## Charger

Prérequis : clé TMDB configurée, worker démarré (`docker compose up -d worker`).

```bash
docker compose exec app php artisan homeflix:demo --download
```

La commande :

1. crée deux comptes de démonstration (`demo-admin@homeflix.test`, `demo-membre@homeflix.test`) avec un **mot de passe tiré au hasard, affiché une seule fois** : aucun mot de passe n'est écrit dans le dépôt ni dans un seeder ;
2. importe chaque film depuis TMDB (affiche, synopsis, genres), sans doublon ;
3. télécharge la vidéo manquante (`--download`, uniquement depuis `download.blender.org` en HTTPS) ou prend celle du dossier `storage/app/demo` (`--videos=DOSSIER` pour un autre dossier) ;
4. l'envoie par le même chemin que l'administration (morceaux de 5 Mo, contrôle du contenu réel) : la conversion HLS part dans la file ;
5. publie les films dont la vidéo est prête.

**Relancez la commande une fois la conversion terminée** (quelques secondes pour ces vidéos) : les films prêts sont publiés. Elle est relançable sans effet de bord : un compte, un film ou une vidéo déjà présents sont conservés.

En production, il faut ajouter `--force`.

## Sources et licences

| Film | Vidéo utilisée | Licence | Source |
|---|---|---|---|
| Big Buck Bunny (2008) | bande-annonce 480p, 11 Mo, 33 s | CC BY 3.0, © Blender Foundation, peach.blender.org | https://peach.blender.org/ |
| Sintel (2010) | bande-annonce 480p, 4 Mo, 52 s | CC BY 3.0, © Blender Foundation, durian.blender.org | https://durian.blender.org/ |

Les adresses de téléchargement sont dans `config/demo.php`. Les fiches (affiches, synopsis) viennent de TMDB : voir l'attribution en pied de site.

Pour ajouter un film libre, ajouter une entrée dans `config/demo.php` (titre cherché sur TMDB, année, licence, source, fichier, adresse de téléchargement sur `download.blender.org`).

## Plan B pour la soutenance

Charger le catalogue **la veille**, vérifier que les deux films sont publiés et lisibles, et ne pas dépendre d'une conversion en direct : un média prêt est le secours si le transcodage est lent.
