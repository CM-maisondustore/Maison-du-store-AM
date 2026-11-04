# Écarts avec la maquette et points à trancher

## Contradictions : le code et les tests restent la référence

| Sujet | Mission ou maquette | Code conservé | Décision attendue |
|---|---|---|---|
| Notes internes d'un rendez-vous | « Les agents ne voient jamais les notes internes » | L'agent affecté voit la note du rendez-vous | À trancher : la masquer retire aussi le seul canal d'instructions de ce rendez-vous |
| Devis visibles par un agent | Devis affectés | Devis créés par l'agent (test `test_agents_only_access_quotes_they_created…`) | La rubrique « À poser » n'apparaît donc que sur les devis dont l'agent est l'auteur |
| Chevauchement de prestations | Avertir sans bloquer | Refus | Conservé |
| Acompte | 50 % | Depuis le lot 3 : 70 / 30 (acompte réglable) jusqu’à 500 000 F, 50 / 30 / 20 au-delà ; les devis chiffrés avant gardent leur acompte unique | Réglé par le lot 3 |
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

## Nouveau devis en 4 étapes, client et prestation en formulaire flottant (03/11/2026)

Demandé après revue de la maquette. Aucune route, migration, règle de validation ni calcul modifié. Le devis de référence, refait dans le nouveau parcours, donne les mêmes lignes et les mêmes montants au franc près (443 916 FCFA, acompte 266 350, solde 177 566).

- **Nouveau devis, stores et rideaux (admin)** : client et ouvertures, matériaux et finitions, affectation et chiffrage, paiement. Les 4 étapes restent un seul formulaire, enregistré d’un bloc : passer d’une étape à l’autre n’enregistre rien. Une erreur du serveur ou du navigateur rouvre l’étape concernée. Sans JavaScript, toutes les étapes s’affichent, avec le message existant.
- **Menus déroulants** pour les tissus, voilages, matériaux de store et fournitures (rail, reflète, motorisation, confection, pose). Les champs envoyés restent les mêmes champs cachés.
- **Ouvertures** : désignation en gros, schéma à l’échelle, notes de l’ouverture visibles dès l’étape 1, aussi sur le relevé de l’agent.
- **Client et prestation** : formulaire flottant au-dessus de la liste, toujours ouvert par ancre (`#nouveau-contact`, `#planifier`), aussi sans JavaScript. Échap le ferme, le focus reste dedans. Durées courantes en raccourcis : elles remplissent le même champ en minutes.
- Identifiants retirés : `s-notes` et `h-notes` (ancres du sommaire du devis, utilisées nulle part ailleurs). Les notes internes sont à l’étape 3, les notes de terrain à l’étape 1.

| Maquette | Non construit | Raison |
|---|---|---|
| Étapes pour le devis par articles et le relevé de l’agent | Formulaire en une page, inchangé | Hors de cette demande |

## Maquette v2, lot 1 : écrans déjà refaits alignés sur les planches (03/11/2026)

Aucune route, migration, règle de validation ni calcul modifié. Aucun attribut `name` renommé, ajouté ou retiré. Le devis de référence donne toujours 443 916 FCFA, 266 350 d’acompte et 177 566 de solde.

- **Étape 2 (DevisMateriaux)** : dès 768 px, les choix par ouverture forment un tableau avec une ligne par ouverture et une colonne par menu (projet, motorisation, tissu ou matériau, voilage, finition, rail, coefficient). La case « Choix communs » de chaque ligne remplace l’ancienne case « Utiliser les choix communs », avec le même champ `inherit_defaults`. Le badge « Personnalisée » apparaît dès qu’elle est décochée. Le tableau défile en largeur dans son cadre, la colonne « Ouverture » restant fixe : la page elle-même ne déborde pas. Sous 768 px, chaque ouverture reste une carte.
- **Rail et coefficient de tissu** : le menu du rail (`costing[support][option_id]`) et le coefficient (`costing[material_coefficient]`) sont sortis de « Quantités, fournitures et prix » pour s’afficher dans la ligne. Ce sont les mêmes champs, envoyés une seule fois (test `test_quote_openings_table_keeps_each_field_once…`). Quantité, mode et prix du rail restent dans le bloc replié.
- La carte de l’étape 1 et la ligne de l’étape 2 sont maintenant deux éléments distincts de la même ouverture. Le nom et les mesures s’affichent sur les deux (`admin.js` met à jour tous les `[data-opening-name]` et `[data-opening-sum]`).
- **Étape 4 (DevisPaiement)** : un récapitulatif latéral (`staff/quote-recap`) affiche le client, les ouvertures, l’agent, le total et les lignes à définir. Il relit les champs du formulaire et le dernier calcul, et n’enregistre rien. Un total qui n’a pas été recalculé s’affiche « À recalculer à l’étape 3 ».
- **Devis par articles (DevisArticles)** : conforme. Seul écart : la taxe affichée vaut 18 % sur la maquette, contre 0 % par défaut dans le code, que je n’ai pas modifié.
- **Relevé de l’agent (DevisAgent)** : conforme (notes de l’ouverture visibles, désignation en gros, aucun prix). Le titre devient « Relevé sur place », comme sur la planche : l’ancien était tronqué dans la barre du téléphone.

