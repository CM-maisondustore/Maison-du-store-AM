# Refonte de l'interface de 7 écrans de création de l'espace pro

Tu travailles dans le dossier `Main du store espace pro` : Laravel 12, Blade, CSS et JS servis sans build (`public/admin.css`, `public/admin.js`). Ces écrans fonctionnent déjà. Ils sont trop longs, trop denses et peu guidés.

**Objectif :** refaire leur interface pour qu'on les remplisse vite, sans erreur, sur ordinateur comme sur téléphone, sans changer ce qu'ils enregistrent.

## Écrans concernés

Ouvre chaque écran avec `demo-admin` / `Demo-Maison-2026` (`demo-agent` pour la variante agent), sur http://127.0.0.1:8001.

| # | Écran | Adresse actuelle | Vue | Enregistrement | Référence maquette |
|---|---|---|---|---|---|
| 1 | Ajouter un matériau | `/admin/options-devis#ajouter-option` | `staff/quote-options` | `POST quote-options.store` | `ProMateriaux` |
| 2 | Nouvelle prestation | `/admin/prestations#planifier` | `staff/interventions`, `staff/planning-fields` | `POST interventions.store` | `ProPrestations` |
| 3 | Créer un client | `/admin/contacts#nouveau-contact` | `staff/contacts`, `staff/contact-fields` | `POST contacts.store` | `ProClients`, `ProClientsMobile` |
| 4 | Ajouter un produit | `/admin/products/create` | `staff/product`, `staff/image-fields` | `POST products.store` | Aucune : s'inspirer de `ProCatalogue` |
| 5 | Ajouter une réalisation | `/admin/contents/create` | `staff/content`, `staff/image-fields` | `POST contents.store` | `ProImages` |
| 6 | Mise en page | `/admin/mise-en-page/{type?}` | `staff/document-layout`, `staff/pdf-letterhead` | `POST layouts.save`, `layouts.preview`, `layouts.restore` | Aucune : bloc « Modèles » de `ProAutomatisations` |
| 7 | Nouveau devis | `/admin/devis/nouveau`, `?boutique=electromenager`, `?boutique=meubles` | `staff/quote-form`, `staff/agent-quote-form`, `staff/quote-articles`, `staff/partials/boutique-picker` | `POST quotes.store`, `quotes.calculate` | `ProDevisNew`, `ProDevisNewAgent`, `ProDevisNewMobile`, `ProDevisNewMobileAdmin`, `ProDevisForm` |

Les écrans 1, 2 et 3 n'ont pas de page propre : le formulaire est replié dans la liste et s'ouvre par une ancre.

## Référence visuelle

La maquette est dans `docs/maquette/`. Lance `python -m http.server 8765` dans ce dossier puis ouvre http://localhost:8765 (voir `LISEZMOI.txt`). Elle prime sur tout choix personnel, en particulier `ProComponents` et `ProTokens`. Les données et coordonnées qu'elle contient sont fictives : ne les reprends jamais.

Réutilise les jetons et composants de `public/admin.css` : couleurs `--pro-*`, polices (Inter, Cormorant Garamond, IBM Plex Mono), `.panel`, `.tabs`, `.chips`, `.segmented`, `.tile`, `.action-bar`, `.notice`, `.badge`, `.step`, `.toc`. N'ajoute un composant que s'il manque, et jamais de style en ligne. Il n'y a ni framework ni étape de build.

## Règles à ne pas enfreindre

- **Contrat :** les URL, noms de routes, attributs `name`, champs cachés (`submission_token`, `edit_version`, `calculation_token`, `tariff_form`, `version`, `target_versions[...]`, paires `hidden` à `0` + case à cocher) et ancres `#ajouter-option`, `#planifier`, `#nouveau-contact` restent identiques. Ces ancres sont utilisées par le menu « Nouveau » de `staff/layout` et par les fiches client, devis et rendez-vous. Toute nouvelle route doit être justifiée dans `docs/ECARTS.md`.
- **Données :** aucune migration. Ne change ni les contrôleurs de calcul, ni `QuoteCosting`, ni les règles de validation, ni les PDF déjà émis.
- **Droits :** ils sont vérifiés côté serveur, masquer un élément ne suffit jamais. L'agent ne voit aucun prix, total, paiement, note interne ni PDF chiffré. Vérifie aussi le JSON embarqué (`#agent-catalogue`).
- **Sens métier :** un prix vide signifie « à définir », jamais 0 et jamais gratuit. Le consentement aux promotions exige son origine (`consent_source`). Un enregistrement ne déclenche aucun envoi.
- **Pages internes :** `no-store`, non indexables, aucun pixel Meta.
- **En cas de contradiction :** signale-la. Le code et les tests font référence, ne la corrige pas de ta propre initiative.
- **Git :** ni commit ni push sans demande.

## Attention à la base de données

