# Récapitulatif du 4 octobre 2026 — Refonte locale `Local-CM`

Ce document conserve les procédures utilisées aujourd’hui pour préparer la refonte du site dans une branche séparée, sans modifier ni déployer la production.

## Versioning

Le dépôt privé est `https://github.com/CM-maisondustore/Maison-du-store-AM`.

- `main` est la branche de production.
- `Local-CM` est la branche de travail de refonte (nom exact, avec une majuscule).
- Les deux branches ont été synchronisées jusqu’au commit `a983671`.
- `main` doit rester intact pendant la refonte.
- `RECAP-PROMPT-DEVIS-ESPACE-PRO.txt` est un fichier local non suivi et ne doit pas être ajouté par erreur.

## Synchroniser `Local-CM` avec `main`

Depuis la racine du projet :

```powershell
git status --short --branch
git fetch origin main Local-CM
git switch Local-CM
git merge --no-edit origin/main
git push origin Local-CM
```

Contrôler les références :

```powershell
git branch -vv
git rev-parse main
git rev-parse Local-CM
git ls-remote --heads origin main Local-CM
```

Les deux commandes `git rev-parse` doivent renvoyer le même commit après synchronisation. Ne pas utiliser `git reset --hard` pour résoudre une différence sans sauvegarde préalable.

## Démarrer le site local

La maquette `index.html` ne donne pas accès à l’espace professionnel. Utiliser le serveur Laravel.

```powershell
git switch Local-CM
.\Backend\serve.ps1
```

URL par défaut : `http://127.0.0.1:8000`.

Pour un autre port :

```powershell
.\Backend\serve.ps1 -Port 8024
```

Pages utiles :

```text
Site public       http://127.0.0.1:8000/
Formulaire devis  http://127.0.0.1:8000/devis
Connexion pro     http://127.0.0.1:8000/admin/connexion
Tableau de bord   http://127.0.0.1:8000/admin
```

Arrêter le serveur avec `Ctrl + C`. La base SQLite locale est indépendante de la base Hostinger.

## Créer les comptes pro locaux

Depuis un second terminal :

```powershell
cd Backend
php artisan staff:create Admin-CM contact@maisondustoream.com --admin
```

La commande demande le mot de passe sans l’afficher. Utiliser un mot de passe local dédié et ne jamais le mettre dans Git.

Créer un agent local :

```powershell
php artisan staff:create Agent-CM agent-local@maisondustoream.com
```

Les comptes de production ne sont pas automatiquement présents dans SQLite local.

## Vérifications fonctionnelles locales

Tester sur ordinateur puis autour de 390 px de largeur :

1. Ouvrir `/` et `/devis`.
2. Parcourir les devis Store et Rideaux.
3. Vérifier le pays/indicatif avant le téléphone.
4. Vérifier `Motorisé : Oui / Non`.
5. Vérifier les validations, les dimensions, le récapitulatif et la confirmation.
6. Vérifier le calendrier avec une date et une heure de test.
7. Vérifier la console navigateur et l’absence de défilement horizontal.

Les demandes locales peuvent être écrites dans SQLite. Employer des coordonnées fictives identifiables ; ne pas envoyer de communication commerciale réelle.

## Tests Meta Pixel

Pixel : `1407495181585849`.

- `PageView` est envoyé après acceptation des cookies.
- `Contact` est envoyé après confirmation d’un devis ou d’un rendez-vous.
- `Schedule` est envoyé après confirmation d’un rendez-vous.
- Aucun événement n’est envoyé après refus du consentement.
- L’espace pro n’est pas suivi.
- Aucun champ client n’est ajouté aux événements.

Le domaine local ne charge pas le SDK Meta. Pour réinitialiser le consentement dans le navigateur :

```js
localStorage.removeItem('mds-advertising-consent-v1');
location.reload();
```

Après acceptation, les fonctions peuvent être vérifiées dans la console :

```js
MDS.metaPixel.trackContact();
MDS.metaPixel.trackSchedule();
```

## Test réel dans Meta

1. Ouvrir le [Gestionnaire d’événements Meta](https://business.facebook.com/events_manager/).
2. Sélectionner le Pixel `1407495181585849` puis **Tester les événements**.
3. Ouvrir `https://www.maisondustoream.com` dans une fenêtre privée.
4. Accepter les cookies et vérifier `PageView`.
5. Envoyer un devis de test avec ses propres coordonnées et vérifier `Contact`.
6. Confirmer un rendez-vous de test et vérifier `Contact`, puis `Schedule`.

Un test réel peut créer un contact, un devis, un rendez-vous et des notifications. Ne pas multiplier les faux envois. Pour tester un refus, recommencer en fenêtre privée, cliquer **Refuser**, recharger et vérifier qu’aucun événement n’est reçu.

## Tests techniques avant commit

Depuis `Backend` :

```powershell
composer test
npm test
npm run build
```

Depuis la racine :

```powershell
git status --short
git diff --check
git diff --stat
```

## Enregistrer une refonte

Toujours vérifier la branche active avant de modifier :

```powershell
git switch Local-CM
git status --short --branch
```

Puis :

```powershell
git add <fichiers-modifiés>
git diff --cached --check
git commit -m "Décrire clairement la refonte"
git push origin Local-CM
```

Ne jamais déployer directement `Local-CM` sur Hostinger. Cette branche sert à développer et relire la refonte ; la production reste liée à `main`.

## Passage vers la production

1. Terminer et tester `Local-CM`.
2. Faire relire les changements.
3. Fusionner explicitement `Local-CM` vers `main`.
4. Vérifier le commit de `main`.
5. Lancer la CI complète.
6. Déployer uniquement l’artefact validé de `main`.
7. Vérifier `/health/ready`, les formulaires, l’espace pro et les empreintes des données/PDF.

Tant que la fusion n’est pas faite, les changements restent séparés de la production.

## Secrets à ne jamais archiver

Ne pas copier dans ce fichier : mots de passe, clé SSH Hostinger, jeton Meta, identifiants API, contenu de la base client ou archives PDF. Les secrets restent dans les fichiers privés locaux ou les variables sécurisées du serveur.
