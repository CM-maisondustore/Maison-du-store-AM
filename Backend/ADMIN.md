# Espace interne Maison du Store AM

Accès : `/admin/connexion`. Aucun compte client ni inscription publique.

## Chiffrage depuis le catalogue

Dans **Catalogue → Tarifs, matériaux et finitions des devis**, Admin-CM modifie les libellés, prix en FCFA, unités, compatibilités et disponibilités. Les matériaux, finitions et supports cochés « demande de devis publique » alimentent le formulaire public sans exposer leurs prix. Les coûts généraux d'une fiche produit restent distincts de cette grille de chiffrage par composant.

Les tarifs initiaux ajoutés par `QuoteTariffSeeder` sont conservés en base : occultants 100/80/60 % à 12 000/9 000/7 000 FCFA/m, voilages transparent/luxe à 4 000/14 000 FCFA/m, rails double/simple/Wave électrique/Wave manuel à 10 000/5 000/22 000/7 000 FCFA/m, barres double/simple à 7 000/4 000 FCFA/m, reflètes 4 files/3 files/Wave à 900/500/1 500 FCFA/m, accroches à 2 000 FCFA le lot de 100, confection à 1 000 FCFA/m, stores lourd/léger à 25 000/18 000 FCFA/m², kit moteur store à 100 000 FCFA/pièce et pose store à Abidjan à 5 000 FCFA/ouverture. Les anciens choix sont conservés. Relancer le seed n'écrase aucun tarif modifié.

Les 14 fiches produits initiales ont été retirées du catalogue par la migration `2026_09_29_000010` (archivage `deleted_at`). Le seed ne les recrée plus. Les dossiers, devis et images historiques sont conservés. Les sélections de devis utilisent directement les matériaux et finitions, sans dépendre de ces fiches.

Dans le formulaire de devis, l’agent sélectionne séparément le tissu rideau et le voilage, ou un store. Les fournitures se choisissent par cases à cocher, avec leurs quantités et les finitions compatibles. Admin-CM gère les groupes de tissus dans **Tarifs, matériaux et finitions**. L’e-mail client est facultatif, y compris sur la demande publique.

Les agents préparent uniquement les mesures, choix, dates et notes de terrain. Les tarifs, lignes de vente, totaux, paiements, notes internes et PDF chiffrés sont réservés à Admin-CM, avec contrôle serveur. Une modification technique par un agent conserve les montants précédents et exige une nouvelle vérification du formulaire par Admin-CM avant validation.

Pour le chiffrage réservé à Admin-CM :

- Saisir chaque fenêtre, puis les quantités **pour une ouverture**. La quantité de fenêtres identiques multiplie ces postes. Le tissu, les supports, les reflètes et la confection sont métrés manuellement. Aucun coefficient de plis n'est supposé.
- Pour les matériaux au m², la surface est largeur × hauteur en cm / 10 000. Les accroches se saisissent en lots entiers de 100.
- Choisir les composants compatibles avec la finition. La motorisation des stores exige le kit moteur. Le rail Wave électrique des rideaux est facturé au mètre ; aucun kit store n'est ajouté aux rideaux.
- Saisir le prix de pose des rideaux dans chaque devis. Hors Abidjan, la pose des stores doit aussi être chiffrée explicitement. Un prix vide est **à définir**, pas zéro. Un brouillon incomplet est conservable mais ne peut pas être validé ; le PDF signale les postes non inclus.
- Cliquer **Calculer depuis le catalogue** : cela remplace les lignes actuelles après confirmation. Les montants sont arrondis au FCFA par poste et ouverture, puis multipliés par le nombre d'ouvertures. Les prix unitaires des lignes représentent ainsi le poste complet pour une ouverture ; le détail du métrage et du tarif apparaît dans la désignation.
- Ajuster librement les lignes commerciales, puis enregistrer. Tout changement de mesures, composants ou quantités exige un nouveau calcul. Les tarifs utilisés, les quantités et le calcul initial sont archivés dans `quotes.configuration`, distincts des lignes commerciales ajustées. Un changement ultérieur du catalogue ne recalcule pas les devis enregistrés.
- Le mode **Saisie entièrement manuelle** reste disponible pour les dossiers particuliers et anciens devis : l'opérateur renseigne alors toutes les prestations, dont la pose, dans les lignes.

