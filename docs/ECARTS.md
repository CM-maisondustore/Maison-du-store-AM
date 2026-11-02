# Écarts avec la maquette et points à trancher

## Contradictions : le code et les tests restent la référence

| Sujet | Mission ou maquette | Code conservé | Décision attendue |
|---|---|---|---|
| Notes internes d'un rendez-vous | « Les agents ne voient jamais les notes internes » | L'agent affecté voit la note du rendez-vous | À trancher : la masquer retire aussi le seul canal d'instructions de ce rendez-vous |
| Devis visibles par un agent | Devis affectés | Devis créés par l'agent (test `test_agents_only_access_quotes_they_created…`) | La rubrique « À poser » n'apparaît donc que sur les devis dont l'agent est l'auteur |
| Chevauchement de prestations | Avertir sans bloquer | Refus | Conservé |
| Acompte | 50 % | 70 % par défaut, réglable sur chaque devis | Conservé |
| Coefficient d'ampleur | Inconnu, lignes « à définir » | 2,5 par défaut | Conservé ; à faire valider par le gérant |
| Numérotation | `D-AAAA-NNNN`, `MDS-0000001` | `AM-date-ST-nnn`, `…-FAC` | Conservée : les documents émis en dépendent |
| Comptes | Invitation par lien de 72 h | Mot de passe temporaire, comme le demande le brief | Conservé |
| Thème | Par appareil | Par compte, comme annoncé | Conservé |
| Libellé | « Documents et automatisations » | Titre « Documents & automatisations », vérifié par un test | Conservé |

## Présent sur la maquette, non construit

| Élément | Raison | Ce qui existe à la place |
|---|---|---|
| Double authentification | Demande un nouveau paquet et un parcours de secours à valider | Mot de passe, limitation des tentatives, déconnexion des autres appareils |
| Hors ligne complet : file d'envoi, fiches gardées 7 à 30 jours, photos en Wi-Fi | Risque de doublons sans clé d'idempotence côté serveur ; à faire en lot dédié | Saisie gardée sur l'appareil (compte rendu, relevé agent, devis par articles de l'agent) |
| Avoirs | Règle comptable à valider par un comptable | Facture émise verrouillée |
| Photos de chantier, preuve d'acceptation en fichier | Demandent un stockage privé et un nouveau schéma | Preuve écrite dans les notes internes du devis |
| Notifications par événement et par canal, heures calmes | Le serveur ne sait pas encore les appliquer : un réglage sans effet serait trompeur | Notifications internes et bouton d'activation du navigateur |
| Liste des appareils connectés | Sessions non suivies par appareil | « Déconnecter les autres appareils » |
| Contacts à qualifier | Aucune donnée correspondante dans le schéma | Filtres de la liste des contacts |
| Comparer deux versions, créer une nouvelle version d'un devis | Pas d'historique de versions | Alerte de modification concurrente, verrouillage |
| Panneau de détail à droite des listes (prestations, rendez-vous, catalogue, matériaux) | Choix de simplicité : mêmes actions sans dupliquer les formulaires | La fiche s'ouvre sur sa propre page |
| Enregistrement groupé des statuts du catalogue | Route absente, contrat conservé | Enregistrement ligne par ligne |
| Glisser-déposer du portfolio, filtre par boutique | Route absente | Numéro d'ordre dans la fiche |
| Devis : origine (téléphone, boutique), créneau préféré | Champs absents du schéma | Notes du devis |
| Fournitures sous forme de cases sur le formulaire | Le moteur existant les gère ouverture par ouverture | Partie « Choix techniques » de chaque ouverture |
| Vite, API `/api/v1/admin`, Sanctum, Policies, Pest | Exclus par la mission : stack actuelle | Blade, CSS et JS servis directement, middlewares existants |
| Page `/admin/_kit` | Outil de développement | Non construite |

## Harmonisation de l'interface (02/11/2026)

- Aucune URL ni aucun contrat de formulaire modifié, aucune migration. **Une route ajoutée**, justifiée par les bulles : `GET /admin/notifications/recentes` (`staff.notifications.recent`), en lecture seule, limitée aux alertes de la personne connectée, sans les marquer comme lues.
- Elle est interrogée toutes les 45 secondes, onglet visible seulement, et plus du tout après 15 minutes sans activité : un poste laissé ouvert continue d'expirer. La page Visiteurs garde son rafraîchissement d'origine, toutes les 15 secondes et sans cette limite.
- Bulles reprises du site (`public/js/mds.js`, `public/css/mds.css` de la refonte) : anneau double, pastille de texte, courbe et durée de 1 100 ms, nouveau placement toutes les 5,5 s, en pause au survol, au focus et onglet masqué. Sans animation si « Réduire les animations » est activé ou si l'appareil le demande.
- Une bulle ne remplace jamais la page : les confirmations restent lisibles sans JavaScript, les erreurs restent dans le contenu et ne disparaissent pas seules.
- La teinte d'une alerte suit la rubrique visée par son lien, car le schéma ne stocke pas de type d'alerte.
- Matériaux et finitions : liste dépliable filtrable plutôt que le panneau latéral de la maquette, chaque option gardant son formulaire et sa route.
- Pages d'erreur 403, 404, 419, 429, 500 et 503 ajoutées aux couleurs de la maison.

