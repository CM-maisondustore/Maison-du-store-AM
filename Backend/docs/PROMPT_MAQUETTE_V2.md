# Reproduire la maquette v2 des écrans de création dans l'Espace Pro

Tu travailles dans le dossier `Main du store espace pro` : Laravel 12, Blade, CSS et JS servis sans build (`public/admin.css`, `public/admin.js`). Réponds en français.

**Objectif :** amener le code au niveau de la maquette v2, écran par écran, sans casser ce qui existe ni changer ce que les formulaires enregistrent.

## La maquette

Elle est dans `docs/maquette-v2/`. Lance `python -m http.server 8766` dans ce dossier, puis ouvre http://localhost:8766 (voir `LISEZMOI.txt`). Chaque planche est un fichier `.dc.html` lisible tel quel. Les positions et titres sont dans `canvas-v2.json`, et la feuille de style est celle de l'application. Toutes les données sont fictives : n'en reprends aucune.

| Planche | Écran du code | Vues concernées |
|---|---|---|
| `Main`, `DevisMateriaux`, `DevisChiffrage`, `DevisPaiement`, `EtapesDevis` | Nouveau devis stores et rideaux, admin, en 4 étapes | `staff/quote-form`, `quote-window`, `quote-selection`, `quote-costing`, `quote-pricing-settings`, `public/quote-editor.js` |
| `DevisArticles` | Nouveau devis électroménager et meubles | `staff/quote-articles`, `quote-article-row` |
| `DevisAgent` | Relevé de l'agent, 390 px | `staff/agent-quote-form`, `agent-quote-window`, `public/agent-quote.js` |
| `Client` | Créer un client, formulaire flottant | `staff/contacts`, `contact-fields` |
| `Prestation` | Nouvelle prestation, formulaire flottant | `staff/interventions`, `planning-fields` |
| `Materiau`, `Realisation`, `Produit` | Ajouter un matériau, une réalisation, un produit | `staff/quote-options`, `quote-option-form`, `content`, `image-fields`, `product` |
| `MiseEnPage` | Éditeur libre de mise en page des PDF | `staff/document-layout`, `pdf-letterhead`, `public/document-layout.js` |
| `Navigation`, `Haut` | Barre latérale, barre du haut | `staff/layout` (déjà conformes) |

## Étape 0 : partir de l'état actuel

1. Lance `git status` et `git log -1 --oneline`. Le dépôt doit être au commit `6818dc2`.
2. Si `resources/views/staff/quote-form.blade.php` ne contient pas `data-wizard`, applique `docs/maquette-v2/correctif-devis-formulaires.patch` avec `git apply --check` puis `git apply --3way`. Ajoute `--ignore-space-change` si seules les fins de ligne bloquent. En cas de conflit réel, arrête-toi et montre-le-moi.
3. Lis `docs/ECARTS.md` en entier : il dit ce qui est déjà fait, ce qui s'écarte de la maquette et pourquoi.

## Règles à ne pas enfreindre

- **Base de données :** avant toute commande `artisan`, `php artisan config:show database.default` doit répondre `sqlite`, sinon arrête-toi. Jamais de `migrate:fresh`. Aucune migration sans mon accord : les réglages du PDF tiennent dans les colonnes JSON existantes (`communication_templates.options` et `layout_draft`).
- **Contrat :** les URL, noms de routes, attributs `name`, champs cachés (`submission_token`, `edit_version`, `calculation_token`, `tariff_form`, `version`, `target_versions[...]`, `open_form`) et ancres `#ajouter-option`, `#planifier`, `#nouveau-contact` restent identiques. Garde aussi les `id` et `data-*` lus par les scripts : cherche leurs usages dans `public/` et `tests/js/` avant de déplacer quoi que ce soit.
- **Droits :** l'agent ne voit aucun prix, total, paiement, note interne ni PDF chiffré, y compris dans le JSON `#agent-catalogue`. Les droits se vérifient côté serveur.
- **Sens métier :** un prix vide signifie « à définir », jamais 0. Un enregistrement n'envoie rien au client.
- **Calcul :** ne touche ni `QuoteCosting`, ni `Quotes/*`, ni les règles de validation, ni les PDF déjà émis, sauf pour le lot 3 et après mon accord explicite.
- **Interface :** utilise les jetons et composants de `public/admin.css`, sans style en ligne dans les vues. Sans JavaScript, les formulaires flottants s'ouvrent par ancre et s'enregistrent.
- **Git :** ni commit ni push sans demande.