Le calcul est effectué côté serveur, sans sauvegarde de dossier pendant la préparation. Un résultat signé empêche de modifier silencieusement le détail calculé ; les lignes commerciales restent volontairement éditables. Les devis acceptés/facturés restent verrouillés.

Les neuf anciens matériaux (lin, coton, polyester, velours, voilage générique, toile textile, toile screen, aluminium, bois) ont été retirés du catalogue et des choix de devis par la migration `2026_09_29_000009`. Leurs entrées sont archivées via `deleted_at` ; les descriptions et tarifs enregistrés sur les devis restent inchangés. Le seed ne les recrée plus. Les finitions et les matériaux de la nouvelle grille sont conservés.

## Rôles

| Fonction | Admin-CM | Agent |
| --- | --- | --- |
| Catalogue, contacts et archives | Gestion complète | Aucun accès global |
| Devis | Affectation, chiffrage, validation, acceptation | Préparation des devis affectés avant validation ; consultation technique des devis liés à ses prestations non annulées |
| Rendez-vous | Confirmation, déplacement, annulation, affectation | Lecture des rendez-vous affectés |
| Prestations | Planification et affectation | Consignes, coordonnées du client, avancement, compte rendu de ses prestations |
| Factures, messagerie, comptes | Gestion complète | Aucun accès |

Les restrictions sont vérifiées sur le serveur, y compris les téléchargements PDF. Les comptes sont désactivables ; les mots de passe temporaires doivent être remplacés à la première connexion. Le changement de mot de passe invalide les autres sessions via `auth.session`. Les tentatives de connexion sont limitées et les pages internes ne sont ni indexables ni mises en cache.

Un agent peut ouvrir le devis lié à sa prestation même si le devis est affecté à un autre agent ou à aucun agent. Cet accès porte uniquement sur les informations techniques, sans prix ni PDF chiffré, et n’autorise pas la modification du devis. Le lien doit concerner le même client. Réaffecter ou annuler la prestation retire cet accès indirect ; l’affectation propre du devis conserve ses droits habituels.

## Parcours quotidien

1. **Clients & contacts** : retrouver un dossier par nom, e-mail ou téléphone. Les demandes du site créent automatiquement un dossier par adresse e-mail normalisée. Elles ne remplacent pas les coordonnées déjà vérifiées dans le dossier. Les demandes conservent les coordonnées saisies à leur création. Les clients, fournisseurs et partenaires peuvent aussi être créés manuellement.
2. **Devis** : consulter les choix du client, relever les mesures, renseigner les lignes et les prix en FCFA, la taxe applicable et la validité. Admin-CM valide avant envoi, puis enregistre l’acceptation du client avec sa preuve dans les notes internes. Le devis accepté est verrouillé.
3. **Rendez-vous** : confirmer, déplacer ou annuler. Un créneau annulé est libéré sans supprimer la demande. Une réactivation sur un créneau déjà repris est refusée.
4. **Prestations** : planifier un devis sur place, une installation ou un SAV, avec un agent, une durée, une adresse et des consignes. Les chevauchements pour un même agent sont refusés. L’agent retrouve uniquement ses prestations, crée le devis lié et rédige son compte rendu. La clôture exige un compte rendu.
5. **Factures** : préparer depuis un devis accepté, vérifier le PDF, puis émettre. L’émission fige les coordonnées de l’entreprise et du client, les montants et la taxe. Les numéros `MDS-0000001` sont uniques, issus de l’identifiant de la facture et ne sont jamais réutilisés. Enregistrer ensuite le paiement intégral reçu. Les acomptes, paiements partiels, avoirs et intégrations fiscales externes ne sont pas inclus dans cette première version.
6. **Archives** : archiver un dossier masque son utilisation dans les nouvelles affectations sans effacer ses devis, rendez-vous, prestations, factures et messages. Aucun effacement métier n’est exposé dans cette interface.

