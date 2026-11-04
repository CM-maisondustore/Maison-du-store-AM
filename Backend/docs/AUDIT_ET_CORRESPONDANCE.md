# Espace Pro : audit, correspondance et arborescence

Référence : `Backend-main.zip` (état initial) et `MDS-espace-pro.zip` (maquette), sur instruction du 02/11/2026. Le commit `cae80df` cité dans la mission n'a pas été utilisé.

## 1. Audit de l'état initial, écran par écran

| Écran | Constat dans le code | Ce qui a été refait |
|---|---|---|
| Menu | 13 entrées à plat, défilement horizontal sur mobile, aucun compteur | Menu par métier et par rôle, repliable, compteurs, onglets bas sur mobile, feuille « Plus » |
| Barre du haut | Nom, notifications, mot de passe, voir le site, déconnexion ; pas de recherche | Recherche globale, bouton Nouveau, cloche, menu du compte |
| Vue d'ensemble | 4 compteurs non cliquables et un tableau, identiques pour les deux rôles | Admin : files d'attente, planning, devis à valider, factures dues, automatisations. Agent : écran Aujourd'hui |
| Liste des devis | Filtre d'état par sélecteur, tableau qui déborde | Filtres avec compteurs, recherche, type, source, agent, tri, cartes sur mobile |
| Page devis | Une seule page empilée, état changé par un sélecteur | Dossier en deux colonnes, frise, prochaine action guidée, acceptation avec preuve, historique |
| Formulaire de devis | Long formulaire sans repère | Sections numérotées, sommaire, barre d'actions fixe. Le moteur et ses champs sont inchangés |
| Relevé agent | Formulaire sans repère, bouton de fin de page | Sections numérotées, barre fixe, saisie gardée sur l'appareil |
| Rendez-vous | Tableau seul | Semaine, jour et liste ; confirmer et annuler en un geste |
| Prestations | Tableau et formulaire de planification toujours ouvert | Filtres, planification repliée, fiche terrain avec appeler, itinéraire, WhatsApp, démarrer, terminer |
| Clients | Recherche seule ; dossier en panneaux | Filtre par type, archivés, fiche avec onglets d'historique et archivage |
| Catalogue | Une carte par produit | Tableau, filtre Univers, statuts modifiables dans la ligne, confirmation de l'interrupteur global |
| Factures | Tableau et page simple, confirmation native | Filtres, aperçu, impression, émission avec case de vérification obligatoire |
| Messagerie | Tableau, puis page séparée | Liste et lecture côte à côte, envoi bloqué expliqué avant le clic |
| Documents et automatisations | Formulaires empilés | État SMTP et traitement, règles à trois modes, modèles en cartes |
| Images, Équipe, Notifications, Matériaux | Listes simples | Cartes avec visibilité, tableau des droits par rôle, prix manquants signalés |
| Mise en page, Visiteurs, Modèles, Rédaction | Fonctionnels, absents de la maquette | Conservés, repris par le nouveau style sans changement de structure |
| Absents | Profil, paramètres, thème, recherche, univers | Créés |

## 2. Correspondance de chaque route

93 routes `/admin`, générées depuis `php artisan route:list`. Aucune URL, aucun nom ni contrat de formulaire existant n'a changé. Six routes sont nouvelles et marquées NOUVEAU.

