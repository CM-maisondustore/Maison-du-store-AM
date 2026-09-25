# Espace interne Maison du Store AM

Accès : `/admin/connexion`. Aucun compte client ni inscription publique.

## Rôles

| Fonction | Admin-CM | Agent |
| --- | --- | --- |
| Catalogue, contacts et archives | Gestion complète | Aucun accès global |
| Devis | Affectation, chiffrage, validation, acceptation | Lecture et préparation des devis affectés, avant validation |
| Rendez-vous | Confirmation, déplacement, annulation, affectation | Lecture des rendez-vous affectés |
| Prestations | Planification et affectation | Consignes, coordonnées du client, avancement, compte rendu de ses prestations |
| Factures, messagerie, comptes | Gestion complète | Aucun accès |

Les restrictions sont vérifiées sur le serveur, y compris les téléchargements PDF. Les comptes sont désactivables ; les mots de passe temporaires doivent être remplacés à la première connexion. Le changement de mot de passe invalide les autres sessions via `auth.session`. Les tentatives de connexion sont limitées et les pages internes ne sont ni indexables ni mises en cache.

## Parcours quotidien

1. **Clients & contacts** : retrouver un dossier par nom, e-mail ou téléphone. Les demandes du site créent automatiquement un dossier par adresse e-mail normalisée. Elles ne remplacent pas les coordonnées déjà vérifiées dans le dossier. Les demandes conservent les coordonnées saisies à leur création. Les clients, fournisseurs et partenaires peuvent aussi être créés manuellement.
2. **Devis** : consulter les choix du client, relever les mesures, renseigner les lignes et les prix en FCFA, la taxe applicable et la validité. Admin-CM valide avant envoi, puis enregistre l’acceptation du client avec sa preuve dans les notes internes. Le devis accepté est verrouillé.
3. **Rendez-vous** : confirmer, déplacer ou annuler. Un créneau annulé est libéré sans supprimer la demande. Une réactivation sur un créneau déjà repris est refusée.
4. **Prestations** : planifier un devis sur place, une installation ou un SAV, avec un agent, une durée, une adresse et des consignes. Les chevauchements pour un même agent sont refusés. L’agent retrouve uniquement ses prestations, crée le devis lié et rédige son compte rendu. La clôture exige un compte rendu.
5. **Factures** : préparer depuis un devis accepté, vérifier le PDF, puis émettre. L’émission fige les coordonnées de l’entreprise et du client, les montants et la taxe. Les numéros `MDS-0000001` sont uniques, issus de l’identifiant de la facture et ne sont jamais réutilisés. Enregistrer ensuite le paiement intégral reçu. Les acomptes, paiements partiels, avoirs et intégrations fiscales externes ne sont pas inclus dans cette première version.
6. **Archives** : archiver un dossier masque son utilisation dans les nouvelles affectations sans effacer ses devis, rendez-vous, prestations, factures et messages. Aucun effacement métier n’est exposé dans cette interface.

## Catalogue

Dans **Catalogue**, rechercher un produit par nom ou référence et filtrer sa visibilité, sa disponibilité ou son type. Les statuts se modifient directement dans la liste avec **Enregistrer les statuts** ; la fiche complète permet de modifier les caractéristiques et les coûts.

- **Visible sur le site** : inclut le produit dans le catalogue public. Un produit masqué reste dans la base interne. Les nouveaux produits sont masqués par défaut jusqu’à leur publication explicite.
- **Disponible** : indique la disponibilité indépendamment de la publication. Un produit visible mais non disponible apparaît sur commande.
- **Coûts internes** : matériel, confection, livraison et pose, en FCFA entiers. Chaque poste possède son unité : article, m², mètre linéaire ou forfait. Un montant vide signifie « non renseigné », tandis que 0 représente un coût nul. Aucun total automatique ne mélange des unités différentes.

**Enregistrer le produit et les coûts** sauvegarde les informations en base MySQL ; elles sont rechargées à la réouverture. Les coûts ne sont accessibles qu’à Admin-CM et ne figurent jamais dans l’API publique. Les prix du site restent sur devis. Les coûts constituent des références internes ; ils ne recalculent pas automatiquement les devis existants. Les changements de statuts préservent les coûts et les caractéristiques enregistrés.

La migration additive `2026_09_25_000003` ajoute les montants et leurs unités à `products`, sans modifier les produits existants. Le seed ne remplace pas les réglages saisis dans l’administration.

## Formulaire de devis interne

