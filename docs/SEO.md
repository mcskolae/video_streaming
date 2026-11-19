# Référencement (bonus SEO)

Le référencement naturel consiste à aider les moteurs de recherche (Google, Bing…) à trouver les pages du site, à les comprendre et à les présenter correctement dans leurs résultats.

## Rendu côté serveur

Le barème cite le Server-Side Rendering (SSR) de Next.js ou Nuxt.js. Ces outils existent pour rattraper un défaut des applications React ou Vue : la page arrive vide et JavaScript la remplit ensuite, alors qu'un robot lit mieux une page déjà complète.

HomeFlix n'a pas ce défaut. Blade produit tout le HTML sur le serveur : un robot reçoit le titre, le synopsis, les genres et les liens dès la première réponse. Livewire ne sert qu'à la recherche en direct, qui n'est de toute façon pas à indexer.

## Ce que le site fournit

| Élément | Où | À quoi ça sert |
|---|---|---|
| Balises sémantiques | toutes les pages publiques | `header`, `nav`, `main`, `article`, `footer`, un seul `h1`, `lang="fr"`. Le robot repère le contenu principal et sa structure. Vérifié par `tests/Feature/Catalog/PublicPagesTest.php`. |
| `<title>` et `meta description` | toutes les pages | Le titre et le court texte affichés dans les résultats de recherche. Chaque page a son titre. |
| `/sitemap.xml` | `SeoController::sitemap` | Le plan du site : l'accueil, les genres et chaque titre publié, avec sa date de dernière modification (`lastmod`). Le robot y trouve toutes les pages sans les chercher. Les brouillons n'y figurent jamais. |
| `/robots.txt` | `SeoController::robots` | Les consignes aux robots : ne pas explorer l'administration, les favoris, le profil, la recherche, Livewire ni les segments vidéo (`/hls/`), et l'adresse du sitemap. Il est généré par Laravel, car la ligne `Sitemap:` doit contenir l'adresse absolue du site. |
| `<link rel="canonical">` | toutes les pages | L'adresse officielle de la page. `/?utm_source=x` et `/` sont la même page : sans balise canonique, le robot pourrait les compter deux fois (contenu dupliqué). Les pages 2 et suivantes d'un genre gardent leur numéro de page. |
| `<meta name="robots" content="noindex, follow">` | recherche, favoris | Demande de ne pas afficher ces pages dans les résultats (résultats de recherche interne, page personnelle), tout en suivant leurs liens. |
| Open Graph (`og:*`) | toutes les pages | L'aperçu d'un lien partagé sur une messagerie ou un réseau social : titre, description, affiche du film, type (`video.movie` ou `video.tv_show`). |
| Données structurées JSON-LD | fiche d'un titre | Une description lisible par une machine, au vocabulaire schema.org : `Movie` ou `TVSeries`, titre, année, genres, durée, nombre de saisons et d'épisodes. Google peut s'en servir pour un résultat enrichi. |

## Sécurité

- Le JSON-LD est construit par `TitleStructuredData` avec `JSON_HEX_TAG` et `JSON_HEX_AMP` : les caractères `<`, `>` et `&` sont encodés. Un titre importé de TMDB qui contiendrait `</script>` ne peut donc pas fermer la balise et injecter du code. Un test le vérifie.
- La balise porte le nonce de la politique CSP, comme les autres scripts de la page.
- L'URL de l'affiche passe par `PosterUrl`, qui n'accepte qu'un chemin `/nom.ext` et une taille connue. Le composant `poster` l'utilise aussi.

## Architecture

Le sitemap suit le même chemin que les autres pages publiques. Le contrôleur (UI) appelle `PublicCatalog::sitemap()` (Application), qui interroge le port `PublicCatalogReader`, implémenté en SQL par `DatabasePublicCatalogReader` (Infrastructure). Le nombre d'adresses est plafonné à 49 000 titres, sous la limite de 50 000 du protocole sitemap.

## Vérifier en production

- `https://<domaine>/robots.txt` et `https://<domaine>/sitemap.xml` répondent.
- Déclarer le sitemap dans Google Search Console (facultatif, demande de prouver qu'on possède le domaine).
- Tester une fiche avec le test des résultats enrichis de Google (https://search.google.com/test/rich-results).