## Catalogue

En haut de **Catalogue**, Admin-CM peut choisir **Masquer le catalogue** ou **Afficher le catalogue**. Le réglage est enregistré dans `site_settings` et retire les sections produits, leurs liens de navigation et les résultats de l’API publique. Le catalogue reste disponible dans l’espace interne et les formulaires de devis ; les visibilités individuelles des produits sont conservées à la réactivation. Par défaut, le catalogue reste affiché. Les changements sont inscrits dans le journal d’audit.

Dans **Catalogue**, rechercher un produit par nom ou référence et filtrer sa visibilité, sa disponibilité ou son type. Les statuts se modifient directement dans la liste avec **Enregistrer les statuts** ; la fiche complète permet de modifier les caractéristiques et les coûts.

- **Visible sur le site** : inclut le produit dans le catalogue public. Un produit masqué reste dans la base interne. Les nouveaux produits sont masqués par défaut jusqu’à leur publication explicite.
- **Disponible** : indique la disponibilité indépendamment de la publication. Un produit visible mais non disponible apparaît sur commande.
- **Coûts internes** : matériel, confection, livraison et pose, en FCFA entiers. Chaque poste possède son unité : article, m², mètre linéaire ou forfait. Un montant vide signifie « non renseigné », tandis que 0 représente un coût nul. Aucun total automatique ne mélange des unités différentes.

**Enregistrer le produit et les coûts** sauvegarde les informations en base MySQL ; elles sont rechargées à la réouverture. Les coûts ne sont accessibles qu’à Admin-CM et ne figurent jamais dans l’API publique. Les prix du site restent sur devis. Les coûts constituent des références internes ; ils ne recalculent pas automatiquement les devis existants. Les changements de statuts préservent les coûts et les caractéristiques enregistrés.

La migration additive `2026_09_25_000003` ajoute les montants et leurs unités à `products`, sans modifier les produits existants. Le seed ne remplace pas les réglages saisis dans l’administration.

## Formulaire de devis interne

Dans **Devis → Nouveau devis**, Admin-CM et les agents disposent du même formulaire de préparation. Les agents créent des devis qui leur sont affectés ; Admin-CM peut choisir l’agent. Un devis se sauvegarde en brouillon, puis reste modifiable avant sa validation. Les devis acceptés ou facturés sont verrouillés. Les interventions sur place ouvrent aussi ce formulaire.

1. Saisir le client : nom ou raison sociale, prénom facultatif, téléphone, adresse et e-mail facultatif. L’enregistrement crée ou rattache un dossier client sans compte utilisateur et sans écraser les coordonnées déjà vérifiées dans le CRM.
2. Choisir la catégorie, puis cocher séparément un tissu rideau et/ou un voilage, ou un matériau de store. Sélectionner la motorisation, la finition et les fournitures compatibles. **Matériaux & finitions**, accessible à Admin-CM, permet de modifier ces choix en base.
3. Ajouter jusqu’à 30 fenêtres avec largeur et hauteur en cm, quantité et notes. Les choix communs s’appliquent aux fenêtres qui les utilisent ; décocher cette option ou changer une sélection personnalise uniquement la fenêtre concernée. Un devis peut ainsi contenir des fenêtres de catégories différentes.
4. Renseigner la date du devis, la validité, la livraison et la pose prévues. Admin-CM calcule ou saisit les lignes de vente et la taxe. Les dates prévisionnelles ne créent pas d’intervention dans le planning.
5. Admin-CM choisit avance puis solde ou paiement intégral et consigne les montants reçus avec date, moyen, référence et confirmation. L’agent ne voit pas ces informations. Seul **Enregistrer le devis** écrit la saisie en base.

