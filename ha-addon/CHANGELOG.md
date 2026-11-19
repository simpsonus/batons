# Journal des modifications

## 0.13.1
- « Continuer » (jeux et livres) liste **tous** les titres en cours, même commencés il y a longtemps,
  chacun avec ▶ et 🏁. Seuls disparaissent les titres terminés, et ceux jamais marqués « commencé »
  sans activité depuis 3 mois.

## 0.13.0
- Option **« Suivre des périodes »** sur les catégories simples (un rhume, une cure…) : un tap sur la tuile
  commence la période, le suivant la termine ; la tuile affiche « En cours depuis 3 jours ».
- Fiche de la catégorie : liste des périodes avec leur durée, durée moyenne et la plus longue ;
  le calendrier colore tous les jours couverts. Dates passées : « Le début » / « La fin » à l'ajout ou à la modification.
- Jeux et livres : bouton **🏁** directement dans « Continuer » pour marquer la fin sans ouvrir la fiche.
- Dates courtes corrigées (« 14 nov. » au lieu de « 14 nove. »).

## 0.12.0
- Livres : **date de début et de fin de lecture**, comme pour les jeux. « Commencé aujourd'hui » /
  « 🏁 Lu aujourd'hui » sur la fiche du livre, ou n'importe quelle date à l'ajout ou en modifiant le bâton.
- Durée (« Lu en 18 jours », « En cours depuis 5 jours ») et durée moyenne des livres lus.
- Un livre en cours de lecture reste dans « Continuer » ; le tome suivant reste proposé après un « Lu ».

## 0.11.0
- Jeux vidéo : **date de début et date de fin**. « Commencé aujourd'hui » / « 🏁 Terminé aujourd'hui »
  sur la fiche du jeu, ou n'importe quelle date via « + Ajouter une partie… » ou en modifiant le bâton.
- Durée affichée (« Terminé en 47 jours », « En cours depuis 12 jours ») et durée moyenne des jeux terminés.
- Les jeux terminés sortent de « Continuer » ; « Recommencer » pour une nouvelle partie complète.
- Export CSV : nouvelle colonne « etape » (debut / fin), reprise à l'import.

## 0.10.0
- Nouveau type de catégorie **Jeux vidéo** : recherche RAWG (clé gratuite dans Compte), plateformes,
  « ▶ Partie » en un tap, note sur 10 ; saisie manuelle possible sans clé.
- **Fiche œuvre** : historique de l'œuvre, ▶ suivant, « + Ajouter un épisode / une lecture / un visionnage / une partie… ».
- Dans les listes, le titre ouvre la fiche ; le crayon ne sert plus qu'à modifier un bâton existant
  (bouton « Mettre à jour »).

## 0.9.0
- Catégories **œuvre** : Séries, Films, Livres. Recherche dans un catalogue (TMDB, TVmaze, Google Books,
  Open Library), épisode ou tome suivant proposé en un tap, note sur 10, affiches.
- Clé TMDB réglable dans Compte › Catalogues.
- Export CSV et réimport incluant les œuvres.

## 0.8.2
- Calendrier : plus d'ascenseur horizontal sur ordinateur.

## 0.8.1
- Icône et logo de l'add-on, icône de barre latérale « bâtons barrés », icônes d'écran d'accueil iPhone.

## 0.8.0
- **Vue calendrier** dans la fiche catégorie (52 semaines × 7 jours, intensité selon le nombre de bâtons).

## 0.7.1
- Titres « Catégories » et « Chronologie » masqués visuellement (ils doublaient l'onglet actif).

## 0.7.0
- **Accès direct** facultatif par un port (onglet Réseau), avec page de connexion ; même identifiant que
  dans Home Assistant et mot de passe défini dans Compte : mêmes données.
- Compatible Nginx Proxy Manager (voir Documentation).

## 0.6.0
- **Catégories et groupes masqués**, déverrouillage par code à 6 chiffres ou Face ID / Touch ID,
  reverrouillage automatique après 5 min d'inactivité.

## 0.5.0
- Menu sur le logo (Archives, Importer, Exporter).
- Réorganisation des groupes entre eux et des catégories dans leur groupe.

## 0.4.0
- Boutons-icônes Modifier / Supprimer, passe complète responsive et accessibilité.

## 0.3.0
- **Import** des exports CSV de Chronicling (catégories, groupes, événements) et de Bâtons, sans doublons.

## 0.2.0
- Icônes Material Design avec recherche ; plus de sélection de texte sur appui long (iPhone).

## 0.1.0
- Première version : catégories, groupes, bâtons, statistiques, graphique, chronologie, archives, export CSV.
