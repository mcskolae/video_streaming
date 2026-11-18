# Soutenance : démonstration et défense du modèle

## Avant le jour J

- [ ] **Gel du code deux jours avant** : plus de fusion dans `main` après ce moment (le déploiement continu repart à chaque fusion).
- [ ] Site en ligne et fonctionnel la semaine avant et la semaine après : `https://esgiflix.duckdns.org/up` répond 200.
- [ ] Catalogue de secours chargé **la veille** : `php artisan homeflix:demo --download` (voir [`DEMO.md`](DEMO.md)), puis relancer pour publier. Au moins deux films publiés et lisibles : **un média déjà prêt est le plan B** si le transcodage est lent.
- [ ] Compte administrateur et compte membre testés (mot de passe noté hors dépôt).
- [ ] Vérifier dans un vrai navigateur : accueil, recherche, lecteur, favoris, reprise de lecture, envoi d'une vidéo, console sans erreur de sécurité (CSP).
- [ ] Fichier vidéo court (moins de 30 s) prêt sur le poste pour l'envoi en direct.
- [ ] Jeton DuckDNS renouvelé ; protection de la branche `main` activée.

## Scénario de démonstration (10 minutes)

| Temps | Étape | Ce qu'on montre | Ce qu'on dit |
|---|---|---|---|
| 0:00 | Accueil public, sans compte | Derniers ajouts, genres, recherche « sintl » trouve « Sintel » | Catalogue public, recherche tolérante aux fautes (`pg_trgm`) |
| 1:00 | Fiche d'un titre, lecture | Lecteur HLS, changement de qualité | Pas de compte requis pour regarder ; flux HLS servi par Nginx |
| 2:00 | Inscription, connexion | Compte membre | Breeze ; rôle `user` ; le rôle n'est pas modifiable par formulaire |
| 3:00 | Favoris et reprise | Ajouter aux favoris ; lire 20 s ; quitter ; revenir : « Reprendre la lecture » | Position envoyée toutes les 10 s ; terminé à 90 % |
| 4:30 | Connexion administrateur | Catalogue admin | Rôle `admin`, routes protégées (403 sinon) |
| 5:00 | Importer depuis TMDB | Chercher un film, aperçu, importer en brouillon | Port `MetadataProvider`, adaptateur TMDB ; pas de doublon à la réimportation |
| 6:00 | Envoyer une vidéo | Fichier court, barre de progression, état « en cours » | Morceaux de 5 Mo avec reprise ; type réel contrôlé ; worker ffmpeg |
| 7:30 | Publier | Bouton actif quand la vidéo est prête, titre visible en public | Un titre ne se publie que s'il est lisible : règle du domaine **et** contrainte en base |
| 8:30 | Administration des comptes | Suspendre un compte, tenter de se connecter | Session fermée ; le dernier administrateur ne peut pas être retiré |
| 9:15 | Qualité | `docker compose exec app vendor/bin/pest` ou la CI verte, schéma de base | Tests, CI/CD, déploiement automatique |

**Plan B** : si l'envoi ou la conversion traîne, passer directement au titre déjà prêt, et dire que la conversion est un travail de fond. Ne pas attendre.

À chronométrer en répétition ; couper d'abord l'administration des comptes si le temps manque.

## Défendre le modèle de données

Détails et tableau des contraintes dans [`BASE-DE-DONNEES.md`](BASE-DE-DONNEES.md). Les quatre arguments à avoir en tête :

1. **Contraintes `CHECK`** : la base refuse un état impossible même si l'application a un défaut (film publié non lisible, vidéo prête sans flux, position au-delà de la durée). Les règles sont dans le domaine *et* dans la base.
2. **UUID v7** : identité connue avant l'enregistrement (pas besoin de la base pour tester le domaine), ordre de création conservé (index efficaces), non devinable dans les URL.
3. **`pg_trgm`** : recherche tolérante aux fautes dans PostgreSQL, sans moteur de recherche à héberger sur un petit serveur.
4. **`renditions` séparée** : un nombre variable de qualités par vidéo, une ligne chacune, unicité `(vidéo, qualité)`.

Questions probables :

- *Pourquoi une table `media_assets` pour les films et les épisodes ?* Un pipeline unique ; un `CHECK` « exactement un des deux » et deux `UNIQUE` gardent de vraies clés étrangères, ce qu'un lien polymorphe ne permet pas.
- *Pourquoi un drapeau `is_playable` dans `titles` ?* Pour afficher le catalogue sans jointure ; tenu par l'événement `MediaAssetReady` et protégé par des contraintes.
- *Que se passe-t-il si deux administrateurs se retirent le rôle en même temps ?* Les administrateurs actifs sont verrouillés (`FOR UPDATE`) dans une transaction : le second attend, puis voit qu'il est le dernier.
- *Pourquoi pas de sauvegarde ?* Décision de périmètre : projet pédagogique, contenu de démonstration rechargeable par une commande. À rouvrir si le site contient un jour des données qu'on ne peut pas recréer.

## Défendre l'architecture et les choix

Voir [`ARCHITECTURE.md`](ARCHITECTURE.md) (contextes, couches, pipeline, front).

- *Pourquoi DDD et hexagonal pour un petit site ?* Le but est de montrer la séparation des règles métier et de l'infrastructure : le domaine se teste sans base (couverture mesurée, seuil 90 % en CI) et TMDB, ffmpeg ou le disque se remplacent derrière des ports.
- *Pourquoi Blade, Livewire et Alpine, pas Svelte ?* Svelte aurait demandé une API JSON et un second état, pour une interactivité limitée à la recherche, aux favoris et à l'état des conversions. Blade garde le code explicable de bout en bout ; Livewire ne sert que là où la page doit réagir.
- *Pourquoi pas de WebSocket ?* Interdit par le sujet ; la liste des conversions se rafraîchit toutes les 10 s, seulement s'il y a une conversion active.
- *Pourquoi `unsafe-eval` dans la CSP ?* Alpine évalue les expressions des menus. Les scripts injectés restent bloqués (nonce obligatoire, aucun hôte externe). Limite connue et assumée.
- *Et le stockage ?* Disque local derrière un port (`MediaStorage`) : un CDN s'ajoute sans toucher au domaine.

## Ce qu'il faut dire honnêtement

- Pas de maquettes : point sacrifié, décidé dès le cadrage.
- Pas de sauvegarde, pas de supervision : périmètre assumé.
- Les flux vidéo ne sont pas authentifiés (un visiteur peut regarder).
- Le transcodage sur ARM est lent : c'est pourquoi la démonstration utilise des vidéos courtes.
- Sous-domaine gratuit DuckDNS : le sujet demande un nom de domaine public ; à confirmer avec l'enseignant (il suffit de changer le nom de domaine dans la configuration).