Les choix par fenêtre et leurs libellés sont conservés avec le devis et apparaissent dans le PDF. Les modifications ultérieures des options ne réécrivent pas les devis enregistrés. Une option devenue incompatible doit être remplacée lors de la prochaine modification du formulaire. La mise à jour du formulaire remet un devis en brouillon pour vérification ; Admin-CM conserve la validation et l’acceptation depuis la fiche.

Les versements sont historisés dans `quote_payments` avec l’auteur, sans déclencher de débit. Après sauvegarde, ajouter les nouveaux versements dans **Dates et paiements**. Un paiement ne se modifie pas silencieusement : Admin-CM peut annuler une erreur de saisie avec un motif ; la trace reste consultable. Une annulation de saisie n’est pas un remboursement bancaire. Les cumuls ne peuvent pas dépasser le total, et le total ne peut pas être abaissé sous les montants déjà reçus. Ce suivi reste distinct du statut de règlement des factures.

La migration `2026_09_25_000005` ajoute les options, configurations, dates et versements sans transformer les anciennes demandes. Le seed initialise les options une seule fois. Les jetons d’envoi préviennent les doublons de devis et de paiements ; un contrôle de version signale une modification concurrente du formulaire.

## Images et portfolio

**Images & portfolio** permet de modifier le titre, la description, l’image et la visibilité du hero de l’accueil, des réalisations et de l’illustration de contact. **Ajouter une réalisation** crée une fiche masquée par défaut ; l’ordre numérique détermine sa place dans le portfolio. Les images des produits se modifient dans leur fiche **Catalogue**.

Importer des fichiers JPG, PNG ou WebP de 2 Mo maximum, avec au plus 6000 pixels par côté. Le formulaire permet de remplacer ou retirer une image et de renseigner son texte alternatif. Après une erreur de validation, sélectionner de nouveau le fichier. L’enregistrement du texte et du fichier est atomique.

Les fichiers sont réellement stockés dans MySQL (`media_assets.payload`, encodage base64), et non dans le répertoire public : les sauvegardes MySQL incluent donc les images. La page et l’API ne chargent que leurs métadonnées et leurs URL ; les octets sont servis par une route dédiée. Les images de contenus masqués ne sont pas accessibles publiquement, tandis qu’Admin-CM dispose d’un aperçu protégé. Les remplacements et retraits suppriment les anciens fichiers devenus inutilisés.

La migration `2026_09_25_000004` ajoute ces tables et le lien des images produits. Le seed importe le hero existant et les quatre descriptions initiales une seule fois ; les déploiements suivants préservent toutes les modifications. Le site public de production est rendu par Laravel ; `index.html` reste la maquette statique locale.

## Messagerie Hostinger Email

### Documents & automatisations

Admin-CM dispose de `/admin/communications`. Les modèles PDF, e-mail et WhatsApp sont enregistrés dans `communication_templates`, avec version et contrôle des modifications concurrentes. L’aperçu utilise des données fictives et ne sauvegarde ni n’envoie rien. Le logo PDF accepte PNG/JPEG de 512 Ko maximum et 2000 pixels par côté ; les textes sont échappés, aucun HTML ou code de modèle n’est exécuté. La couleur, l’introduction, la signature, le pied de page et les coordonnées/conditions de l’émetteur sont modifiables. Les montants restent calculés depuis les données métier.

Les factures nouvellement émises et les PDF joints aux nouveaux e-mails sont archivés dans `document_archives` (octets en base64, taille, SHA-256). Le téléchargement restitue le fichier exact. Les anciens messages ne disposant que d’un instantané de données restent téléchargeables avec la mention « reconstitué » ; aucun fichier historiquement envoyé n’est inventé rétroactivement. Les archives entrent dans la sauvegarde MySQL habituelle.

