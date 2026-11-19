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
  (sur Catégories et Chronologie, le `h1` a la classe `.tab-title` : visuellement masqué partout car l'onglet actif
  le montre déjà). Texte jamais sous 13 px (11 px dans les SVG).
- **Navigation** : en haut sur grand écran (logo = menu ; barre claire au même style que celle du bas : icône + texte,
  pastille sur l'onglet actif) ; sur téléphone (≤ 640 px) barre d'onglets en bas
  (Catégories, Chronologie, Menu), délimitée par deux filets dans le navigateur, un seul en mode écran d'accueil
  (`display-mode: standalone`). Le menu est un seul partiel `views/_menu.php` (panneau du logo, `<dialog>` sur
  téléphone, route `menu` sans JS). Ajouter une entrée de menu = l'ajouter là ; le menu ne contient que des entrées valables
  depuis n'importe quel écran (Réorganiser est sur l'accueil, ligne `.home-actions` sous les groupes).
- **Pages secondaires** (fiche catégorie, fiche œuvre, formulaires) : ✕ en haut à droite via `close_link()` dans
  `.head-tools`, vers la page d'origine transmise par `?from=` (liens construits avec `from_here()`, conservé sur la
  même page par `keep_from()`, lu par `from_url($défaut)`, validé par `safe_from()` : URL relative seulement) ; sans `from`,
  un écran fixe par défaut — jamais `history.back()`. Les formulaires renvoient `back` = cette origine (`Http::back()`).
  Modifier la catégorie = ✎ (`.edit-btn`) collé au nom. Vocabulaire : « fiche » = page d'une œuvre uniquement ; la page
  d'une catégorie s'appelle « Statistiques et historique » (lien `.stats-link` en bas de `work_add`).
