# Déploiement Hostinger Web / Cloud

Cible : **https://www.maisondustoream.com**, dépôt prévu **CM-maisondustore/maison-du-store-am**, branche `main`. Ce document prépare un déploiement PHP/MySQL sur hPanel. Il ne signifie pas que le site est déjà publié.

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
  -Archive C:\chemin\hostinger-release.tar.gz
```

Adapter le port à celui affiché par hPanel. La clé SSH doit déjà être autorisée ; l’envoi utilise `BatchMode=yes`. Le script vérifie l’empreinte de l’archive, exécute les contrôles de production, migre la base, ajoute le catalogue sans écraser les produits existants, prépare les caches puis active le lien de la nouvelle version. Il ne régénère jamais APP_KEY. Une activation échouée avant le changement de lien conserve le code courant ; les migrations déjà exécutées restent appliquées.

Les mêmes scripts s’appliquent à la préproduction avec sa racine et sa configuration propres. Tester d’abord la préproduction, puis publier en production une version validée.

## Vérifications après publication

- Accueil et `/devis` en HTTPS ; CSS, JS et favicon chargés sans erreur.
- `/up` répond 200 ; `/health/ready` répond `{"status":"ok"}` et vérifie les trois tables métier.
- `/api/products` retourne le catalogue ; refus 419 sans CSRF et 422 pour des données invalides.
- Envoyer une demande de test identifiée, vérifier sa persistance en base et supprimer uniquement cet enregistrement après vérification.
- `/.env`, `/composer.json`, `/vendor/autoload.php` et `/database/database.sqlite` doivent répondre 404/403, jamais livrer un fichier.
- Vérifier les cookies Secure/HttpOnly, la redirection du domaine nu vers `www` si configurée dans hPanel, et `/robots.txt` selon l’environnement.

## Mises à jour, sauvegardes et retour arrière

Avant chaque mise à jour, conserver une sauvegarde MySQL récente avec hPanel. Activer les sauvegardes disponibles dans l’offre et vérifier périodiquement une restauration en préproduction. Sauvegarder aussi `.mds/shared/.env` et le stockage privé hors du serveur, de façon sécurisée.

Pour réactiver le code précédent, en SSH :

```bash
bash rollback.sh /home/UTILISATEUR_HOSTINGER/domains/maisondustoream.com
```

Ce retour arrière change uniquement le code. Il ne restaure pas la base : toute migration doit rester compatible avec la version précédente ; une restauration de données exige une décision séparée. Les anciennes versions ne sont pas supprimées automatiquement. Surveiller l’espace disque et les journaux, avec une rotation adaptée au quota hPanel.

## Publication GitHub

Le dépôt local est préparé pour `https://github.com/CM-maisondustore/maison-du-store-am.git`. La création distante exige une authentification autorisée sur **CM-maisondustore**. Le compte connecté au début de cette intervention (`maisondustoreci-oss`) n’est pas utilisé pour publier.

Avec GitHub CLI connecté au bon compte :

```powershell
gh auth login --hostname github.com --git-protocol https --web
gh api user --jq .login
gh repo create CM-maisondustore/maison-du-store-am --private
git push -u origin main
```

Si le dépôt existe déjà, ne pas le recréer ni forcer un push : examiner son historique d’abord. Si `CM-maisondustore` est une organisation, utiliser un compte membre autorisé. Dans GitHub, protéger `main`, exiger la CI et les revues utiles, puis créer les environnements `staging` et `production` pour la suite. Ne publier aucun secret dans le dépôt, les artefacts ou les journaux.

Documentation officielle : [Laravel sur Hostinger](https://www.hostinger.com/support/which-programming-languages-and-frameworks-are-supported-at-hostinger/), [Git dans hPanel](https://www.hostinger.com/support/1583302-how-to-deploy-a-git-repository-in-hostinger/), [déploiement Laravel](https://laravel.com/docs/12.x/deployment).