Le formulaire de message propose un canal et un modèle. Modifier un brouillon programmé le repasse en envoi manuel. Si les coordonnées ou les données du document ont changé depuis la préparation, l’envoi est bloqué et un nouveau brouillon doit être préparé. Les brouillons annulés restent dans l’historique. Les promotions nécessitent le consentement enregistré et ne font pas partie des règles automatiques.

Les cinq règles e-mail couvrent l’accusé de réception d’une demande du site, le devis validé, la facture émise, le rendez-vous confirmé et son rappel. Elles sont **désactivées par défaut**. Modes disponibles : désactivée, brouillon à valider, envoi automatique. L’envoi automatique exige une connexion SMTP vérifiée depuis cette page (authentification sans envoi). Un changement de configuration invalide le contrôle. Une erreur de transport marque l’envoi « à vérifier » et suspend les envois automatiques jusqu’à un nouveau contrôle ; aucun renvoi aveugle n’est effectué.

Les nouvelles demandes/validations sont prises en compte à partir de l’activation de la règle ; les rappels concernent les rendez-vous à venir dans le délai choisi (1 à 168 heures). Aucun rappel n’est envoyé pour un rendez-vous annulé ou passé. Une clé unique empêche les doublons par document/événement ou créneau. Une replanification permet un nouveau rappel pour le nouveau créneau. Un devis déjà traité automatiquement n’est pas renvoyé automatiquement à chacune de ses modifications : préparer explicitement un nouveau brouillon.

Sur Hostinger, ajouter dans **hPanel → Tâches cron → Personnalisée/Autre** une exécution toutes les cinq minutes (`*/5 * * * *`) de :

```text
/usr/bin/flock -n /home/u254587528/domains/maisondustoream.com/.mds/communications.lock /usr/bin/php /home/u254587528/domains/maisondustoream.com/.mds/current/artisan communications:process --limit=100
```

Le chemin `.mds/current` suit les déploiements. `flock` empêche deux traitements planifiés simultanés. L’espace pro affiche le dernier passage ; le bouton **Exécuter les règles actives maintenant** permet un traitement ponctuel avec confirmation. La commande n’envoie rien tant que les règles restent désactivées ou en mode brouillon. Elle n’affiche que des compteurs, jamais le contenu des messages.

WhatsApp est préparé pour le numéro professionnel **+2250712449319**. Un brouillon ouvre `wa.me` avec le numéro du client et le texte ; le PDF est à joindre manuellement depuis la fiche. L’application WhatsApp connectée doit être celle du compte professionnel. « Marquer comme partagé » enregistre une déclaration de l’opérateur, pas une preuve de livraison. Aucun envoi API WhatsApp ni suivi de lecture n’est activé ; cette étape nécessite la connexion Business Platform Meta.

Expéditeur et adresse de réponse : `contact@maisondustoream.com`.

Dans le fichier privé de l’environnement, configurer :

```dotenv
COMMERCIAL_MAIL_ENABLED=true
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=contact@maisondustoream.com
MAIL_PASSWORD="mot de passe à renseigner uniquement dans le fichier privé"
IMAP_HOST=imap.hostinger.com
IMAP_PORT=993
```

Les connexions SMTP et IMAP utilisent TLS avec vérification des certificats. Le mot de passe n’est jamais saisi dans une page publique ni stocké dans Git. Après changement de configuration sur le serveur : `php artisan config:cache`, puis `chmod 600 bootstrap/cache/config.php`.

