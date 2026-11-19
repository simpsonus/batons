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
- **Authentification enfichable** (`BATONS_AUTH`) :
  - `none` : un seul utilisateur local (`default_user`) ;
  - `header` : identité transmise par un reverse proxy (HA Ingress, Authelia, Authentik…), avec
    `BATONS_TRUSTED_PROXIES` pour n'accepter l'en-tête que du proxy ;
  - `builtin` (comptes + mots de passe) : **pas encore implémenté**.
  Toutes les données sont rattachées à un `user_id` dès maintenant.
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
- Interface en français pour l'instant (i18n prévue plus tard).

## Arborescence

```
app/                    l'application, autonome
  public/               racine web : index.php (routeur), assets/
  src/                  Config, Db (+ migrations), Auth, Http, Time, Categories, Events, Chart
  views/                templates PHP natifs (layout.php + une vue par page)
  migrations/           NNN_nom.sql, appliqués automatiquement au démarrage
docker/                 image générique nginx + php-fpm (Alpine), port 8080, données dans /data
ha-addon/               add-on Home Assistant = enveloppe autour de l'image (config.yaml seulement)
repository.yaml         pour ajouter le dépôt comme source d'add-ons dans HA
.github/workflows/      build multi-arch -> ghcr.io/simpsonus/batons
```

## Modèle de données

`users` → `groups` (facultatifs) → `categories` (nom, icône émoji, couleur, unité, position, archivée)
→ `events` (occurred_at UTC, value REAL facultative, note facultative).
Nouvelle évolution de schéma = nouveau fichier `app/migrations/00N_xxx.sql`, jamais modifier un ancien.

## Lancer en local

```
php app/bin/fetch-icons.php                     # une fois : génère app/icons/mdi.php
cd app && php -S 127.0.0.1:8080 -t public      # données dans app/data/
BATONS_AUTH=header BATONS_AUTH_HEADER=X-User php -S …   # tester le mode header
docker build -f docker/Dockerfile -t batons . && docker run -p 8080:8080 -v $PWD/data:/data batons
```

## Publier une version (pour l'add-on HA)

1. Mettre à jour `VERSION` dans `app/src/bootstrap.php` et `version` dans `ha-addon/config.yaml`.
2. `git tag vX.Y.Z && git push --tags` → la CI publie `ghcr.io/simpsonus/batons:X.Y.Z`.
3. Le paquet ghcr doit être **public** (réglage à faire une fois sur GitHub) pour que HA puisse le télécharger.

## Feuille de route

- [x] v0.1 : catégories, groupes, bâtons (tap éclair + saisie détaillée), stats, graphique, chronologie,
      archives, réorganisation, export CSV, Docker, add-on HA.
- [x] v0.2 : icônes Material Design (sélecteur avec recherche, mots-clés français), correctif appui long iOS.
- [ ] Recherche / filtre dans la chronologie, vue calendrier (heatmap) par catégorie.
- [ ] Rappels / objectifs (« au moins 3 par semaine »).
- [ ] Import CSV (et import depuis Chronicling).
- [ ] Mode d'authentification `builtin` (prérequis pour exposer l'app hors d'un proxy authentifiant, ex. port direct de l'add-on).
- [ ] i18n (anglais).
- [ ] Intégration HA facultative : capteurs (« temps depuis », « total du jour »), webhook/API pour ajouter
      un bâton depuis une automatisation. Toujours via une API générique, pas de code spécifique HA dans l'app.
- [ ] PWA hors ligne.
