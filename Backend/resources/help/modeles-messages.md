---
title: Modèles de messages et automatisations
summary: Rédiger les messages e-mail et WhatsApp, utiliser les variables, régler les envois automatiques.
group: Documents et site
order: 20
roles: admin
routes: staff.communications.*
---
## Modifier un modèle

1. Ouvrez **Documents et automatisations**. La rubrique **Modèles** liste les messages : demande de devis reçue, envoi du devis, envoi de facture, confirmation et rappel de rendez-vous, échange commercial, offre et promotion.
2. Ouvrez un modèle, en version e-mail ou WhatsApp.
3. Modifiez l’objet et le texte. Les variables entre doubles accolades sont remplacées pour chaque client.
4. Vérifiez l’aperçu puis enregistrez.

![Un modèle de message](capture:modele-message)

## Les variables

- `{{civilite}}` : « Madame » ou « Monsieur » ; si elle n’est pas connue, elle est omise.
- `{{prenom}}`, `{{nom}}`, `{{adresse}}`, `{{entreprise}}`.
- `{{reference}}`, `{{montant}}`, `{{acompte}}`, `{{solde}}`, `{{remise}}`, `{{conditions_paiement}}`.
- `{{date}}`, `{{heure}}`, `{{statut}}` pour les rendez-vous.
- Liens vers le site : `{{site}}`, `{{lien_devis}}`, `{{lien_realisations}}`, `{{lien_boutique}}`, `{{lien_rendez_vous}}`. Les visites qu’ils apportent apparaissent dans **Visiteurs**.

Exemple : « Bonjour {{civilite}} {{nom}}, » donne « Bonjour Madame Awa Kouassi, ».

## Les règles d’automatisation

Pour chaque événement (demande reçue, devis validé, rendez-vous…), choisissez le mode puis **Enregistrer la règle** :

- **Brouillon à valider** prépare le message sans l’envoyer ; vous le relisez dans la messagerie.
- **Envoi automatique** l’envoie sans nouvelle validation.

Pour le rappel de rendez-vous, réglez les **Heures avant le rendez-vous**. **Exécuter les règles actives maintenant** lance le traitement sans attendre.

> **Bon à savoir.** **Vérifier la connexion sans envoyer d’e-mail** contrôle la boîte d’envoi (SMTP). Les demandes et validations antérieures à l’activation d’une règle ne sont pas traitées.