- **Envoi** : les confirmations de devis, factures et rendez-vous se préparent depuis le dossier correspondant. Les échanges libres peuvent viser un client, fournisseur ou partenaire. Tous les envois passent par un brouillon relu puis envoyé explicitement par Admin-CM. Aucune campagne ou réponse automatique n’est lancée.
- **Documents** : devis validés et factures émises peuvent être joints en PDF. Le destinataire doit correspondre au client du document. Les données du PDF sont figées au moment de l’envoi et restent consultables dans le message archivé.
- **Promotions** : l’envoi exige un consentement commercial actif et documenté dans le dossier. L’acceptation des conditions d’un devis ne vaut pas consentement aux promotions. Ajouter au message la manière de demander l’arrêt des communications ; retirer ensuite le consentement dans le dossier.
- **Lecture** : « Relever les e-mails » importe une page de 20 messages de la boîte INBOX, du plus récent au plus ancien. Le numéro de page permet de remonter l’historique. L’identité IMAP évite les doublons. Lecture en texte échappé uniquement ; aucune image externe ou HTML actif. Les messages de plus de 1 Mo et leurs pièces jointes restent consultables dans [le webmail Hostinger](https://mail.hostinger.com).
- **Limites de synchronisation** : import manuel de la boîte de réception, sans suppression ni modification des messages sur Hostinger. L’état « lu » est local à l’application. Les messages envoyés sont conservés dans l’application ; leur copie dans le dossier « Envoyés » du webmail n’est pas automatique.
- **Incident SMTP** : l’état « Envoi à vérifier » évite un renvoi automatique potentiellement en double. Vérifier la situation dans Hostinger avant de préparer un autre message. « Envoyé » indique l’acceptation SMTP, pas la lecture ou la remise définitive au destinataire.

Paramètres officiels : [Hostinger Email — configuration des applications](https://support.hostinger.com/en/articles/4305847-set-up-hostinger-email-on-your-applications-and-devices). Architecture : [authentification Laravel](https://laravel.com/docs/12.x/authentication), [mail Laravel](https://laravel.com/docs/12.x/mail), [bibliothèque PHP-IMAP](https://www.php-imap.com/).

## Paramètres de facturation

Renseigner `BILLING_NAME`, `BILLING_ADDRESS`, `BILLING_REGISTRATION`, `BILLING_TAX_NOTE` et `BILLING_PAYMENT_TERMS` avec les informations de l’entreprise. Tant que les mentions sont incomplètes, les factures restent en brouillon. Le taux de taxe est saisi sur chaque devis ; aucun taux fiscal n’est déduit automatiquement. Les PDF sont des documents commerciaux internes ; aucune homologation ou transmission à une plateforme fiscale n’est implémentée.

## Installation et première connexion

Sauvegarder MySQL et le fichier privé avant les migrations. Aucun compte par défaut ni mot de passe public n’est créé par les migrations ou le seed.

```bash
php artisan migrate --force
php artisan staff:create Admin-CM contact@maisondustoream.com --admin
```

La commande demande le mot de passe sans l’afficher. Pour un déploiement automatisé, `--password-file=/chemin/prive` permet de le lire sans argument secret dans la ligne de commande. Supprimer ensuite ce fichier temporaire de manière ciblée. Les autres comptes sont créés dans **Équipe**. Pour réinitialiser le seul compte administrateur, exécuter `php artisan staff:password Admin-CM` sur le serveur ; aucune réinitialisation publique non vérifiée n’est exposée.

La migration `2026_09_24_000002` rattache les demandes existantes aux contacts et remplace la contrainte de rendez-vous par un emplacement réservé nullable pour conserver les annulations. Mettre le site en maintenance pendant cette première migration et l’activation du nouveau code. Le retour à une version antérieure à cette migration exige un plan de restauration de base : un simple changement du lien de code serait insuffisant. La méthode `down()` refuse donc d’effacer les archives automatiquement.

## Vérifications

`composer test` couvre les droits, connexions, restrictions des agents, historique, planning, facturation PDF, destinataires des documents, consentements et doubles envois. La CI les exécute sur SQLite et MySQL. Les tests SMTP utilisent un transport simulé et n’envoient aucun message à un client. La connexion réelle à Hostinger nécessite le mot de passe privé.
