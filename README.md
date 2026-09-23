# Maison du Store AM — Stores &amp; Rideaux Sur Mesure

**Production prévue :** `www.maisondustoream.com` sur Hostinger Web/Cloud. Voir [le guide Hostinger](deploy/hostinger/README.md) pour publier l’application Laravel et [Backend/README.md](Backend/README.md) pour le développement. Dépôt cible : `CM-maisondustore/maison-du-store-am`.

> Le backend Laravel est désormais implémenté dans [Backend](Backend/README.md) : catalogue en base, devis et rendez-vous persistés, validation et protection CSRF. Pour l’utiliser sur cette machine, lancer `.\Backend\serve.ps1`, puis ouvrir http://127.0.0.1:8000. Les explications de mode démonstration ci-dessous concernent uniquement les pages HTML statiques.

Site vitrine statique (HTML5 / CSS3 / JavaScript vanilla) pour Maison du
Store AM, entreprise de stores et rideaux sur mesure basée à Cocody —
Marché Saint Jean, Abidjan, Côte d'Ivoire.

Construit à partir de la maquette Figma fournie, avec toutes les images
remplacées par des emplacements vides (`.image-placeholder`) et tous les
prix affichés sous la forme exacte **« Prix »**, comme demandé.

## Démarrer

Aucune installation n'est nécessaire : le site ne dépend d'aucun
framework ni d'aucune étape de build.

- **Le plus simple** : double-cliquer sur `index.html` (l'ouverture directe
  du fichier fonctionne, y compris hors connexion).
- **Recommandé pour le développement** : servir le dossier avec un petit
  serveur local, pour que les chemins relatifs et une éventuelle future
  API se comportent comme en production :
  ```bash
  cd store-rideaux
  python3 -m http.server 8000
  # puis ouvrir http://localhost:8000
  ```

Deux pages : `index.html` (vitrine complète, catalogue et rendez-vous) et
`devis.html` (parcours de devis Store / Rideau).

## Arborescence

```text
store-rideaux/
├── index.html              Accueil : hero, services, catalogue, réalisations,
│                            processus, rendez-vous, contact
├── devis.html               Parcours de devis (Store et Rideau, indépendants)
├── css/                      Une feuille par responsabilité (voir le prompt d'origine)
├── js/
│   ├── app.js                 Point d'entrée : initialise les modules présents sur la page
│   ├── navigation.js           Liens internes, boutons devis/rendez-vous, retour catalogue
│   ├── mobile-menu.js           Menu hamburger (ouverture/fermeture uniquement)
│   ├── catalogue/                Données, catégories, recherche, filtres, assemblage, détail produit
│   ├── rendez-vous/               Calendrier, créneaux, validation, orchestration
│   ├── devis/                      Dimensions, devis Store, devis Rideau, validation, récapitulatif, orchestration
│   └── utils/                      validation.js, notifications.js, modal.js, api.js
├── assets/
│   ├── fonts/                Cormorant Garamond, Inter, IBM Plex Mono (hébergées localement)
│   ├── icons/                 Icônes SVG (trait, sans remplissage) + favicon
│   └── placeholders/            SVG de secours pour les emplacements d'image
└── README.md
```

Chaque fichier JavaScript a une seule responsabilité, comme demandé dans le
prompt d'origine ; aucune logique n'est regroupée dans un `main.js`
générique. Deux fichiers ont été ajoutés à l'arborescence de base pour que
cette règle reste vraie :

- **`js/catalogue/catalogue.js`** — assemble recherche, catégorie et filtres
  pour construire la grille, le compteur et la pagination. Sans lui, cette
  logique de composition aurait dû aller dans `app.js` ou être dupliquée.
- **`js/utils/api.js`** — point unique d'appel au futur backend (voir
  ci-dessous). Sans lui, chaque module de devis ou de rendez-vous aurait
  appelé `fetch()` séparément.

## Mode démonstration et backend Laravel

Le site fonctionne aujourd'hui entièrement en local : le catalogue vient de
`js/catalogue/products.js`, et les formulaires (devis, rendez-vous)
n'envoient rien — ils simulent un succès après un court délai et
l'affichent clairement comme tel (« mode démonstration »), avec un lien
`mailto:` de secours vers `maisondustore.ci@gmail.com`.

Pour utiliser le frontend statique avec une API Laravel :

1. Ouvrir `js/utils/api.js`.
2. Passer `enabled: true` dans `config.api` (et ajuster `baseUrl` si
   l'API n'est pas servie depuis la même origine).
3. Les quatre points d'entrée sont déjà prêts côté frontend :
   - `GET  /api/products/`
   - `POST /api/quotes/stores/`
   - `POST /api/quotes/curtains/`
   - `POST /api/appointments/`

Aucun autre fichier n'a besoin d'être modifié : tous les modules
(catalogue, devis, rendez-vous) passent déjà par `MDS.api`.

La version directement intégrable à Laravel se trouve dans `Backend/` :
elle contient les vues Blade, les sources Vite, les URLs Laravel et la
protection CSRF. Le design et le contenu sont identiques à cette version
statique.

## Points d'attention

- **Contraste du bouton « or ».** Le texte des boutons `.btn--gold` est
  volontairement sombre (`--color-on-gold: #1c1c1c`) plutôt que blanc comme
  sur la maquette, le blanc sur `#C09F6A` n'atteignant pas un contraste
  suffisant. Pour revenir au rendu visuel exact de la maquette, changer
  cette seule variable dans `css/variables.css`.
- **Horaires et réseaux sociaux.** Non fournis dans le prompt d'origine, ils
  n'apparaissent donc nulle part (plutôt que d'être inventés). Le calendrier
  de rendez-vous (`js/rendez-vous/calendar.js`) ferme uniquement le
  dimanche par défaut — à ajuster dans `CONFIG.closedWeekdays` si les
  horaires réels sont différents.
- **Aucune dépendance externe.** Polices et icônes sont toutes hébergées
  dans `assets/`, sans appel à un CDN : le site s'ouvre aussi hors ligne.

## Tests effectués

Le site a été vérifié avec Chromium (Playwright) :

- aucune erreur console sur `index.html` et `devis.html` ;
- aucun défilement horizontal aux largeurs 1440 / 768 / 390 px ;
- parcours complets testés de bout en bout : devis Store (7 étapes) et
  devis Rideau (9 étapes), y compris validation des champs, révélation
  conditionnelle (alimentation motorisée), édition depuis le
  récapitulatif, et confirmation finale ;
- calendrier et créneaux de rendez-vous, recherche, filtres, catégories,
  pagination, fiche produit en modale, menu mobile.