| Méthode | URL | Nom | Rôle | Emplacement |
|---|---|---|---|---|
| GET | `/admin` | `staff.dashboard` | Tous | Vue d’ensemble (admin) / Aujourd’hui (agent) |
| PATCH | `/admin/catalogue/visibilite` | `staff.catalogue.visibility` | Tous | Catalogue, carte Visibilité du catalogue |
| GET | `/admin/communications` | `staff.communications.index` | Tous | Site et réglages > Documents et automatisations |
| POST | `/admin/communications/connexion` | `staff.communications.connection` | Tous | Site et réglages > Documents et automatisations |
| POST | `/admin/communications/executer` | `staff.communications.process` | Tous | Site et réglages > Documents et automatisations |
| GET | `/admin/communications/modeles/{template}` | `staff.communications.edit` | Tous | Site et réglages > Documents et automatisations |
| POST | `/admin/communications/modeles/{template}` | `staff.communications.update` | Tous | Site et réglages > Documents et automatisations |
| POST | `/admin/communications/modeles/{template}/apercu` | `staff.communications.preview` | Tous | Site et réglages > Documents et automatisations |
| PUT | `/admin/communications/regles/{rule}` | `staff.communications.rule` | Tous | Site et réglages > Documents et automatisations |
| GET | `/admin/connexion` | `login` | Public | Écran de connexion |
| POST | `/admin/connexion` | `staff.login` | Tous | Écran de connexion |
| GET | `/admin/contacts` | `staff.contacts.index` | Tous | Gestion > Clients et contacts, puis fiche 360° |
| POST | `/admin/contacts` | `staff.contacts.store` | Tous | Gestion > Clients et contacts, puis fiche 360° |
| GET | `/admin/contacts/{contact}` | `staff.contacts.show` | Tous | Gestion > Clients et contacts, puis fiche 360° |
| PUT|PATCH | `/admin/contacts/{contact}` | `staff.contacts.update` | Tous | Gestion > Clients et contacts, puis fiche 360° |
| GET | `/admin/contents` | `staff.contents.index` | Tous | Site et réglages > Images et portfolio |
| POST | `/admin/contents` | `staff.contents.store` | Tous | Site et réglages > Images et portfolio |
| GET | `/admin/contents/create` | `staff.contents.create` | Tous | Site et réglages > Images et portfolio |
| PUT|PATCH | `/admin/contents/{content}` | `staff.contents.update` | Tous | Site et réglages > Images et portfolio |
| GET | `/admin/contents/{content}/edit` | `staff.contents.edit` | Tous | Site et réglages > Images et portfolio |
| POST | `/admin/deconnexion` | `staff.logout` | Tous | Menu du compte, Paramètres > Session |
| GET | `/admin/devis` | `staff.quotes.index` | Tous | Activité > Devis : filtres par état, type, source, agent |
| POST | `/admin/devis` | `staff.quotes.store` | Tous | Formulaire de devis (fenêtres ou articles) |
| POST | `/admin/devis/chiffrer` | `staff.quotes.calculate` | Tous | Formulaire de devis, section Chiffrage |
| GET | `/admin/devis/nouveau` | `staff.quotes.create` | Tous | Bouton Nouveau, liste des devis, bouton flottant mobile |
| GET | `/admin/devis/{quote}` | `staff.quotes.show` | Tous | Dossier devis |
| PUT | `/admin/devis/{quote}` | `staff.quotes.update` | Tous | Dossier devis : Valider, Enregistrer l’acceptation, Suivi du devis |
| POST | `/admin/devis/{quote}/avancement` | `staff.quotes.workflow` | Tous | Dossier devis, Fabrication et suivi |
| POST | `/admin/devis/{quote}/facture` | `staff.invoices.create` | Tous | Dossier devis accepté, section Facturation |
| GET | `/admin/devis/{quote}/formulaire` | `staff.quotes.edit` | Tous | Dossier devis, bouton Ouvrir le formulaire complet |
| PUT | `/admin/devis/{quote}/formulaire` | `staff.quotes.form.update` | Tous | Formulaire de devis, barre d’actions |
| POST | `/admin/devis/{quote}/paiements` | `staff.quotes.receipt` | Tous | Dossier devis, section Paiements |
| POST | `/admin/devis/{quote}/paiements/{payment}/annuler` | `staff.quotes.receipt.void` | Tous | Dossier devis, section Paiements, Corriger une erreur de saisie |
| GET | `/admin/devis/{quote}/paiements/{payment}/recu` | `staff.layouts.receipt` | Tous | Dossier devis, Paiements, reçu PDF |
| GET | `/admin/devis/{quote}/pdf` | `staff.quotes.pdf` | Tous | Dossier devis, en-tête |
| POST | `/admin/devis/{quote}/presentation` | `staff.layouts.metadata` | Tous | Dossier devis, Présentation du document |
| GET | `/admin/documents/{issue}/archive` | `staff.layouts.archive` | Tous | Dossier devis, Éditions conservées |
| GET | `/admin/equipe` | `staff.users.index` | Tous | Site et réglages > Équipe |
| POST | `/admin/equipe` | `staff.users.store` | Tous | Site et réglages > Équipe |
| PUT | `/admin/equipe/{user}` | `staff.users.update` | Tous | Site et réglages > Équipe |
| GET | `/admin/factures` | `staff.invoices.index` | Tous | Gestion > Factures, puis fiche |
| GET | `/admin/factures/{invoice}` | `staff.invoices.show` | Tous | Gestion > Factures, puis fiche |
| POST | `/admin/factures/{invoice}/emettre` | `staff.invoices.issue` | Tous | Gestion > Factures, puis fiche |
| POST | `/admin/factures/{invoice}/paiement` | `staff.invoices.paid` | Tous | Gestion > Factures, puis fiche |
| GET | `/admin/factures/{invoice}/pdf` | `staff.invoices.pdf` | Tous | Gestion > Factures, puis fiche |
| GET | `/admin/illustrations-produits/{product}` | `staff.quote-product-image` | Tous | Formulaire de devis, illustrations des choix |
| GET | `/admin/media/{media}` | `staff.media.preview` | Tous | Aperçu d’image, images et catalogue |
| GET | `/admin/messages` | `staff.messages.index` | Tous | Gestion > Messagerie, liste et lecture côte à côte |
| POST | `/admin/messages` | `staff.messages.store` | Tous | Gestion > Messagerie, liste et lecture côte à côte |
| GET | `/admin/messages/nouveau` | `staff.messages.create` | Tous | Gestion > Messagerie, liste et lecture côte à côte |
| POST | `/admin/messages/synchroniser` | `staff.messages.sync` | Tous | Gestion > Messagerie, liste et lecture côte à côte |
| GET | `/admin/messages/{message}` | `staff.messages.show` | Tous | Gestion > Messagerie, liste et lecture côte à côte |
| PUT | `/admin/messages/{message}` | `staff.messages.update` | Tous | Gestion > Messagerie, liste et lecture côte à côte |
| POST | `/admin/messages/{message}/annuler` | `staff.messages.cancel` | Tous | Gestion > Messagerie, liste et lecture côte à côte |
| GET | `/admin/messages/{message}/document` | `staff.messages.attachment` | Tous | Gestion > Messagerie, liste et lecture côte à côte |
| POST | `/admin/messages/{message}/envoyer` | `staff.messages.send` | Tous | Gestion > Messagerie, liste et lecture côte à côte |
| POST | `/admin/messages/{message}/partage` | `staff.messages.shared` | Tous | Gestion > Messagerie, liste et lecture côte à côte |
| GET | `/admin/messages/{message}/whatsapp` | `staff.messages.whatsapp` | Tous | Gestion > Messagerie, liste et lecture côte à côte |
| GET | `/admin/mise-en-page/{type?}` | `staff.layouts.edit` | Tous | Site et réglages > Mise en page |
| POST | `/admin/mise-en-page/{type}` | `staff.layouts.save` | Tous | Site et réglages > Mise en page |
| POST | `/admin/mise-en-page/{type}/apercu` | `staff.layouts.preview` | Tous | Site et réglages > Mise en page |
| POST | `/admin/mise-en-page/{type}/versions/{revision}` | `staff.layouts.restore` | Tous | Site et réglages > Mise en page |
| GET | `/admin/mot-de-passe` | `staff.password` | Tous | Menu du compte, Paramètres > Compte et sécurité |
| POST | `/admin/mot-de-passe` | `staff.password.update` | Tous | Menu du compte, Paramètres > Compte et sécurité |
| GET | `/admin/notifications` | `staff.notifications.index` | Tous | Cloche de la barre du haut |
| POST | `/admin/notifications/{notification}/lu` | `staff.notifications.read` | Tous | Cloche de la barre du haut |
| GET | `/admin/options-devis` | `staff.quote-options.index` | Tous | Activité > Matériaux et finitions |
| POST | `/admin/options-devis` | `staff.quote-options.store` | Tous | Activité > Matériaux et finitions |
| PUT | `/admin/options-devis/{option}` | `staff.quote-options.update` | Tous | Activité > Matériaux et finitions |
| GET | `/admin/parametres` | `staff.settings` | Tous | NOUVEAU — menu du compte, Paramètres |
| PATCH | `/admin/parametres` | `staff.settings.update` | Tous | NOUVEAU — menu du compte, Paramètres |
| POST | `/admin/parametres/autres-appareils` | `staff.settings.logout-others` | Tous | NOUVEAU — menu du compte, Paramètres |
| GET | `/admin/prestations` | `staff.interventions.index` | Tous | Activité > Prestations, puis fiche |
| POST | `/admin/prestations` | `staff.interventions.store` | Tous | Prestations, Planifier une prestation |
| GET | `/admin/prestations/{intervention}` | `staff.interventions.show` | Tous | Activité > Prestations, puis fiche |
| PUT | `/admin/prestations/{intervention}` | `staff.interventions.update` | Tous | Activité > Prestations, puis fiche |
| POST | `/admin/prestations/{intervention}/devis` | `staff.interventions.quote` | Tous | Fiche prestation, Devis sur place |
| GET | `/admin/products` | `staff.products.index` | Tous | Site et réglages > Catalogue |
| POST | `/admin/products` | `staff.products.store` | Tous | Site et réglages > Catalogue |
| GET | `/admin/products/create` | `staff.products.create` | Tous | Site et réglages > Catalogue |
| PUT|PATCH | `/admin/products/{product}` | `staff.products.update` | Tous | Site et réglages > Catalogue |
| GET | `/admin/products/{product}/edit` | `staff.products.edit` | Tous | Site et réglages > Catalogue |
| PATCH | `/admin/products/{product}/visibility` | `staff.products.visibility` | Tous | Site et réglages > Catalogue |
| GET | `/admin/profil` | `staff.profile` | Tous | NOUVEAU — menu du compte, Mon profil |
| PATCH | `/admin/profil` | `staff.profile.update` | Tous | NOUVEAU — menu du compte, Mon profil |
| DELETE | `/admin/push/abonnement` | `staff.push.unsubscribe` | Tous | Menu du compte, bouton Activer les notifications |
| POST | `/admin/push/abonnement` | `staff.push.subscribe` | Tous | Menu du compte, bouton Activer les notifications |
| GET | `/admin/push/cle-publique` | `staff.push.public-key` | Tous | Menu du compte, bouton Activer les notifications |
| GET | `/admin/recherche` | `staff.search` | Tous | NOUVEAU — barre du haut, recherche globale |
| GET | `/admin/rendez-vous` | `staff.appointments.index` | Tous | Activité > Rendez-vous : semaine, jour, liste, puis fiche |
| GET | `/admin/rendez-vous/{appointment}` | `staff.appointments.show` | Tous | Activité > Rendez-vous : semaine, jour, liste, puis fiche |
| PUT | `/admin/rendez-vous/{appointment}` | `staff.appointments.update` | Tous | Activité > Rendez-vous : semaine, jour, liste, puis fiche |
| GET | `/admin/visiteurs` | `staff.visitors.index` | Tous | Site et réglages > Visiteurs |