- **Réorganiser** : glisser-déposer maison en Pointer Events (poignée `[data-handle]`, `touch-action: none` sur la
  poignée seule), enregistrement immédiat (route `reorder`, JSON, ordre complet ; `Categories::reorder()` laisse en
  place les éléments non affichés : masqués verrouillés). Alternative sans glisser (WCAG 2.5.7) : appui = menu
  `<dialog>`, flèches au clavier. Sans JS : flèches `.nojs-move`. Plier/déplier en `localStorage`. Renommer un groupe
  (`group_rename`, fusion confirmée si le nom existe ; nom d'un groupe masqué verrouillé = « pas disponible »).
- **Apparence** : réglages `tile_style`, `tile_grad`, `tile_shape` (user_settings). Toute nouvelle couleur de texte sur
  un fond de catégorie passe par les variables de `Theme::tileVars()` (--c-solid / --c-on / --c-deep…), jamais du blanc
  en dur. Le calendrier colore avec `--c-cal` (éclairci en sombre).
- **Champs de saisie libre** (intitulés) : jamais `name="name"` ni d'id/libellé « name » — Safari y propose une
  fiche Contact malgré `autocomplete="off"`. Utiliser `name="label"` + autocomplete/autocorrect/spellcheck désactivés.
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
- Interface en français pour l'instant (i18n prévue plus tard). Verbes valables au doigt comme à la souris
  (« choisis », « ouvre », « appuie sur », jamais « touche » ni « tap ») ; les aides citent les libellés exacts des boutons.

## Arborescence

```
app/                    l'application, autonome
  public/               racine web : index.php (routeur), assets/
  src/                  Config, Db (+ migrations), Auth, Http, Time, Categories, Events, Chart, Icons, Importer,
                        Privacy, WebAuthn, Cbor, Catalog, Net, Works, Settings, Periods, Theme
  views/                templates PHP natifs (layout.php + une vue par page)
  migrations/           NNN_nom.sql, appliqués automatiquement au démarrage
docker/                 image générique nginx + php-fpm (Alpine), port 8080, données dans /data
ha-addon/               add-on Home Assistant = enveloppe autour de l'image (config.yaml seulement)
repository.yaml         pour ajouter le dépôt comme source d'add-ons dans HA
.github/workflows/      build multi-arch -> ghcr.io/simpsonus/batons
```

## Modèle de données

`users` → `groups` (facultatifs) → `categories` (nom, icône, couleur, unité, position, archivée, masquée, kind)
→ `events` (occurred_at UTC, value, note, + work_id, season, episode, volume, rating, milestone pour les œuvres).
`works` (par utilisateur : kind, source, source_id, titre, sous-titre, année, affiche, saisons en JSON, stopped_at).
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
   **Ajouter aussi une entrée en tête de `ha-addon/CHANGELOG.md`** (en français, orientée utilisateur) :
   c'est ce que Home Assistant affiche lors de la mise à jour.
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
- [x] v0.11 : jeux : bâtons-étapes `events.milestone` (start / finish), durée pour finir (`Works::status()`),
      moyenne des jeux terminés.
- [x] v0.12 : idem pour les livres (`Works::TRACKED` = jeu, livre ; libellés via `Works::milestoneLabels()`).
- [x] v0.13 : option `categories.periods` sur les catégories simples (`Periods.php`) : bâtons début/fin anonymes,
      **une seule période ouverte à la fois** (choix assumé : périodes sans nom), tap = début puis fin ;
      🏁 dans « Continuer » pour jeux/livres.
- [x] v0.14 : livres en tomes : « ▶ Tome N+1 » = fin du tome N (1 s avant) + début du tome N+1 ; étapes avec
      `volume` ; `Works::currentVolume()`, `Works::volumes()` ; flash « Annuler » multi-bâtons.
- [x] v0.15 : boutons d'action **avec libellé + sous-titre** (`views/_work_actions.php`), jamais d'icône seule
      pour une action qui enregistre ; infobulles `title` explicites ; aide contextuelle `help_box()`
      (details/summary) ; `works.stopped_at` (« J'arrête là », effacé par tout nouveau bâton de l'œuvre).
- [x] v0.16 : barre d'onglets en bas sur téléphone, menu unique (`_menu.php`), tuile « + Nouvelle catégorie »,
      corrections de lisibilité iPhone (aide « ? » pleine largeur, boutons d'action, tailles de texte).
- [x] v0.17 : ligne d'actions de l'accueil (Nouvelle catégorie, Réorganiser) sous les groupes, barre du haut
      alignée sur celle du bas, titres d'onglet masqués sur téléphone.
- [x] v0.18 : ✎ à côté du nom de la catégorie, ✕ « fermer » en haut à droite des pages secondaires.
- [x] v0.19 : ✕ sur l'écran d'une catégorie œuvre, « Fiche » -> ligne « Statistiques et historique » en bas.
- [x] v0.20 : aides réécrites (ajouter un titre d'abord), vocabulaire neutre doigt/souris, insécables dans `help_box()`.
- [x] v0.20.1 : ✕ / Annuler / Enregistrer ramènent à la page d'origine (`from`).
- [x] v0.21 : Réorganiser par glisser-déposer (catégories entre groupes), plier/déplier, renommer/fusionner les groupes.
- [x] v0.21.1 : groupe sous le doigt pendant le glisser (écart mesuré avant repli), pas de remplissage Contact Safari.
- [x] v0.22 : apparence des tuiles (`Theme.php` : 6 styles, 6 dégradés, 5 formes, couleurs dérivées AA calculées en PHP,
      classes `tiles-*/grad-*/shape-*` sur `<body>`) ; calendrier à échelle relative (`Chart::calendarScale()`, 90e centile).
- [x] v0.22.1 : dégradés réellement appliqués (fond uni en `background-color`, dégradé en `background-image`).
- [ ] Type de catégorie « Éléments » générique (éléments nommés sans catalogue, plusieurs en parallèle,
      début/fin/durée — mécanique des œuvres) : à faire si un cas concret apparaît (ex. « Traitements »).
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
