# CLAUDE.md — contexte du projet Bâtons

Ce fichier donne le cadre du projet à toute session de Claude (ou à tout contributeur).
Le tenir à jour quand une décision change.

## L'idée

**Bâtons** est une application web auto-hébergée dans l'esprit de *Chronicling* (iOS) :
« compter et dater tout ce qui compte ». Des **catégories** (Café, Arrosage, Migraine…) et des
**bâtons** (événements datés, avec valeur numérique et note facultatives). Un tap sur une tuile = un bâton
maintenant. Chaque catégorie a ses statistiques, un graphique et son historique.

Le nom vient des bâtons qu'on trace pour compter (IIII barrés). Slug technique : `batons` (sans accent).

## Décisions d'architecture (à respecter)

- **PHP 8.3 sans framework**, sans Composer obligatoire. Autoloader maison dans `app/src/bootstrap.php`.
  Style « vieille garde » assumé : lisible, peu de magie.
- **PDO + SQLite** par défaut (mode WAL). SQL aussi standard que possible pour pouvoir passer à
  PostgreSQL plus tard (le multi-utilisateur ne l'exige pas : SQLite suffit pour une famille).
- **L'application est indépendante de Home Assistant.** HA n'est qu'une cible de déploiement parmi d'autres.
  Aucun code applicatif ne doit mentionner HA ou en dépendre.
- **Configuration par variables d'environnement `BATONS_*`** (ou `app/config.php`), voir `app/src/Config.php`.
- **URLs toujours relatives.** Un seul point d'entrée `app/public/index.php`, routes via le paramètre `r`
  (`./?r=category&id=3`). Pas de réécriture d'URL. Ça fonctionne à la racine, en sous-dossier, derrière un
  proxy et sous HA Ingress. Ne jamais écrire d'URL absolue commençant par `/`.
- **Authentification enfichable** (`BATONS_AUTH`, un ou plusieurs modes séparés par des virgules, `Auth.php`) :
  - `none` : un seul utilisateur local (`default_user`) ;
  - `header` : identité transmise par un reverse proxy (HA Ingress, Authelia, Authentik…). L'en-tête n'est
    cru que des IP de `BATONS_TRUSTED_PROXIES` ; ailleurs il est **ignoré** ;
  - `builtin` : comptes + mots de passe (page de connexion, `password_hash`, 5 échecs -> blocage 15 min par
    compte, 20 échecs/15 min par IP ; sessions de 30 jours stockées dans `data_dir/sessions` ; changer le
    mot de passe invalide les autres sessions). En `builtin` seul : le premier compte (route `setup`) est admin
    et gère les autres (page Compte).
  - `header,builtin` (add-on HA) : Ingress = identité HA ; accès direct par un port = connexion, avec le
    **même identifiant** (nom d'utilisateur HA) et un mot de passe défini dans « Compte » -> mêmes données.
  Toutes les données sont rattachées à un `user_id`.
- **Dates stockées en UTC** (`'Y-m-d H:i:s'`), converties dans le fuseau `BATONS_TIMEZONE`/`TZ`
  pour l'affichage et la saisie (`app/src/Time.php`).
- **Front : HTML + CSS + un peu de JS vanilla**, mobile d'abord, clair/sombre automatique.
  Tout doit marcher sans JS (formulaires POST + redirection 303). Graphiques en **SVG générés en PHP**.
- Sécurité : échapper toute sortie avec `e()`, jeton CSRF sur tout POST (`csrf()` dans les formulaires),
  requêtes toujours filtrées par `user_id`.
- **Icônes** : émoji libre, ou `mdi:nom` (Material Design Icons, comme Home Assistant), rendues en SVG inline
  par `Icons::html()` / `icon()` dans les vues. La table `app/icons/mdi.php` n'est **pas versionnée** : elle est
  générée par `php app/bin/fetch-icons.php` (téléchargement jsDelivr, version épinglée), automatiquement au
  build Docker. Sans elle, l'app marche avec les émojis seuls.
- Tactile : pas de sélection de texte ni de menu contextuel sur un appui long (CSS `user-select`/`touch-callout`).
- **Responsive et accessible (exigence, WCAG 2.2 AA visé)** : vérifier de 320 px à grand écran sans défilement
  horizontal ; cibles tactiles ≥ 44 px (`--tap`) ; contrastes AA (couleur de catégorie en texte via `--c-ink`,
  éclaircie en sombre) ; focus visible ; lien d'évitement ; boutons-icônes avec `aria-label` explicite
  (`ui_icon()` pour les pictos d'interface) ; icônes décoratives `aria-hidden` ; erreurs de formulaire liées par
  `aria-describedby` + `aria-invalid` ; graphique doublé d'un tableau pour lecteurs d'écran ; pas de message
  qui disparaît tout seul ; `prefers-reduced-motion` respecté ; un seul `h1` et pas de saut de niveau de titre
  (sur Catégories et Chronologie, le `h1` est visuellement masqué `.sr` car il doublerait l'onglet actif).
- L'interface ne mentionne pas Home Assistant (l'app doit rester neutre).
- **Éléments masqués** (`Privacy.php`, `WebAuthn.php`, `Cbor.php`) : `categories.hidden` / `groups.hidden`.
  Verrouillé = invisibles PARTOUT (accueil, chronologie, archives, export, réorganiser, liste des groupes,
  compte rendu d'import, URL directes -> 404). Toute nouvelle requête qui liste des catégories ou des bâtons
  doit ajouter `Privacy::visibleSql()` (avec `LEFT JOIN groups g`). Déverrouillage : code à 6 chiffres haché
  (`password_hash`, 5 essais puis blocage 15 min) ou passkey WebAuthn (Face ID / Touch ID…), implémentée sans
  bibliothèque (ES256/RS256, attestation « none », vérification utilisateur requise, rpId enregistré avec la clé).
  État en session, expiration après `BATONS_HIDDEN_TIMEOUT` s d'inactivité (300). Masquer exige un code défini.
  Ce n'est pas du chiffrement (le dire dans l'interface).
- **Catégories « œuvre »** (`categories.kind` = `serie` | `film` | `livre` | `jeu`, sinon `simple`) : chaque bâton
  pointe vers une œuvre (`works`) avec saison/épisode (séries), tome (livres) et note 1–10 facultative.
  La tuile ouvre `work_add` (« Continuer » = épisode/tome suivant en un tap, puis recherche). Catalogues
  interrogés **côté serveur** (`Catalog.php`, `Net.php` avec cache `http_cache`) : TMDB (clé, réglage par
  utilisateur dans Compte ou `BATONS_TMDB_KEY`), TVmaze (séries sans clé), Google Books puis Open Library,
  RAWG pour les jeux (clé, `BATONS_RAWG_KEY` ; lien actif vers rawg.io obligatoire là où ses données s'affichent).
  Chaque œuvre a sa fiche (route `work` : historique, ▶ suivant, « + Ajouter … ») ; dans les listes, le titre
  mène à la fiche (pas de crayon : un crayon = modifier un bâton existant, bouton « Mettre à jour »).
  Affiches copiées dans `data_dir/posters`, vignettes de recherche relayées via `thumb` (liste blanche
  d'hôtes) : le navigateur ne contacte jamais les catalogues. Saisie manuelle toujours possible.
  Mention TMDB obligatoire (« utilise l'API TMDB mais n'est ni approuvé ni certifié par TMDB »).
  Tests hors ligne : `BATONS_HTTP_FIXTURES=fichier.php` (tableau regex => JSON) remplace le réseau.
- Interface en français pour l'instant (i18n prévue plus tard).

## Arborescence

```
app/                    l'application, autonome
  public/               racine web : index.php (routeur), assets/
  src/                  Config, Db (+ migrations), Auth, Http, Time, Categories, Events, Chart, Icons, Importer,
                        Privacy, WebAuthn, Cbor, Catalog, Net, Works, Settings
  views/                templates PHP natifs (layout.php + une vue par page)
  migrations/           NNN_nom.sql, appliqués automatiquement au démarrage
docker/                 image générique nginx + php-fpm (Alpine), port 8080, données dans /data
ha-addon/               add-on Home Assistant = enveloppe autour de l'image (config.yaml seulement)
repository.yaml         pour ajouter le dépôt comme source d'add-ons dans HA
.github/workflows/      build multi-arch -> ghcr.io/simpsonus/batons
```

## Modèle de données

`users` → `groups` (facultatifs) → `categories` (nom, icône, couleur, unité, position, archivée, masquée, kind)
→ `events` (occurred_at UTC, value, note, + work_id, season, episode, volume, rating pour les œuvres).
`works` (par utilisateur : kind, source, source_id, titre, sous-titre, année, affiche, saisons en JSON).
Autres : `passkeys`, `user_settings`, `login_failures`, `http_cache`.
Nouvelle évolution de schéma = nouveau fichier `app/migrations/00N_xxx.sql`, jamais modifier un ancien.

## Lancer en local

```
php app/bin/fetch-icons.php                     # une fois : génère app/icons/mdi.php
cd app && php -S 127.0.0.1:8080 -t public      # données dans app/data/
BATONS_AUTH=header BATONS_AUTH_HEADER=X-User php -S …   # tester le mode header
docker build -f docker/Dockerfile -t batons . && docker run -p 8080:8080 -v $PWD/data:/data batons
```

## Publier une version (pour l'add-on HA)

1. Mettre à jour `VERSION` dans `app/src/bootstrap.php` et `version` dans `ha-addon/config.yaml` (les deux identiques).
2. Pousser sur `main`. La CI voit qu'il n'existe pas encore de tag `vX.Y.Z` : elle publie
   `ghcr.io/simpsonus/batons:X.Y.Z` (+ `latest`), puis crée elle-même le tag et la release GitHub.
   Sans changement de version, elle ne publie que l'image `edge`. Pas besoin de créer de release à la main.
3. Le paquet ghcr est public (réglage fait une fois) : HA propose alors la mise à jour de l'add-on.

## Feuille de route

- [x] v0.1 : catégories, groupes, bâtons (tap éclair + saisie détaillée), stats, graphique, chronologie,
      archives, réorganisation, export CSV, Docker, add-on HA.
- [x] v0.2 : icônes Material Design (sélecteur avec recherche, mots-clés français), correctif appui long iOS.
- [x] v0.3 : import (exports CSV Chronicling events/categories/groups, et export CSV de Bâtons), sans doublons
      (`app/src/Importer.php` : SF Symbols -> MDI, couleurs iOS -> palette).
- [x] v0.4 : boutons-icônes Modifier/Supprimer, passe responsive (320 px → desktop) et accessibilité ;
      la CI crée elle-même les releases.
- [x] v0.5 : menu sur le logo (Archives, Importer, Exporter ; `<details>` sans JS requis),
      réorganisation des groupes entre eux et des catégories dans leur groupe.
- [x] v0.6 : catégories et groupes **masqués** (à la « Photos masquées » d'Apple), voir ci-dessus.
- [x] v0.7 : mode `builtin` (connexion, comptes) et combinaison `header,builtin` ; port direct facultatif dans
      l'add-on (doc Nginx Proxy Manager dans `ha-addon/DOCS.md`).
- [x] v0.8 : vue calendrier par catégorie (grille 52 semaines × 7 jours, lundi en haut, intensité 1→4+,
      `Chart::calendar()` + tableau mensuel pour lecteurs d'écran).
- [x] v0.9 : catégories « œuvre » (séries, films, livres) : recherche catalogue, épisode/tome suivant,
      note sur 10, affiches, export/réimport.
- [x] v0.10 : jeux vidéo (RAWG, « ▶ Partie »), fiche œuvre, libellés Ajouter / Mettre à jour clarifiés.
- [ ] Plus tard éventuellement : synchro BetaSeries / Trakt (pas SensCritique ni SerieBox : pas d'API
      publique). Jamais de synchro pour une catégorie masquée.
- [ ] Recherche / filtre dans la chronologie.
- [ ] Rappels / objectifs (« au moins 3 par semaine »).
- [ ] i18n (anglais).
- [ ] Intégration HA facultative : capteurs (« temps depuis », « total du jour »), webhook/API pour ajouter
      un bâton depuis une automatisation. Toujours via une API générique, pas de code spécifique HA dans l'app.
- [ ] PWA hors ligne.
- [ ] (mis de côté) Widgets iOS : impossibles pour une app web. Pistes étudiées : API à clé personnelle +
      publication MQTT (découverte HA) pour les widgets personnalisés de l'app Home Assistant, et/ou
      Raccourcis iOS appelant l'API. Catégories masquées jamais exposées. App native Swift écartée.
