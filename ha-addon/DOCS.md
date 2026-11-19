# Bâtons — add-on Home Assistant

Bâtons sert à compter et dater tout ce qui compte : un café, un arrosage, une migraine, un footing…
Chaque catégorie est une tuile ; un tap ajoute un « bâton » daté. Statistiques, graphiques et chronologie
sont dans la fiche de chaque catégorie.

## Utilisation

Après l'installation, démarre l'add-on puis ouvre **Bâtons** dans la barre latérale.

- Chaque utilisateur Home Assistant a ses propres catégories et bâtons (identité transmise par Ingress).
- Les données (base SQLite) sont dans le dossier `/data` de l'add-on et sont incluses dans les
  sauvegardes Home Assistant.
- Export CSV disponible en bas de page.

## Accès direct (sans passer par Home Assistant)

Facultatif. Utile pour ouvrir Bâtons comme une application à part, par exemple sur
`https://batons.mon-domaine.fr`.

1. Dans Bâtons (depuis la barre latérale de HA) : menu du logo › **Compte** ›
   « Mot de passe pour l'accès direct ». Ton identifiant est ton **nom d'utilisateur Home Assistant**.
2. Dans l'onglet **Réseau** de l'add-on, choisis un port (par ex. `8090`) puis redémarre l'add-on.
3. Bâtons répond alors sur `http://<ip-de-home-assistant>:8090` avec une page de connexion.
   Tu y retrouves exactement les mêmes données que dans HA.

### Avec Nginx Proxy Manager (https, recommandé)

Crée un *Proxy Host* :
- *Domain Names* : `batons.mon-domaine.fr`
- *Scheme* `http`, *Forward Hostname / IP* : l'IP de Home Assistant, *Forward Port* : le port choisi (8090)
- onglet *SSL* : un certificat Let's Encrypt, *Force SSL* activé.

En https, Face ID / Touch ID fonctionnent aussi pour les éléments masqués (à enregistrer une fois
pour cette adresse, dans Confidentialité). En simple http, seul le code est possible.

Sécurité : ne publie pas le port direct sur Internet sans https devant ; un en-tête d'identité envoyé
par l'accès direct est toujours ignoré (seul HA est cru sur parole) ; 5 mots de passe faux bloquent le
compte 15 min.

## Fuseau horaire

Le fuseau horaire est celui de Home Assistant.