Avant toute commande `artisan`, lance `php artisan config:show database.default`. La réponse doit être `sqlite`. Si elle est différente, arrête-toi : un terminal VS Code ouvert sur l'autre projet peut injecter les variables de la base `mds-test`, et un `migrate:fresh` a déjà effacé cette base ainsi. Ne lance jamais `migrate:fresh` sur la base locale sans le demander : teste sur une copie de `database/database.sqlite`.

## Principes communs aux 7 écrans

1. **Une seule colonne de saisie**, regroupée par étapes dans l'ordre où l'information arrive sur le terrain. Les réglages rares sont repliés, mais une section repliée contenant une erreur s'ouvre d'elle-même.
2. **Un bouton principal toujours accessible**, dans `.action-bar`, dont le libellé dit ce qui se passe. Garde le même mot partout : aujourd'hui « Créer un client », « Nouveau contact », « Créer un contact » et « Créer le dossier » désignent la même action.
3. **Erreurs :** résumé en haut (`staff.feedback`), message sous le champ concerné, `aria-invalid`, focus sur le premier champ fautif. La saisie est conservée via `old()`, sauf les fichiers, que l'interface demande de resélectionner.
4. **Champs adaptés :** unité visible (`cm`, `FCFA`), état « À définir » de `ProComponents`, listes filtrées selon les choix déjà faits.
5. **Mobile :** à partir de 360 px, cibles de 44 px minimum, aucun débordement horizontal, `.action-bar` et onglets du bas sans chevauchement. Le texte d'un champ est à 16 px pour éviter le zoom sur iPhone.
6. **Accessibilité :** clavier complet, focus visible, chaque champ a son `label`, l'état n'est jamais porté par la seule couleur. Thème sombre et « Réduire les animations » respectés.
7. **Sans JavaScript :** l'ouverture par ancre et l'enregistrement restent possibles sur les écrans 1 à 5. Les écrans 6 et 7 affichent leur message actuel.

## Consignes par écran

### 1. Ajouter un matériau

Présente la création dans un panneau latéral comme `ProMateriaux` à partir de 1200 px, et en pleine largeur en dessous. Le formulaire de modification de chaque option garde sa route `PUT`.

Défauts du code actuel à corriger dans la vue :
- le formulaire n'utilise pas `old()` et ne se rouvre pas après une erreur : la saisie est perdue. Plusieurs formulaires partagent la page, donc rouvre uniquement celui qui a échoué ;
- les finitions compatibles sont calculées pour la catégorie affichée au chargement (`rideau`). En passant à `store`, on peut cocher une finition rideau, que le serveur refuse. Filtre finitions et produits associés selon la catégorie choisie ;
- le groupe de tissu doit correspondre à la catégorie pour un matériau : ne propose que les valeurs valides ;
- la nature (`kind`) décide des champs utiles : n'affiche le groupe de tissu que pour un matériau, et les finitions que pour un rail, une barre ou un accessoire.

Le serveur n'accepte que les catégories `rideau` et `store`. N'ajoute pas l'électroménager ou les meubles ici sans validation : signale-le seulement.

### 2. Nouvelle prestation

Ordre proposé : client, type de prestation, agent et créneau, adresse et consignes, liens optionnels.
- Le serveur refuse un devis ou un rendez-vous d'un autre client : filtre ces deux listes selon le client choisi.
- Préremplis l'adresse à partir du client quand elle est vide, sans écraser une saisie.
- Conserve le préremplissage par `?contact_id=`, `?quote_id=`, `?appointment_id=` et l'ouverture automatique.
- Le chevauchement d'agent est refusé par le serveur : affiche ce message près du créneau.
- « Créer un client » quitte aujourd'hui le formulaire et perd la saisie : ouvre ce lien dans un nouvel onglet ou préviens avant de quitter.
- L'agent ne voit jamais ce formulaire.

### 3. Créer un client

Commence par la catégorie (client, fournisseur, partenaire) puis les coordonnées.
- Le champ `consent_source` n'apparaît que si le consentement est coché, et il est obligatoire dans ce cas.
- Les notes internes sont visuellement séparées des coordonnées.
- Un e-mail déjà utilisé est refusé par le serveur : le message doit le dire clairement.
- `staff/contact-fields` sert aussi à la fiche client : vérifie la modification et la case « Dossier archivé ».

### 4. Ajouter un produit

Découpe en étapes : fiche, image, disponibilité, caractéristiques et coloris, coûts internes.
- Les libellés restent ceux du formulaire actuel. Le type (`store`, `rideau`, `electromenager`, `meuble`) et l'univers doivent rester cohérents : le choix de l'un guide l'autre.
- Les coûts sont réservés à l'administrateur. Vide signifie « Non renseigné », `0` signifie coût nul, et la différence reste lisible.
- Présente les coloris en pastilles cochables et remplace si possible la saisie libre de `specs_text` par une aide claire, sans changer le champ envoyé.
- Le prix relevé indicatif reste signalé comme n'étant pas un prix de vente.
- La vue sert aussi à la modification (`products.edit`).

### 5. Ajouter une réalisation