| Maquette | Construit | Raison |
|---|---|---|
| Choix communs : rail / support et coefficient de tissu | Rail et coefficient se règlent ouverture par ouverture | Le serveur ne connaît pas de rail ni de coefficient communs : il faudrait changer la validation et `QuoteCosting` |
| Menus « Comme les choix communs » dans chaque ligne | Les menus affichent la valeur commune, et la case « Choix communs » indique l’état | Ajouter une option « comme les choix communs » changerait la valeur envoyée |
| Pas de colonnes Projet ni Motorisé | Colonnes conservées | Projet et motorisation sont des champs obligatoires par ouverture |
| Motorisation, poses et quantités dans « Ajuster » à l’étape 3 | Toujours sous chaque ouverture, à l’étape 2 | Déplacer les quantités vers l’étape 3 n’a pas été demandé pour ce lot |

### Nouvelle prestation : planning de l’agent (Prestation)

- Sous la durée, une frise de 8 h à 18 h montre les prestations de l’agent choisi ce jour-là, le créneau proposé (en rouge s’il chevauche) et « Libre à partir de… : choisir 10:30 », qui remplit le même champ `scheduled_at`. Le serveur refuse toujours seul un chevauchement : la frise n’est qu’une aide.
- **Aucune route ajoutée.** Les créneaux sont intégrés à la page `/admin/prestations` (`<script id="agent-planning">`), pour l’administrateur seulement : 60 jours à partir d’aujourd’hui, prestations planifiées ou en cours des agents actifs, avec l’agent, le type, le début et la fin. Ni client, ni adresse, ni consignes (test `test_agent_planning_shows_only_busy_slots_and_only_to_the_admin`). Au-delà de 60 jours, la frise affiche « Planning non chargé pour cette date ».

## Maquette v2, lot 2 : éditeur libre de mise en page des PDF (03/11/2026)

Aucune route ni migration. Les blocs sont stockés dans les options du modèle (`options.blocks`, dans `communication_templates.options` et `layout_draft`), avec le brouillon, la publication, les versions et « Reprendre en brouillon » actuels.