Dans **Devis → Nouveau devis**, Admin-CM et les agents disposent du même formulaire de préparation. Les agents créent des devis qui leur sont affectés ; Admin-CM peut choisir l’agent. Un devis se sauvegarde en brouillon, puis reste modifiable avant sa validation. Les devis acceptés ou facturés sont verrouillés. Les interventions sur place ouvrent aussi ce formulaire.

1. Saisir le client : nom ou raison sociale, prénom facultatif, téléphone, adresse et e-mail facultatif. L’enregistrement crée ou rattache un dossier client sans compte utilisateur et sans écraser les coordonnées déjà vérifiées dans le CRM.
2. Choisir la catégorie, un type du catalogue en base, la motorisation oui/non, le matériau et la finition. Les produits masqués du site restent utilisables en interne. **Matériaux & finitions**, accessible à Admin-CM depuis Devis, permet de gérer les options et leurs compatibilités par catégorie, type et motorisation.
3. Ajouter jusqu’à 30 fenêtres avec largeur et hauteur en cm, quantité et notes. Les choix communs s’appliquent aux fenêtres qui les utilisent ; décocher cette option ou changer une sélection personnalise uniquement la fenêtre concernée. Un devis peut ainsi contenir des fenêtres de catégories différentes.
4. Renseigner les lignes de vente en FCFA, la taxe, la date du devis, la validité, la livraison et la pose prévues. Le chiffrage commercial est saisi librement ; les coûts internes du catalogue ne sont pas automatiquement transformés en prix de vente. Les dates prévisionnelles ne créent pas d’intervention dans le planning.
5. Choisir avance puis solde ou paiement intégral. Les montants déjà reçus se saisissent séparément avec date, moyen, référence et confirmation de réception. Seul le bouton **Enregistrer le devis** écrit la saisie en base ; aucune sauvegarde automatique dans le navigateur n’est utilisée.

Les choix par fenêtre et leurs libellés sont conservés avec le devis et apparaissent dans le PDF. Les modifications ultérieures des options ne réécrivent pas les devis enregistrés. Une option devenue incompatible doit être remplacée lors de la prochaine modification du formulaire. La mise à jour du formulaire remet un devis en brouillon pour vérification ; Admin-CM conserve la validation et l’acceptation depuis la fiche.

Les versements sont historisés dans `quote_payments` avec l’auteur, sans déclencher de débit. Après sauvegarde, ajouter les nouveaux versements dans **Dates et paiements**. Un paiement ne se modifie pas silencieusement : Admin-CM peut annuler une erreur de saisie avec un motif ; la trace reste consultable. Une annulation de saisie n’est pas un remboursement bancaire. Les cumuls ne peuvent pas dépasser le total, et le total ne peut pas être abaissé sous les montants déjà reçus. Ce suivi reste distinct du statut de règlement des factures.

La migration `2026_09_25_000005` ajoute les options, configurations, dates et versements sans transformer les anciennes demandes. Le seed initialise les options une seule fois. Les jetons d’envoi préviennent les doublons de devis et de paiements ; un contrôle de version signale une modification concurrente du formulaire.

## Images et portfolio

**Images & portfolio** permet de modifier le titre, la description, l’image et la visibilité du hero de l’accueil, des réalisations et de l’illustration de contact. **Ajouter une réalisation** crée une fiche masquée par défaut ; l’ordre numérique détermine sa place dans le portfolio. Les images des produits se modifient dans leur fiche **Catalogue**.

Importer des fichiers JPG, PNG ou WebP de 2 Mo maximum, avec au plus 6000 pixels par côté. Le formulaire permet de remplacer ou retirer une image et de renseigner son texte alternatif. Après une erreur de validation, sélectionner de nouveau le fichier. L’enregistrement du texte et du fichier est atomique.

Les fichiers sont réellement stockés dans MySQL (`media_assets.payload`, encodage base64), et non dans le répertoire public : les sauvegardes MySQL incluent donc les images. La page et l’API ne chargent que leurs métadonnées et leurs URL ; les octets sont servis par une route dédiée. Les images de contenus masqués ne sont pas accessibles publiquement, tandis qu’Admin-CM dispose d’un aperçu protégé. Les remplacements et retraits suppriment les anciens fichiers devenus inutilisés.

La migration `2026_09_25_000004` ajoute ces tables et le lien des images produits. Le seed importe le hero existant et les quatre descriptions initiales une seule fois ; les déploiements suivants préservent toutes les modifications. Le site public de production est rendu par Laravel ; `index.html` reste la maquette statique locale.

## Messagerie Hostinger Email

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
