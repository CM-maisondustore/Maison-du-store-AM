# Déploiement Hostinger Web / Cloud

Site en ligne : **https://www.maisondustoream.com**, dépôt privé **CM-maisondustore/Maison-du-store-AM**, branche `main`. Première publication effectuée le 24 septembre 2026 sur Hostinger Web/Cloud, avec PHP 8.3 et MySQL.

Contrôles en production réussis : accueil et devis, ressources compilées, disponibilité MySQL, catalogue de 14 produits, création des deux types de devis et d’un rendez-vous, refus des créneaux déjà réservés, validation et protection CSRF. La persistance des trois demandes techniques a été vérifiée en base, puis ces seuls enregistrements ont été supprimés. Les fichiers privés sont inaccessibles par HTTP et les cookies de session utilisent Secure/HttpOnly.

La page Hostinger initiale a été conservée dans `.mds/backups/public_html-default-20260924T102701Z`. Le lien `public_html -> .mds/current/public` fonctionne sur cette offre. La préproduction dispose d’un modèle de configuration et d’un environnement GitHub ; aucun site de préproduction n’a encore été déployé.

## Environnements

| Environnement | Configuration | Base | Indexation |
| --- | --- | --- | --- |
| Développement | `Backend/.env` | SQLite locale | Non |
| Tests | `Backend/phpunit.xml` | SQLite en mémoire | Non |
| Préproduction | `.env.staging` à partir du modèle adjacent | MySQL distincte | Non |
| Production | `.env.production` à partir du modèle adjacent | MySQL production | Oui |

Chaque environnement déployé possède sa propre APP_KEY, sa base et son stockage. Les fichiers `.env.production` et `.env.staging`, lorsqu’ils sont présents, sont privés et ignorés par Git. Les deux modèles `*.example` peuvent être publiés. Renseigner les noms MySQL exacts de hPanel et le mot de passe directement dans les fichiers privés, jamais dans Git ou le chat. Conserver les APP_KEY existantes lors des mises à jour.

## Préparer hPanel

1. Ajouter `maisondustoream.com` à l’hébergement, faire pointer le domaine et `www` vers les valeurs DNS fournies par Hostinger. Activer le certificat SSL pour les deux noms et la redirection HTTPS. Ne pas inventer une adresse IP ou remplacer des enregistrements existants sans les vérifier.
2. Choisir **PHP 8.3** pour le Web et vérifier `php -v` en SSH. Activer PDO MySQL, mbstring, intl, fileinfo, OpenSSL, DOM/XML, cURL et ZIP. Le paquet de production utilise Composer 2 lors de sa construction ; Node n’est pas nécessaire sur l’hébergement.
3. Créer une base MySQL et son utilisateur dans hPanel. Copier leurs noms complets, souvent préfixés par l’identifiant du compte, dans le fichier privé de production.
4. Activer SSH, ajouter une clé publique et relever hôte, utilisateur, port et chemin du domaine. Vérifier l’empreinte du serveur avec Hostinger avant de l’enregistrer dans `known_hosts`. Les scripts ne désactivent pas cette vérification.
5. Sauvegarder tout site déjà présent dans `public_html` et exporter sa base. La procédure ci-dessous refuse d’écraser un site existant.

La préproduction est optionnelle : créer `staging.maisondustoream.com` dans hPanel, avec sa propre racine et sa propre base. Le blocage des robots n’est pas un contrôle d’accès ; protéger ce sous-domaine dans hPanel si les essais doivent rester privés. Ne pas y copier les données personnelles de production.

## Arborescence sur le serveur

Le chemin exact dépend de hPanel ; exemple à adapter :

```text
/home/u123456789/domains/maisondustoream.com/
├── .mds/
│   ├── shared/.env                 configuration privée
│   ├── shared/storage/             sessions, cache, journaux persistants
│   ├── releases/<commit>/          code et vendor, sans secrets
│   ├── current -> releases/<commit>
│   ├── previous -> releases/<ancien-commit>
│   └── incoming/                   archives reçues
└── public_html -> .mds/current/public
```

