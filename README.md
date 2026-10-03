# Maison du Store AM — Stores &amp; Rideaux Sur Mesure

**Production en ligne :** [www.maisondustoream.com](https://www.maisondustoream.com), sur Hostinger Web/Cloud avec MySQL. Les devis et rendez-vous sont enregistrés en base. Voir [le guide Hostinger](deploy/hostinger/README.md) pour les mises à jour et [Backend/README.md](Backend/README.md) pour le développement. Dépôt privé : [CM-maisondustore/Maison-du-store-AM](https://github.com/CM-maisondustore/Maison-du-store-AM).

**Espace interne :** `/admin/connexion`, réservé aux rôles Admin-CM et Agent. Gestion du catalogue, des dossiers clients, devis, rendez-vous, prestations, factures et échanges commerciaux. Voir [le guide d’utilisation et de configuration](Backend/ADMIN.md). Aucun compte client public.

**Préparation des devis :** rideaux et voilages sélectionnés séparément, stores, fournitures à cocher et finitions modifiables en base. L’agent relève les mesures et prépare les choix ; Admin-CM seul consulte et modifie les montants et les PDF chiffrés. L’e-mail client est facultatif. Les 14 anciens produits sont archivés et ne sont plus proposés.

**Modifier l’accueil en ligne :** les textes fixes et les liens se trouvent dans [Backend/resources/views/home.blade.php](Backend/resources/views/home.blade.php). `index.html` est la maquette locale : ses modifications doivent être reportées dans cette vue, puis publiées avec la procédure Hostinger. Les images et textes du hero et du portfolio se modifient dans l’espace pro.

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

La maquette HTML à la racine permet de consulter la présentation en local.
Le configurateur de devis utilise exclusivement les produits, matériaux et
finitions de la base : utiliser la page `/devis` servie par Laravel pour le tester.
L'ouverture directe de `devis.html` ne charge pas ces choix et ne permet pas
d'enregistrer un devis réel.

Pour utiliser le frontend statique avec une API Laravel :

1. Ouvrir `js/utils/api.js`.
2. Passer `enabled: true` dans `config.api` (et ajuster `baseUrl` si
   l'API n'est pas servie depuis la même origine).
3. Les quatre points d'entrée sont déjà prêts côté frontend :
   - `GET  /api/products/`
   - `POST /api/quotes/stores/`
   - `POST /api/quotes/curtains/`
   - `POST /api/appointments/`

Les modules passent par `MDS.api`. Pour le configurateur, la vue Laravel injecte
aussi les produits et les options disponibles, sans leurs coûts internes.

La version directement intégrable à Laravel se trouve dans `Backend/` :
elle contient les vues Blade, les sources Vite, les URLs Laravel et la
protection CSRF. Le design et le contenu sont identiques à cette version
statique.

### Catalogue et illustrations du devis

- Dans **Catalogue**, publier les produits disponibles et leur ajouter une photo.
- Dans **Matériaux & finitions**, activer les choix proposés au formulaire public.
  Les produits associés limitent leur compatibilité et fournissent leur miniature ;
  sans association, une photo de la même catégorie est utilisée si disponible.
- Dans **Images & portfolio**, modifier les deux éléments **Carte — démarrer un
  devis** pour les images, titres et descriptions des cartes Store et Rideaux.
- Les images sont recadrées automatiquement dans le formulaire client. Les photos
  propres aux tissus et voilages se modifient dans **Catalogue → Matériaux & finitions**.
  Les formulaires professionnels n’affichent pas de miniatures.
- Les cartes publiques **Wave**, **Ruflette** et **Barre de fer**, ainsi que les
  messages du récapitulatif Store / Rideaux, se modifient dans **Images & portfolio**.
- Le client peut choisir un tissu, un voilage, ou les deux. Les accessoires techniques
  et les motifs ne sont plus demandés. Le coloris définitif est confirmé sur place.
- L’agent voit uniquement les devis dont il est l’auteur. Son formulaire conserve
  les coordonnées, les dimensions et descriptions des ouvertures, le type de projet,
  la motorisation, la finition, la zone et les notes de terrain. L’administrateur
  complète les matériaux, quantités, coûts, dates et affectations. Les modifications
  du relevé conservent le chiffrage existant et imposent sa vérification avant validation.
- Les devis Store et Rideaux demandent **Motorisé : oui ou non**. Le choix est
  enregistré avec le devis, sans demander l'alimentation.
- Les formulaires client, agent et administrateur proposent un champ **Pays / indicatif**
  avant le téléphone, avec **+225** par défaut et des suggestions de pays. Le numéro
  est enregistré au format international ; un numéro déjà préfixé ne reçoit pas
  un second indicatif. Les coordonnées restent modifiables dans l'espace pro.
  Les cartes du catalogue n'affichent plus « Prix » et **Nous contacter** ouvre
  WhatsApp au **+225 07 12 44 93 19**.

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
- parcours de devis avec validation des choix en base, compatibilité des
  matériaux et finitions, et récapitulatif ;
- miniatures et cartes de démarrage, motorisation sans alimentation,
  affichage mobile et accès aux illustrations par les agents ;
- calendrier et créneaux de rendez-vous, recherche, filtres, catégories,
  pagination, fiche produit en modale, menu mobile.
