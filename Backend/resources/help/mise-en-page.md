---
title: Mettre en page les devis, factures et reçus
summary: Placer et régler les blocs du PDF, insérer les données du client, publier le modèle.
group: Documents et site
order: 10
roles: admin
routes: staff.layouts.*
---
## À quoi ça sert

La mise en page définit l’apparence commune des PDF envoyés aux clients : devis, factures et reçus. Les changements ne s’appliquent qu’après **Publier le modèle**.

![L’éditeur de mise en page](capture:mise-en-page)

## Étapes

1. Ouvrez **Mise en page** et choisissez le document en haut : devis, facture ou reçu.
2. Dans **Réglages du document**, renseignez l’**Entreprise** (coordonnées, RCCM, NCC) et l’**Identité** (logo, couleurs, police : DejaVu, Arial ou Times New Roman).
3. Dans **Blocs à placer**, ajoutez un bloc ; faites-le glisser sur la page A4. Les **Calques** listent les blocs ; touchez-en un pour le sélectionner.
4. Réglez le bloc sélectionné :
   - **Texte** : intitulé, police, taille, couleur, gras, italique, MAJUSCULES, interligne, espacement.
   - **Contenu** : texte libre ou complémentaire. Une ligne `---` trace un trait.
   - **Lignes du bloc** (Client, Entreprise) : lignes affichées, leur ordre, intitulés, traits horizontaux et vertical.
   - **Lignes de séparation**, **Disposition** (marge intérieure, alignement, coins arrondis), **Fond et bordure**.
   - **Tableau** : lignes affichées et leur ordre, colonnes, intitulés, en-tête, lignes alternées.
   - **Dupliquer**, **Premier plan**, **Arrière-plan**, **Retirer ce bloc**.
5. **Actualiser l’aperçu A4** ou **Aperçu PDF** pour vérifier le rendu.
6. **Enregistrer le brouillon** garde votre travail sans changer les documents envoyés. **Publier le modèle** l’applique aux prochains documents.

## Insérer les données du client

Dans un texte, les boutons de **Données à insérer** ajoutent une balise remplacée dans chaque document :

- `{client.civilite}` : « Madame » ou « Monsieur », selon le choix du client.
- `{client.nom}`, `{client.telephone}`.
- `{devis.numero}`, `{devis.date}`, `{total.general}`, `{acompte}`, `{reste_a_payer}`.

Exemple : `{client.civilite} {client.nom}` donne « Madame Awa Kouassi ».

> **Bon à savoir.** Toutes les options sont désactivées par défaut : un modèle garde exactement son rendu tant que vous ne les utilisez pas. **Historique des modèles publiés** permet de revenir à une version précédente avec **Reprendre en brouillon**. Les documents déjà envoyés ne changent jamais.