Seul `public/` est exposé. Ne jamais déposer tout le dépôt dans `public_html` ni activer un déploiement Git hPanel à sa racine : cela exposerait le code privé et pourrait afficher la maquette statique.

Vérifier que la configuration du domaine autorise ce lien symbolique. Si l’offre impose un répertoire public réel, demander à Hostinger de faire pointer la racine vers `.mds/current/public` avant d’utiliser cette procédure. Ne pas contourner cette contrainte en exposant le dossier Laravel entier.

## Construire une version

La CI GitHub teste le backend et le frontend, compile Vite et produit l’artefact `hostinger-<commit>`. Télécharger et décompresser cet artefact : il contient l’archive, son empreinte SHA-256 et les scripts serveur. Aucun déploiement automatique n’est déclenché par un push.

Depuis Windows, après avoir enregistré les changements dans Git :

```powershell
.\deploy\build-release.ps1
```

Le script utilise PHP/Node du PATH ou les outils temporaires de cette session. Si Composer n’est pas dans le PATH, on peut passer `-ComposerPhar C:\chemin\composer.phar`. Les archives sont placées dans `artifacts/`, ignoré par Git. Elles contiennent uniquement les dépendances de production et les ressources compilées ; pas de `.env`, base locale, sessions ni journaux. `REVISION` identifie le commit source.

## Première mise en ligne

Après inspection et sauvegarde du site existant, préparer la racine du domaine sur Hostinger. Si `public_html` existe, le renommer dans hPanel en sauvegarde avant la première activation. Le script ne le supprime pas.

Créer `.mds/shared/`, puis transférer le fichier privé `.env.production` en **`.mds/shared/.env`**, hors de la racine publique. Restreindre ses permissions à `600`. Les modèles ne contiennent pas d’APP_KEY : si aucune clé privée n’a été préparée localement, générer une fois une clé avec `php artisan key:generate --show`, puis l’inscrire uniquement dans ce fichier privé.

Depuis Windows, avec les paramètres SSH réels :

```powershell
.\deploy\hostinger\publish.ps1 `
  -SshHost HOTE_HOSTINGER -SshUser UTILISATEUR_HOSTINGER -Port 65002 `
  -DomainRoot /home/UTILISATEUR_HOSTINGER/domains/maisondustoream.com `
  -Archive C:\chemin\hostinger-release.tar.gz `
  -IdentityFile C:\chemin\cle_ssh_privee `
  -KnownHostsFile C:\chemin\known_hosts
