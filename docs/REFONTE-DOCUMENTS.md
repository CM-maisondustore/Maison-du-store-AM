# Mise en page et documents commerciaux

L’espace Admin-CM propose `/admin/mise-en-page` : style commun, devis, factures et reçus. Les agents ne peuvent accéder ni aux réglages ni aux PDF financiers.

## Utilisation

1. Ouvrir **Mise en page → Tous les documents**. Les images et marges de l’ancien modèle de devis sont reprises à l’installation lorsqu’elles existent.
2. Choisir logo, couleurs, police, marges, tableaux, totaux, signatures et coordonnées. Si le filigrane contient déjà le logo, désactiver le logo indépendant.
3. Vérifier le véritable PDF dans l’aperçu A4. Les exemples sont fictifs : une ouverture, quatre fenêtres, 8/20/30 ouvertures, store, remise, négociation, exemplaires individuels, facture et reçu.
4. **Enregistrer le brouillon** ne change pas le modèle actif. **Publier le modèle** active les réglages pour les prochaines éditions. La publication commune peut faire hériter les trois types du même style ; chaque type peut désactiver cet héritage.
5. L’historique permet de reprendre une ancienne publication en brouillon avant de la republier. Un contrôle de version empêche d’écraser une modification concurrente.

Les fonds existants restent compatibles : image standard sur page unique/dernière page, en-tête seul sur les précédentes. Les marges s’appliquent au modèle premium même sans fond. Le texte du pied de document et les signatures sont placés à la fin ; les numéros de page sont sous la zone de contenu. Vérifier les marges avec les images officielles réellement importées.

Dans un devis, **Présentation du document et éditions archivées** permet de saisir l’objet et les pièces des ouvertures sans recalculer le devis. Les devis acceptés ou facturés restent verrouillés. Les éditions conservées sont téléchargeables dans cette section. Chaque paiement non annulé propose un reçu PDF ; un reçu déjà émis reste accessible dans l’historique même après annulation de sa saisie, signalée dans l’interface.

## Architecture et fichiers

- `Backend/app/Services/Documents.php` reste le seul moteur Dompdf. Il sélectionne le rendu historique ou le rendu premium selon `renderer_version`, conserve les deux passes des filigranes et libère les ressources de rendu.
- `DocumentLayout.php` : paramètres, validation, modèle publié et historique. `DocumentLayoutController.php` : éditeur, aperçu, publication, historique et téléchargements administrateur.
- `DocumentViewModel.php` : formatage des montants enregistrés et préparation des groupes d’ouvertures. Aucun accès au catalogue ni nouveau calcul de prix.
- `DocumentIssues.php` et `DocumentIssue.php` : éditions immuables des devis et reçus. Les messages e-mail et les partages WhatsApp déclarés utilisent les PDF conservés.
- `DocumentAssets.php` : images privées nommées par SHA-256 via Laravel Storage. Les anciennes images base64 restent lisibles ; aucun fichier historique n’est supprimé ou remplacé.
- `DocumentPreview.php` : données fictives, calculées avec le moteur existant avant rendu. Le scénario quatre fenêtres doit afficher 241 875 + 30 000 = 271 875 F CFA, acompte 190 313, solde 81 562.
- `resources/views/documents/pdf.blade.php` et ses composants : identité, client, synthèse, ouvertures, tableau, frais/totaux, conditions, reçu, signatures et pied de document. Les styles Dompdf sont centralisés dans `partials/styles.blade.php`.
- `resources/views/staff/document-layout.blade.php`, `document-history.blade.php`, `public/document-layout.js` et `.css` : interface et aperçu différé sans enregistrement.
- Contrôleurs de communication/facture/message et services de communication : raccordement à la publication et à l’archivage ; les workflows financiers existants sont conservés.

## Migration additive

`2026_09_30_000004_document_layouts.php` ajoute :

- `communication_templates.layout_draft` et `published_version` ;
- `quotes.document_metadata` (objet et pièces, distincts de la configuration technique) ;
- `document_template_revisions` (révisions publiées) ;
- `document_issues` (instantané, empreinte des données commerciales et lien vers `document_archives`) ;
- les modèles commun et reçu, sans écraser les modèles existants.

La migration conserve une révision de référence des paramètres PDF existants. Aucun PDF historique n’est généré pendant la migration. Son retour arrière ne supprime volontairement aucune donnée.

## Conservation des documents

Une archive existante est restituée octet pour octet, avec contrôle de longueur et SHA-256. Le téléchargement d’un devis validé crée une édition conservée ou réutilise l’édition correspondante. Les changements de style et de versements ne remplacent pas cette édition. Une modification des données commerciales peut produire une nouvelle édition, sans toucher à la précédente.

Les pièces jointes historiques restent accessibles par leurs routes existantes. Une pièce jointe de devis envoyée est réutilisée lorsque son instantané correspond aux données actuelles. Les anciens documents sans PDF conservé gardent le moteur historique ; les devis non modifiés antérieurs à la révision de référence utilisent ses paramètres. Un ancien PDF absent ne peut pas être recréé avec une garantie d’identité binaire.

Les factures émises conservent leur archive. Les nouvelles émissions figent leur présentation, la référence du devis et les versements connus à l’émission. Un reçu est lié à un paiement réellement enregistré et fige le reste à payer au moment de son édition. Aucun reçu nouveau n’est émis pour un paiement annulé.

## Vérification

Depuis `Backend` :

```sh
php -d memory_limit=512M vendor/bin/phpunit
npm test
npm run build
php -d memory_limit=256M tools/preview-documents.php
```

GD est requis. Le dernier script écrit uniquement des exemples fictifs dans `artifacts/document-layout/`, dossier exclu de Git et des déploiements. Il ne lit aucun client en base.

`DocumentLayoutsTest` couvre brouillon/publication/concurrence, aperçu sans écriture, permissions, images invalides, stockage privé, conservation des archives, reçus annulés, métadonnées sans recalcul, valeurs de référence, affichage détaillé et PDF de trente ouvertures. Les tests de communication, filigranes, facturation et chiffrage existants doivent également passer sur SQLite et MySQL.

## Déploiement Hostinger et retour arrière

1. Faire passer la CI sur le commit exact : tests SQLite/MySQL, JavaScript, build et vérification des scripts shell.
2. Utiliser l’archive de cette CI et `deploy/hostinger/publish.ps1`, avec les accès SSH déjà configurés. Ne pas utiliser le déploiement Git hPanel vers `public_html`.
3. `release.sh` appelle `backup.sh` avant migration. La sauvegarde privée contient MySQL (dont archives PDF et anciennes images base64), la configuration et `shared/storage/app` (dont les nouveaux fichiers de modèles). Le tar des fichiers est vérifié avant finalisation.
4. Les migrations/seeders, caches et contrôles de disponibilité s’exécutent avant bascule vers la nouvelle release. Vérifier HTTPS, `/health/ready`, l’éditeur et un document de démonstration.
5. L’ancien style reste actif tant qu’aucun modèle premium n’est publié. Examiner l’aperçu avec les filigranes officiels avant publication.

En cas de problème, `bash deploy/hostinger/rollback.sh /home/u254587528/domains/maisondustoream.com` rebascule sur la précédente release sans restaurer ni supprimer la base. Conserver tables, révisions, archives et fichiers privés. Ne pas lancer `migrate:rollback` pour supprimer les données documentaires. Une restauration de sauvegarde complète est une opération distincte, à préparer selon les écritures intervenues depuis la sauvegarde.
