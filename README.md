# Bâtons

**Compter et dater tout ce qui compte.** Un café, un arrosage, une migraine, un footing…
Chaque catégorie est une tuile : un tap ajoute un « bâton » daté. Statistiques, graphiques et
chronologie sont dans la fiche de chaque catégorie. Inspiré de l'app iOS *Chronicling*, en version
web auto-hébergée.

- PHP 8.3 sans framework, SQLite, aucune dépendance JS
- Mobile d'abord, thème clair/sombre automatique, installable en PWA
- Multi-utilisateur via un reverse proxy qui authentifie (Home Assistant, Authelia, Authentik…)
- Catégories « œuvre » : séries (épisode suivant en un tap), films, livres (tome), jeux vidéo, avec note sur 10
- Export CSV

## Installation

### Docker

```sh
docker run -d --name batons -p 8080:8080 -v ./data:/data -e TZ=Europe/Paris ghcr.io/simpsonus/batons:latest
```

ou avec le `docker-compose.yml` fourni. Puis ouvrir http://localhost:8080.

### Add-on Home Assistant

1. **Paramètres → Modules complémentaires → Boutique → ⋮ → Dépôts**, ajouter
   `https://github.com/simpsonus/batons`
2. Installer **Bâtons**, démarrer, puis l'ouvrir depuis la barre latérale.

Chaque utilisateur Home Assistant a ses propres données ; elles sont incluses dans les sauvegardes HA.

### Hébergement PHP classique

Copier le dossier `app/` sur un serveur PHP 8.3+ (extension `pdo_sqlite`), avec `app/public/` comme
racine web (ou dans un sous-dossier). Le dossier `app/data/` doit être accessible en écriture.

## Configuration

| Variable | Défaut | Rôle |
|---|---|---|
| `BATONS_DATA_DIR` | `app/data` (`/data` en Docker) | Dossier de la base SQLite |
| `BATONS_DB_DSN` | `sqlite:<data_dir>/batons.sqlite` | DSN PDO |
| `BATONS_TIMEZONE` | `TZ` ou fuseau PHP | Fuseau d'affichage et de saisie |
| `BATONS_AUTH` | `none` | `none` (un utilisateur), `header` (reverse proxy), `builtin` (comptes et mots de passe), ou une combinaison comme `header,builtin` |
| `BATONS_AUTH_HEADER` | `Remote-User` | En-tête portant l'identifiant (mode `header`) |
| `BATONS_AUTH_NAME_HEADER` | — | En-tête portant le nom affiché (facultatif) |
| `BATONS_TRUSTED_PROXIES` | — | IP autorisées à envoyer l'en-tête, séparées par des virgules |
| `BATONS_HIDDEN_TIMEOUT` | `300` | Secondes d'inactivité avant reverrouillage des éléments masqués |
| `BATONS_RAWG_KEY` | — | Clé RAWG commune (jeux vidéo) ; chaque utilisateur peut aussi saisir la sienne |
| `BATONS_TMDB_KEY` | — | Clé TMDB commune (films et séries) ; chaque utilisateur peut aussi saisir la sienne dans « Compte » |

En mode `builtin` seul, le premier visiteur crée le compte **administrateur** (qui crée ensuite les autres) :
fais-le dès l'installation. En mode `header,builtin`, les requêtes du proxy de confiance utilisent son identité ;
les autres passent par la page de connexion, avec le même identifiant (mot de passe défini dans « Compte »).

⚠️ En mode `none`, n'exposez pas Bâtons sur Internet sans protection devant (reverse proxy avec authentification, VPN…).

## Développement

```sh
cd app && php -S 127.0.0.1:8080 -t public
```

Voir `CLAUDE.md` pour l'architecture et les conventions.