```

Adapter le port à celui affiché par hPanel. La clé SSH doit déjà être autorisée ; l’envoi utilise `BatchMode=yes`. Les paramètres `IdentityFile` et `KnownHostsFile` sont optionnels : sur la machine de préparation, le script détecte respectivement `$env:USERPROFILE\.ssh\mds_hostinger` et `artifacts/ssh/known_hosts` s’ils existent. Ne jamais publier la clé privée. Le script vérifie l’empreinte de l’archive, exécute les contrôles de production, migre la base, ajoute le catalogue sans écraser les produits existants, prépare les caches puis active le lien de la nouvelle version. Il ne régénère jamais APP_KEY. Une activation échouée avant le changement de lien conserve le code courant et supprime la version incomplète, ce qui permet de relancer le même commit ; les migrations déjà exécutées restent appliquées. Après activation, seules les 5 versions les plus récentes sont gardées (`KEEP_RELEASES`), `current` et `previous` comprises.

Après extraction, le script rétablit les permissions de traversée des répertoires publics pour le serveur Web Hostinger. Le répertoire partagé reste privé (`700`), ainsi que `.env` et le cache de configuration (`600`).

Les mêmes scripts s’appliquent à la préproduction avec sa racine et sa configuration propres. Tester d’abord la préproduction, puis publier en production une version validée.

## Vérifications après publication

- Accueil et `/devis` en HTTPS ; CSS, JS et favicon chargés sans erreur.
- `/up` répond 200 ; `/health/ready` répond `{"status":"ok"}` et vérifie les trois tables métier.
- `/api/products` retourne le catalogue ; refus 419 sans CSRF et 422 pour des données invalides.
- Envoyer une demande de test identifiée, vérifier sa persistance en base et supprimer uniquement cet enregistrement après vérification.
- `/.env`, `/composer.json`, `/vendor/autoload.php` et `/database/database.sqlite` doivent répondre 404/403, jamais livrer un fichier.
- Les réponses portent `Strict-Transport-Security` quand `APP_FORCE_HTTPS=true` ; la redirection HTTP vers HTTPS reste assurée par hPanel / le CDN Hostinger.
- Vérifier les cookies Secure/HttpOnly, la redirection du domaine nu vers `www` si configurée dans hPanel, et `/robots.txt` selon l’environnement.

## Mises à jour, sauvegardes et retour arrière

Les mises à jour d’une installation existante déclenchent désormais `backup.sh` avant les migrations : export MySQL et copie de la configuration dans `.mds/backups/`, en permissions privées. Le déploiement s’arrête si la préparation, l’export ou la vérification du fichier échoue. L’export est lancé par SSH et reste compatible avec la désactivation de `proc_open` dans PHP chez Hostinger. Les paramètres MySQL temporaires sont supprimés à la fin ; aucun mot de passe n’est placé dans les arguments de commande. Prévoir une copie sécurisée hors serveur et une rotation de ces sauvegardes selon le quota.

La première migration de l’espace interne change la réservation des créneaux tout en conservant les annulations. Un retour au code antérieur à cette migration nécessite une restauration compatible de la base ; le simple script de retour arrière ne suffit pas. Voir [Backend/ADMIN.md](../../Backend/ADMIN.md).

Avant chaque mise à jour, conserver une sauvegarde MySQL récente avec hPanel. Activer les sauvegardes disponibles dans l’offre et vérifier périodiquement une restauration en préproduction. Sauvegarder aussi `.mds/shared/.env` et le stockage privé hors du serveur, de façon sécurisée.

Pour réactiver le code précédent, en SSH :

```bash
bash rollback.sh /home/UTILISATEUR_HOSTINGER/domains/maisondustoream.com
```

Ce retour arrière change uniquement le code. Il ne restaure pas la base : toute migration doit rester compatible avec la version précédente ; une restauration de données exige une décision séparée. Les sauvegardes de `.mds/backups/` ne sont pas supprimées automatiquement. Surveiller l’espace disque et les journaux, avec une rotation adaptée au quota hPanel.

## Publication GitHub

Le dépôt privé existe à l’adresse `https://github.com/CM-maisondustore/Maison-du-store-AM`, avec `main` publiée et les environnements GitHub `staging` et `production`. Les tests PHP sur SQLite et MySQL, les tests JavaScript et la construction de l’archive passent dans GitHub Actions. Aucun secret de production n’est stocké dans Git.

Pour une mise à jour, avec GitHub CLI connecté au bon compte :

```powershell
gh auth login --hostname github.com --git-protocol https --web
gh api user --jq .login
git push origin main
```

Ne pas recréer le dépôt ni forcer un push. Dans GitHub, configurer la protection de `main` et les revues selon les possibilités de l’offre du compte. Ne publier aucun secret dans le dépôt, les artefacts ou les journaux.

Documentation officielle : [Laravel sur Hostinger](https://www.hostinger.com/support/which-programming-languages-and-frameworks-are-supported-at-hostinger/), [Git dans hPanel](https://www.hostinger.com/support/1583302-how-to-deploy-a-git-repository-in-hostinger/), [déploiement Laravel](https://laravel.com/docs/12.x/deployment).