- **Format** : `{ format: 1, items: [ { id, type, x, y, w, h (mm), pages: ["single","continuation"], align, locked, font, size (pt), color, background, border_width (mm), border_color, … } ] }`. Les champs propres à certains types : `text` pour le texte libre et « Notre engagement », `columns`, `group` et `follow` pour le tableau, `source` pour l’image, `shape` pour la ligne ou le cadre. 14 types, 60 blocs au maximum. Le serveur valide tout (`App\Services\DocumentBlocks::normalize`), et un type inconnu est refusé.
- **Enregistrement** : le champ caché `blocks` porte le JSON. S’il est absent (formulaire envoyé sans JavaScript), les blocs enregistrés sont gardés ; s’il est vide (« Revenir au rendu actuel »), le modèle revient au rendu actuel.
- **Rendu** : `documents/pdf.blade.php` bascule vers `documents/blocks.blade.php` dès qu’il y a des blocs. Sans blocs, la sortie est identique octet pour octet (12 cas devis, facture et reçu comparés avant et après, et un test). Les PDF déjà émis restent archivés tels quels, et la publication ne vaut que pour les prochains documents.
- **Pages** : toutes les désignations, puis les frais (pose, livraison), puis le sous-total, les taxes et la remise. Le total général, les conditions de paiement et « Notre engagement » remontent juste sous la dernière ligne ; les signatures et le pied de page restent en place. Au-delà d’une page, le document continue sur des pages suite qui reprennent les blocs marqués « Pages suite » (en-tête), et la dernière page garde les totaux. Les lignes ont une hauteur fixe et les désignations trop longues sont raccourcies (…), pour que la pagination calculée reste juste.
- **Conditions de paiement** : acompte et solde avec leurs montants, acompte reçu et reste à payer, puis les conditions complémentaires si elles sont activées.
- **Total général** : texte blanc sur fond sapin (#1F3A2E) par défaut.
- **Écran** :
  - barre du haut : nom du modèle, état, Devis / Facture / Reçu / Style commun, page unique / page suite, annuler / rétablir (Ctrl+Z, Ctrl+Y), zoom, scénario d’aperçu, brouillon et publication ;
  - à gauche : les blocs à placer et les calques ;
  - au centre : la page A4 avec règles en millimètres, marges, grille de 5 mm aimantée (Alt pour placer librement), et l’onglet « Aperçu PDF » qui passe par `layouts.preview` ;
  - à droite : les propriétés du bloc choisi, puis les réglages du document.
- **Clavier** : chaque bloc se sélectionne au clavier et depuis la liste des calques. Les flèches déplacent de 1 mm, Maj + flèches de 5 mm, Alt + flèches redimensionnent, Suppr retire le bloc et Échap rend la main à la liste.

| Maquette ou demande | Construit | Raison |
|---|---|---|
| L’éditeur « à la place de l’écran de réglages » | Les réglages actuels (mentions légales, couleurs, fonds, marges, introduction) restent, avec les mêmes champs, dans « Réglages du document » | Sans mentions légales, l’émission d’une facture est refusée ; sans JavaScript, c’est le seul écran enregistrable |
| Interrupteur « Continuer sur une page suite » | Toujours actif | Un devis ne doit jamais perdre de lignes |
| Mention « Notre engagement » | Bloc placé sous les conditions dans la mise en page de départ ; vide, il reprend le texte du modèle | Voir le lot 3 |
| Glisser un bloc depuis la palette | Un clic l’ajoute en haut de la zone imprimable, puis il se glisse sur la page | Même résultat, accessible au clavier |
| Poignées sur les huit côtés | Une poignée en bas à droite, et Alt + flèches | Suffisant pour la taille, moins de cibles minuscules |
| Rendu fidèle dans la page de l’éditeur | Contenu indicatif ; le PDF exact est dans « Aperçu PDF » | L’aperçu passe par le moteur PDF réel |
| Une seule page pour le devis | Respecté tant que les lignes tiennent ; sinon des pages suite | Avec les marges par défaut (40 mm en haut, 30 mm en bas), le tableau de départ contient une quinzaine de lignes. Réduisez les marges dans « Fonds et marges » pour en loger davantage |
| Pagination « Page x / y » | Celle du rendu actuel, sous la marge basse | Placée par le moteur PDF, elle n’est pas un bloc |

## Maquette v2, lot 3 : échéancier et « Notre engagement » (03/11/2026)

Accord donné le 03/11/2026. Aucune migration ni route : l’échéancier est enregistré dans le résultat du calcul de chaque devis (`configuration.calculation.financial.schedule`), et le choix dans `configuration.calculation.pricing.schedule`.

- **Calcul** (`QuoteTotals`) : choix `auto`, `two` ou `three` (champ `pricing[schedule]`).
  - `auto` : 2 échéances jusqu’à 500 000 F inclus, 3 au-delà.
  - 2 échéances : l’acompte garde son pourcentage réglable (`pricing[deposit_percent]`, 70 % par défaut), et le solde prend le reste.
  - 3 échéances : 50 % à la validation du chantier, 30 % après installation des rails, 20 % après livraison et pose.
  - Chaque échéance est arrondie au franc, et la dernière reprend le reste : la somme fait toujours le total exact.
- **Acompte enregistré** : `deposit_amount` et `deposit` valent la première échéance. L’état de paiement et l’autorisation de lancer la confection en dépendent, comme avant.
- **Devis existants** : ceux qui ont été chiffrés sans échéancier gardent leur acompte unique et leur rendu PDF, au caractère près (vérifié sur les deux moteurs PDF). Rouvert, un tel devis propose « 2 échéances » au même pourcentage, ce qui conserve ses montants. Les factures et les PDF émis restent figés.
- **Devis de référence** : sous le seuil, avec 60 % choisi, il donne toujours 443 916 FCFA, 266 350 d’acompte et 177 566 de solde. Forcé en 3 échéances : 221 958, 133 175 et 88 783.
- **Étape 4** : choix de l’échéancier, acompte des 2 échéances (déplacé depuis l’étape 3, même champ), tableau des échéances relu dans le dernier calcul, mention « Notre engagement ». Changer de choix relance le calcul.
- **« Notre engagement »** : nouveau réglage `commitment_text` des modèles PDF (Mise en page, rubrique Entreprise). Le texte par défaut reprend la planche (`DocumentLayout::COMMITMENT`). Il s’imprime sous les conditions de paiement des devis à échéancier, avec les deux moteurs PDF, et dans le bloc « Notre engagement » de l’éditeur s’il est laissé vide. Les factures et les reçus ne l’impriment pas.
- **Textes générés** : l’échéancier du PDF, des messages (`{conditions_paiement}`) et de la fiche du devis vient d’une seule fonction (`QuoteFinancials::scheduleText`).

| Point à faire valider | Choix retenu | Raison |
|---|---|---|
| Texte de « Notre engagement » | Celui de la planche, modifiable dans Mise en page | Repris de la maquette et du journal ; il engage la maison, à relire avant publication |
| Seuil de 500 000 F | Total définitif après taxe et remise, 500 000 F inclus dans les 2 échéances | « Jusqu’à 500 000 F » dans la planche |
| Devis de référence à 60 % | L’acompte choisi est conservé dans les 2 échéances | Le réglage existait et le devis de référence en dépend |
| Libellés des échéances | « Acompte à la validation du chantier », « Après installation des rails », « Après livraison et pose », « Solde après livraison et pose » | Repris de la planche |
| Devis par articles (électroménager, meubles) | Non concernés | Ils ont leur propre mode de paiement (avance puis solde) |

## Encore à faire

- Brancher les demandes du nouveau site (dépôt Refonte V2) sur cet espace pro, puis retirer le `/admin` minimal de cette refonte.
- Compléter les mentions légales dans Mise en page : l'émission d'une facture reste refusée tant qu'elles sont vides.
- Fournir un logo en haute définition : le fichier de la maquette est un petit JPG.

## Corrections et améliorations du 04/11/2026

Aucune migration : le rôle est déjà une colonne texte, et les nouvelles données tiennent dans les colonnes JSON existantes (`users.profile`, `quotes.dimensions`). Les images passent par `media_assets`, comme celles du catalogue.

- **Rôles** :
  - **Admin principal** (`admin`) : il a accès à tout.
  - **Manager** (`manager`) : il a le bureau, c'est-à-dire devis et chiffrage, matériaux, rendez-vous, prestations, messagerie, factures, catalogue et clients. Il voit les prix et les PDF chiffrés.
  - **Agent** : rien ne change pour lui.
  - Le nouveau middleware `owner` réserve à l'admin principal les rubriques suivantes : visiteurs, équipe, mise en page, documents et automatisations, images et portfolio.
  - Les routes, leurs noms et leurs URL ne changent pas. Seul leur groupe change. Un manager qui ouvre une rubrique réservée reçoit une erreur 403. Ces rubriques sont aussi retirées de son menu, de sa vue d'ensemble et de la messagerie.
  - Les notifications du bureau partent aussi vers les managers.
- **Boutiques de l'agent** :
  - L'admin principal choisit, dans Équipe, les boutiques de chaque agent : Stores & Rideaux, Électroménager, Meubles. Elles sont rangées dans `profile.shops`.
  - Sans choix enregistré, l'agent a les trois boutiques, comme avant.
  - Le serveur bloque toute boutique refusée. L'adresse ouvre la première boutique autorisée, et un enregistrement forcé reçoit une erreur 403.
- **Agent** :
  - Sur mobile, le bouton « Nouveau devis » devient un rond « + » sans texte, nommé par `aria-label`. Son style reprend le bouton panier du site.
  - Le profil gagne une photo de couverture. Chacun ne lit que la sienne, par la route `profil/couverture`.
  - La disponibilité devient un interrupteur. Les jours de travail et les compétences deviennent des cases à cocher (`days`, `skill_list`).
  - La carte à partager reste dans sa fenêtre flottante. Ses textes se modifient librement et elle propose 3 fonds : vert maison, noir et or, sable. La couverture peut s'y afficher. Rien n'est enregistré : l'image est créée sur l'appareil.
- **Étape 1**, pour les trois formulaires : bureau, agent et articles.
  - Le champ « Ville » devient « Commune » : les 13 communes du district d'Abidjan, puis « Hors Abidjan ». Il est toujours enregistré dans `contact[city]`.
  - Le champ « Adresse » devient « Complément d'adresse », toujours dans `contact[address]`.
  - Une ancienne ville hors liste reste proposée et sélectionnée.
- **Photo d'ouverture** :
  - Un bouton appareil photo facultatif apparaît sur chaque carte d'ouverture.
  - La photo est rangée dans `dimensions[*].photo_media_id`, réduite sur l'appareil au-delà de 1,5 Mo, et refusée au-delà de 2 Mo côté serveur.
  - Elle n'est lisible que par qui peut voir le devis, et seulement si elle appartient à ce devis. Un id d'une autre image envoyé dans le formulaire est ignoré.
  - Le calcul n'envoie jamais la photo.
- **Étape 2, choix communs** :
  - Chaque choix devient une ligne de pastilles, construite à partir des matériaux en base : « Tissu occultant — 60 % / 80 % / 100 % », « Voilage — lourd / luxe / transparent », « Store — lourd / léger », « Motorisé — Oui / Non ».
  - La finition reste un menu, au même style.
  - Les fournitures (rails, ruflettes, accroches) se règlent en Oui / Non, deux rails ou ruflettes au plus par ouverture. Elles s'appliquent aux ouvertures qui suivent les choix communs. Les fournitures proposées dépendent de la finition, selon les règles du catalogue.
  - Les pastilles pilotent les menus existants, qui restent les champs enregistrés.
- **Étape 3, formulaire de calcul** :
  - Coefficients en pastilles : tissu 2 / 2,5 / 3 / Saisir, voilage 2 / 2,5 / 3 / Saisir, ruflette 1 / 1,5 / 2 / Saisir.
  - Pose et livraison : « Valeurs auto » prend la pose du catalogue, ouverture par ouverture. « Saisir » crée un forfait unique pour tout le devis, dans le frais « Pose et livraison ».
  - Ces contrôles remplissent les champs de chaque ouverture et les frais. Le serveur ne les lit pas, donc le calcul et `QuoteCosting` ne changent pas. Sans JavaScript, ils restent cachés et les champs par ouverture servent comme avant.
  - Le devis de référence est inchangé : 266 350 F d'acompte à 60 % ; en 3 échéances, 221 958, 133 175 et 88 783 F.

| Point à faire valider | Choix retenu | Raison |
|---|---|---|
| Manager et Prestations | Accès complet, planification comprise | Le manager a les rendez-vous et les devis, et la prestation en découle |
| Manager et Matériaux et finitions | Accès complet | « Chiffrage » figure dans ses accès |
| Rails « Oui / Non » | Deux au plus par ouverture, le premier comme rail principal et le second comme rail supplémentaire | Le devis n'a que ces deux emplacements |
| Valeurs de la ruflette | 1 / 1,5 / 2 / Saisir | Aucune valeur donnée ; 1 est la valeur actuelle par défaut |
| « Voilage sablé », « Occultant 90 % » | Non affichés | Ils n'existent pas dans le catalogue ; ils apparaîtront dès leur ajout dans Matériaux et finitions |
| Nom « Reflète » des ruflettes | Libellés du catalogue conservés | À renommer dans Matériaux et finitions si vous préférez « Ruflette » |
| Bouton « + » rond | Aussi pour le bureau sur la liste des devis | Harmonisation des écrans du manager et de l'admin |

### Correctif du 04/11/2026 (suite) : quantités en lignes, confection et pose à l'étape 3

| Point | Choix fait | À valider |
|---|---|---|
| Confection voilage | Rideau + voilage sur une même ouverture : le métrage du voilage s'ajoute à la confection des tissus, au même tarif (le calcul n'a qu'une ligne de confection par ouverture). Un voilage seul a son propre tarif « Auto / Saisir ». | Un tarif de confection voilage distinct sur une ouverture mixte demande une ligne de calcul en plus (`QuoteCosting`, `Quotes/*`) : accord nécessaire. |
| Confection voilage par défaut | Non comptée, comme avant, pour ne pas changer les devis existants. | Faut-il la compter par défaut ? |
| Confection store | Ligne visible pour les stores, mais aucun tarif « Confection » store au catalogue. | À créer dans Matériaux et finitions si besoin. |
| Zone de pose | Reprise de la commune de l'étape 1 (« Hors Abidjan » → pose à chiffrer), modifiable à l'étape 3. | — |
| Confection au forfait | Le mode « Au forfait / Montant manuel » par ouverture n'est plus affiché ; l'étape 3 saisit un prix au mètre (ou au m²). | Garder le forfait ? |
| Pose par ouverture | Le prix de pose par ouverture n'est plus saisi à l'étape 2 : « Valeurs auto » (catalogue) ou « Saisir » (forfait unique). | — |