Présente-la comme une carte de portfolio : image d'abord avec aperçu immédiat, puis titre, description, ordre, visibilité.
- Le texte alternatif est mis en avant, comme dans `ProImages`.
- Le bouton dit « Enregistrer la réalisation » à la création.
- La vue `staff/content` sert aussi aux contenus `hero`, `contact` et `quote` : garde leurs libellés et leurs champs, et le `position` caché quand il l'est aujourd'hui. Ne modifie pas `kind` ni `slot`, que le serveur impose.

### 6. Mise en page

Écran de réglage et non de création : l'aperçu A4 doit rester visible pendant la saisie.
- Les boutons « Enregistrer le brouillon » et « Publier le modèle » restent accessibles sans remonter (`name="action"`).
- Montre clairement l'état : brouillon en cours, version publiée, style commun hérité ou personnalisé.
- Regroupe les réglages en onglets ou sections courtes, en gardant tous les champs dans `#layout-form` : `document-layout.js` envoie tout le formulaire à l'aperçu.
- Garde les identifiants `layout-preview`, `preview-status`, `preview-download`, `refresh-preview` et le comportement de `pdf-letterhead.js`.
- Sur des captures récentes à 1440 px (Edge sans interface), le PDF paraît coupé dans son cadre. Vérifie-le dans un vrai navigateur et, si c'est confirmé, fais-le tenir en largeur.
- Publier s'applique aux prochains documents uniquement : dis-le à côté du bouton.

### 7. Nouveau devis

Ce sont trois formulaires et deux rôles. Le sélecteur de boutique passe par `?boutique=` et reste en tête :
- **Stores et rideaux, admin** (`quote-form`, avec `quote-editor.js`) : c'est le plus lourd. Garde les étapes et le sommaire latéral, marque l'étape courante et complète, affiche le total en continu près de l'action si le calcul a déjà eu lieu. Rends chaque ouverture repliable avec ses mesures en résumé (`ProComponents`, carte fenêtre).
- **Stores et rideaux, agent** (`agent-quote-form`, avec `agent-quote.js`) : formulaire de terrain à une main, gros champs numériques, brouillon local conservé. Aucun prix.
- **Électroménager et meubles** (`quote-articles`) : liste d'articles et services de livraison, installation ou montage. Le rôle agent n'affiche aucun prix.

Conserve tous les `id` et attributs `data-*` lus par les scripts (`quote-windows`, `quote-window-template`, `add-quote-window`, `calculate-quote`, `calculation-status`, `lines`, `lines-template`, `quote-financial-result`, `quote-catalogue-data`, `data-window`, `data-selection`, `data-inherit`, `data-remove-window`, `agent-openings`, `agent-opening-template`, `agent-add-opening`, `agent-catalogue`, `data-agent-window`, `data-agent-selection`, `articles`, `articles-template`, `add-article`, `article-search`, `data-article-name`, `data-remove-article`). Avant de déplacer un élément, cherche ses usages dans `public/` et `tests/js/`. Le formulaire sert aussi à la modification d'un devis existant : vérifie `/admin/devis/{id}/formulaire`.

## Méthode

Avance écran par écran, dans l'ordre 3, 5, 2, 1, 4, 6, 7 : du plus simple au plus risqué. Pour chacun :
1. lis la vue, ses partiels, son contrôleur, son JS et ses tests, puis regarde la maquette ;
2. fais une capture avant à 1440 et 390 px ;
3. refais l'interface, sans casser les autres écrans qui partagent les partiels (`image-fields`, `contact-fields`, `planning-fields`, `quote-phone`) ;
4. vérifie à l'écran un enregistrement réussi et un enregistrement en erreur, puis la capture après à 1440, 1024, 768, 390 et 360 px, en clair et en sombre ;
5. fais passer les tests, puis résume ce qui a changé avant l'écran suivant.

## Vérifications obligatoires

- `composer test` : à ce jour 168 tests réussis sur 171. Les 3 erreurs de `PdfLetterheadTest` viennent de l'absence de JPEG dans le PHP local. Aucun nouvel échec n'est admis. Si un test vérifie un texte que tu changes, signale-le et garde le texte, sauf accord.
- `npm test` : 17 sur 17.
- Ajoute à `tests/Feature/StaffInterfaceTest.php` un test pour tout nouveau comportement côté serveur, et un test de droits pour tout nouvel écran ou nouvelle route.
- Parcours réel dans le navigateur (Playwright avec Edge) sur les 7 écrans, comme administrateur, et comme agent sur l'écran 7 : aucun débordement, aucune erreur console, cibles tactiles de 40 px minimum, enregistrement réussi.
- Compare un devis calculé avant et après la refonte : montants identiques au franc près.

## Compte rendu final

Donne, écran par écran : ce qui a changé, ce qui s'écarte de la maquette et pourquoi, les résultats exacts des tests, et ce qui n'a pas pu être vérifié. Note les écarts durables dans `docs/ECARTS.md`.