## 3. Arborescence par rôle

**Admin-CM**, sur ordinateur : Vue d'ensemble · *Activité* : Devis, Matériaux et finitions, Rendez-vous, Prestations · *Gestion* : Clients et contacts, Factures, Messagerie · *Site et réglages* : Catalogue, Images et portfolio, Documents et automatisations, Mise en page, Visiteurs, Équipe.
Sur mobile : Accueil, Devis, Planning, Clients, Plus.

**Agent** : Aujourd'hui · Mes prestations · Mes rendez-vous · Mes devis · Profil.
Sur mobile : Aujourd'hui, Prestations, Devis, RDV, Profil.

**Parcours principaux**

- Devis : Nouveau > Boutique > Client > Fenêtres ou Articles > Chiffrage > Enregistrer > Valider > Préparer l'envoi > Enregistrer l'acceptation > Préparer la facture > Émettre > Marquer comme payée.
- Agent : Aujourd'hui > Ouvrir la fiche > Démarrer > Compte rendu > Terminer.

## 4. Changements de données

- Une migration additive et réversible : `users.profile` et `users.preferences`, en JSON et facultatives.
- Aucune autre table modifiée. L'univers d'un produit est dans `products.details` ; un devis par articles utilise `quotes.type` (`electromenager`, `meubles`) et `quotes.configuration`.
- Références : `AM-date-EL-nnn` et `AM-date-MB-nnn` s'ajoutent à `ST` et `RV`, sur la même séquence.

## 5. Déploiement conseillé

1. Sauvegarder la base et les fichiers, puis déployer sur une copie de test avec une copie de la base.
2. `php artisan migrate`, sans autre changement de données.
3. Recette avec un compte Admin-CM et un compte Agent, sur téléphone.
4. Facultatif : `php artisan catalogue:import`. Aucun produit n'est publié.
5. Retour arrière : `php artisan migrate:rollback --step=1` et version précédente des fichiers.

**Risques à surveiller** : le service worker a la portée `/` (état initial, inchangé) ; les utilisateurs doivent recharger pour obtenir le nouveau `admin.css` ; le site public de cette application reçoit des produits importés seulement si Admin-CM les publie.