## Lot 1 : aligner les écrans déjà refaits sur la maquette

Compare chaque écran à sa planche, à 1440 et 390 px, et corrige les écarts suivants sans changer les champs envoyés.

1. **Étape 2 du devis (`DevisMateriaux`) :** la maquette présente les choix par ouverture dans un tableau compact, avec une ligne par ouverture, un menu par colonne et l'état « Choix communs » ou « Personnalisée ». Le code affiche une carte par ouverture. Rapproche-toi du tableau sur grand écran ; garde les cartes sous 768 px. Le coefficient de tissu et le rail sont aujourd'hui des réglages par ouverture dans « Quantités, fournitures et prix » : affiche-les dans la ligne, en gardant leurs champs actuels.
2. **Étape 4 (`DevisPaiement`) :** ajoute le récapitulatif latéral (client, ouvertures, agent, total, lignes à définir), à partir des données déjà présentes dans le formulaire. Ne fais pas encore l'échéancier en 3 parts ni « Notre engagement » : c'est le lot 3.
3. **Devis par articles (`DevisArticles`) et relevé de l'agent (`DevisAgent`) :** vérifie la conformité, notamment les notes d'ouverture visibles et la désignation mise en avant.
4. **Nouvelle prestation (`Prestation`) :** la maquette montre le planning de l'agent ce jour-là et propose le premier créneau libre. Il faut pour cela les prestations de l'agent par jour. Propose-moi la solution la plus légère (données embarquées dans la page ou route en lecture seule, limitée à l'administrateur), avec son test de droits, avant de la construire. Toute nouvelle route se justifie dans `docs/ECARTS.md`.

## Lot 2 : l'éditeur libre de mise en page (`MiseEnPage`)

Construis l'éditeur de la planche, à la place de l'écran de réglages, pour les modèles Devis, Facture, Reçu et Style commun.

- **Écran :**
  - barre du haut : retour, nom du modèle, état (brouillon en cours, version publiée), type de document, page unique ou page suite, annuler et rétablir, zoom, scénario d'aperçu, « Enregistrer le brouillon » et « Publier le modèle » ;
  - à gauche : les blocs à placer et la liste des calques ;
  - au centre : la page A4 avec règles en millimètres, marges et grille de 5 mm aimantée ;
  - à droite : les propriétés du bloc choisi.
- **Blocs :**
  - logo ou image, texte libre, coordonnées de l'entreprise, titre et numéro, bloc client, objet ;
  - tableau des désignations, total général, conditions de paiement, « Notre engagement » ;
  - signature du client, cachet, pied de page, ligne ou cadre.
- **Propriétés d'un bloc :**
  - position et taille en millimètres, alignement, verrouillage ;
  - police (DejaVu Sans ou Serif), taille, couleur, fond, bordure ;
  - pages où il apparaît (page unique ou dernière, pages suite) ;
  - données à insérer dans un texte libre : `{client.nom}`, `{client.telephone}`, `{devis.numero}`, `{devis.date}`, `{total.general}`, `{acompte}`, `{reste_a_payer}`.
- **Règles du devis à respecter** (journal MDS Sambou) :
  - une seule page, sans notes ;
  - toutes les désignations, puis la pose et livraison et le total juste en dessous ;
  - un devis trop long continue sur une page « suite » qui reprend l'en-tête ;
  - les conditions de paiement affichent les échéances, l'acompte reçu et le reste à payer.
- **Enregistrement :**
  - les blocs vont dans les options du modèle (`options.blocks`, positions en millimètres), avec le brouillon, la publication, les versions et « Reprendre en brouillon » actuels ;
  - publier s'applique aux prochains documents uniquement ;
  - les PDF déjà émis et archivés restent inchangés ;
  - un modèle sans `blocks` garde le rendu actuel.
- **Rendu :**
  - ajoute un rendu PDF par blocs à côté du rendu actuel, sans remplacer celui-ci, avec des positions absolues en millimètres dans la vue PDF existante ;
  - l'aperçu passe par la route `layouts.preview` existante ;
  - le « Total général » doit être lisible : texte clair sur fond sapin.
- **Accessibilité :** chaque bloc se sélectionne aussi au clavier et depuis la liste des calques. Les flèches déplacent le bloc de 1 mm, et de 5 mm avec Maj.
- **Tests :** enregistrement et publication des blocs, refus pour un agent, page suite au-delà d'une page, rendu inchangé pour un modèle sans blocs.

Avant de coder ce lot, présente-moi en quelques lignes le format de `options.blocks` et la façon dont le rendu PDF le lit.

## Lot 3, seulement après mon accord : échéancier et « Notre engagement »

La planche `DevisPaiement` et le journal prévoient 70 / 30 jusqu'à 500 000 F, et 50 / 30 / 20 au-delà : validation du chantier, installation des rails, livraison et pose. Elle prévoit aussi la mention « Notre engagement » sous les conditions de paiement. Le code ne connaît qu'un acompte unique, en pourcentage. Ce lot change le calcul, le stockage du devis et le PDF : décris-moi d'abord ce qu'il faut modifier, les données touchées et les tests, puis attends ma réponse.

## Méthode et vérifications

Avance lot par lot, écran par écran. Pour chacun :

1. Lis la vue, ses partiels, son contrôleur, ses scripts et ses tests, puis la planche.
2. Fais des captures avant et après à 1440, 1024, 768, 390 et 360 px, en clair et en sombre.
3. Teste dans un vrai navigateur (Playwright avec Edge) un enregistrement réussi et un enregistrement en erreur. Teste toujours sur une copie de `database/database.sqlite`, avec un serveur lancé sur cette copie (`DB_DATABASE`), jamais sur la base locale.
4. Lance `composer test` (référence : 173 réussis sur 176 ; les 3 erreurs de `PdfLetterheadTest` viennent de l'absence de JPEG dans le PHP local) et `npm test` (17 sur 17). Aucun nouvel échec n'est admis. Ajoute à `tests/Feature/StaffInterfaceTest.php` un test pour tout nouveau comportement côté serveur, et un test de droits pour toute nouvelle route.
5. **Devis de référence à refaire après chaque lot qui touche au devis :**
   - client quelconque ;
   - Salon 240 × 260 cm, 2 exemplaires ; Chambre 180 × 220 cm ;
   - choix communs : Rideaux, premier tissu rideau, premier voilage, première finition ;
   - acompte 60 %, remise de 5 %, taxe 18 %.

   Avec le catalogue de démonstration d'origine, il doit donner 443 916 FCFA de total, 266 350 d'acompte et 177 566 de solde.
6. Aucun débordement horizontal, aucune erreur console, cibles tactiles de 40 px au minimum, texte des champs à 16 px sur téléphone, focus visible, thème sombre et « Réduire les animations » respectés.

## Compte rendu

Écran par écran : ce qui a changé, ce qui s'écarte encore de la maquette et pourquoi, les résultats exacts des tests, et ce qui n'a pas pu être vérifié. Mets à jour `docs/ECARTS.md`. Signale toute contradiction entre la maquette, ce document et le code au lieu de la trancher seul : le code et les tests font référence.