## Refonte des 7 écrans de création (02/11/2026)

Aucune route, aucune migration, aucune règle de validation ni aucun calcul modifié. Un devis calculé avant et après donne les mêmes montants au franc près.

### Contradictions signalées, code conservé

| Sujet | Cahier des charges | Code conservé | Décision attendue |
|---|---|---|---|
| Envoi à l’enregistrement | « Un enregistrement ne déclenche aucun envoi » | Créer ou modifier une prestation notifie l’agent affecté (`StaffPushNotifications`) | Le formulaire le dit : « L’agent choisi est prévenu sur son compte. Le client ne reçoit aucun message. » À confirmer |
| Mot unique « Créer un client » | Même mot partout | Le bouton sert aussi aux fournisseurs et partenaires, et la page reste « Clients & contacts » | À confirmer : « Créer un contact » serait plus exact, la maquette dit « Nouveau contact » |
| Matériaux d’électroménager ou de meubles | À signaler seulement | Le serveur n’accepte que `rideau` et `store` | Non ajoutés : demanderait de changer la validation |

### Écarts durables avec la maquette

| Écran | Maquette | Construit | Raison |
|---|---|---|---|
| Matériau | Panneau latéral affichant l’option sélectionnée | Le panneau sert à l’ajout ; chaque option se modifie dans la liste, avec sa route `PUT` | Demandé par le cahier des charges |
| Matériau | Coloris proposés, onglets par famille | Filtres par nature | Champ absent du schéma |
| Client | Indicateurs de chiffre d’affaires, contacts à qualifier | Non construits | Hors périmètre, données absentes |
| Réalisation | Boutique, Monter / Descendre, Supprimer | Numéro d’ordre, visibilité | Route et champ absents, voir plus haut |
| Mise en page | Aucune maquette propre | Onglets dans `#layout-form`, aperçu collant | Sous 768 px, l’aperçu n’est pas collant : il prendrait tout l’écran. Un bouton « Voir l’aperçu » y mène, et le PDF s’ouvre dans un nouvel onglet |
| Nouveau devis | Recherche d’un client existant, origine de la demande, créneau, photos | Non construits | Route ou champs absents, voir plus haut |
| Nouveau devis | Quantité avec − et + sur la carte fenêtre de l’agent | Largeur et hauteur seulement | Le relevé de l’agent n’a pas de champ de quantité |
| Nouveau devis | Prix ligne à ligne et total sur les articles | Prix par article et par service, total sur la fiche après enregistrement | Aucun calcul côté client : le serveur reste seul à calculer |

### Ajouts à connaître

- **Champ caché `open_form`** sur les formulaires de matériaux. Plusieurs formulaires partagent la page et le serveur ne dit pas lequel a échoué : ce repère rouvre le bon. Le serveur l’ignore et il n’est jamais enregistré (test `test_the_form_marker_is_never_stored…`).
- **Messages d’erreur en français** ajoutés dans `lang/fr/validation.php`, dont l’e-mail déjà utilisé, qui s’affichait en anglais. Les règles ne changent pas.
- **Défaut corrigé hors maquette :** la liste « Finition » de l’agent restait vide, car `agent-quote.js` filtrait sur `kind` et `active`, absents du catalogue qui lui est envoyé. L’agent ne pouvait donc pas enregistrer un relevé utilisant les choix communs. Le filtre porte maintenant sur la catégorie, et le catalogue ne contient toujours aucun prix.
- **Défaut corrigé :** le contrôle de validité de `quote-editor.js` rouvrait les détails de chaque ouverture à chaque changement. Il lit maintenant `validity.valid`, sans déclencher d’événement.
- **Aperçu A4 :** le PDF paraissait coupé dans son cadre, confirmé dans Edge. L’adresse du cadre porte maintenant `#view=FitH` et le PDF tient en largeur.
- Le brouillon local de l’agent retrouve aussi les ouvertures et articles ajoutés et, pour un nouveau relevé, ses choix.

### Observé, non modifié

- Dans l’aperçu du modèle « Devis » aux réglages historiques, le bloc « Total général » s’affiche en texte sombre sur fond sapin, peu lisible. Il vient du rendu PDF, exclu de cette refonte : à vérifier dans les réglages du modèle.

## Encore à faire

- Brancher les demandes du nouveau site (dépôt Refonte V2) sur cet espace pro, puis retirer le `/admin` minimal de cette refonte.
- Compléter les mentions légales dans Mise en page : l'émission d'une facture reste refusée tant qu'elles sont vides.
- Fournir un logo en haute définition : le fichier de la maquette est un petit JPG.
