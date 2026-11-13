---
title: Traiter une demande de devis
summary: Une demande arrive du site ou d’un agent : compléter le chiffrage puis valider le devis.
group: Devis et ventes
order: 10
roles: manager, admin
routes: staff.quotes.index, staff.quotes.show
---
## D’où viennent les demandes

- **Site** : le client a rempli le formulaire de devis en ligne. Le devis arrive avec l’état **À traiter** et une notification.
- **Sur place** : un agent a relevé les mesures chez le client. Le devis arrive en **Brouillon**, sans prix.
- **Espace interne** : un devis créé au bureau.

![La liste des devis](capture:devis-liste)

## Étapes

1. Ouvrez **Devis**. Les devis à traiter sont signalés par une pastille dans le menu. **Filtrer** et **Autres filtres** trient la liste.
2. Ouvrez la demande. La fiche montre l’avancement en haut, les ouvertures, la **Demande d’origine** et les **Choix du client sur le site**.
3. Si un encadré indique **Prochaine étape : compléter le chiffrage**, touchez **Ouvrir le formulaire complet** et complétez les prix (voir [Créer et chiffrer un devis](guide:creer-devis)).
4. De retour sur la fiche, touchez **Aperçu** pour relire le PDF.
5. Touchez **Valider le devis**. Il passe à l’état **Validé** et peut être envoyé au client.

![La fiche d’un devis](capture:devis-fiche)

> **Bon à savoir.** La validation reste bloquée tant qu’un prix est « à définir » ou que les choix de l’agent ne sont pas vérifiés. Un prix vide n’est jamais compté comme gratuit.

Ensuite : [Envoyer un devis au client](guide:envoyer-devis).
