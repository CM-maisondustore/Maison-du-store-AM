# Backend — Maison du Store AM

Application Laravel 12 autonome, avec les vues Blade existantes et Vite. Les demandes sont réellement enregistrées en base. Ouvrir `index.html` à la racine continue de lancer la maquette statique : utiliser le serveur Laravel pour le backend.

## Démarrage sur cette machine

Les dépendances, `.env`, la clé locale et SQLite ont été préparées pendant l’intervention. Depuis la racine du projet, dans PowerShell :

```powershell
.\Backend\serve.ps1
```

Puis ouvrir http://127.0.0.1:8000. Le script utilise PHP du PATH ou, à défaut, le PHP temporaire téléchargé pour les vérifications. Ce dernier disparaîtra si le dossier temporaire est nettoyé ; installer alors PHP normalement.

## Installation sur une autre machine

Prérequis : PHP 8.2+ (extensions PDO SQLite, mbstring, fileinfo, OpenSSL, DOM/XML), Composer 2, Node.js 22.12+ ou 24 LTS avec npm. Depuis `Backend` :

```powershell
composer install
Copy-Item .env.example .env
New-Item -ItemType File database/database.sqlite
php artisan key:generate
php artisan migrate --seed
npm ci
npm run build
php artisan serve
```

Ne copier `.env.example` et ne créer SQLite que lors de la première installation. Le seed ajoute les 14 produits du catalogue original sans écraser les produits existants. Les prix restent « Prix ». SQLite est privé dans `database/`, hors de la racine web. MySQL est aussi configurable via `DB_CONNECTION=mysql` et les variables `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.

## API

Les routes JSON restent dans `routes/web.php` pour conserver la session et le CSRF. Le frontend Blade envoie `Accept: application/json` et `X-CSRF-TOKEN`, avec le jeton fourni dans la page.

| Méthode | URL | Résultat |
| --- | --- | --- |
| GET | `/api/products` | `{ data: [...] }`, catalogue lu en base |
| POST | `/api/quotes/stores` | Devis store enregistré, réponse 201 |
| POST | `/api/quotes/curtains` | Devis rideau enregistré, réponse 201 |
| POST | `/api/appointments` | Rendez-vous demandé, réponse 201 |
| GET | `/api/appointments/availability?date=AAAA-MM-JJ` | Date, fuseau et heures indisponibles |

Les créations répondent `{ data: { reference: "UUID", status: "pending" } }`. Les coordonnées ne sont pas renvoyées. Aucun endpoint public ne permet de lire les demandes des clients.

Devis : `{ contact, product, dimensions, acceptTerms, notes?, submittedAt? }`. Les champs correspondent aux parcours existants ; `acceptTerms` est obligatoire. Les dimensions acceptent la virgule décimale : 1 à 8 fenêtres, largeur/hauteur 20–400 cm, quantité 1–20. Les choix store/rideau sont validés séparément, avec alimentation obligatoire pour un store motorisé. L’heure d’enregistrement et l’acceptation des conditions sont horodatées par le serveur ; `submittedAt` ne fait pas autorité.

Rendez-vous : `{ lastName, firstName, phone, email, appointmentType, project?, message?, date, time, submittedAt? }`. Une contrainte SQL unique sur date/heure empêche les doubles réservations, y compris concurrentes. Les demandes restent en attente de confirmation humaine. Une place est occupée dès l’enregistrement.

Les règles reprennent la maquette : dimanche fermé, délai minimal d’une heure, créneaux 08–11 h et 14–17 h, réservation jusqu’à la fin du troisième mois à venir. Fuseau `Africa/Abidjan`, configurable dans `.env`. Les horaires sont dans `config/appointments.php` et doivent rester cohérents avec le calendrier et les créneaux JS si modifiés.

Erreurs : 422 avec `errors` par champ, 419 si CSRF absent/expiré, 429 après 10 soumissions/minute/IP. Les disponibilités sont limitées à 60 lectures/minute/IP. Le frontend conserve la saisie et affiche les erreurs du serveur.

## Vérifications

```powershell
composer test
npm test
npm run build
php artisan route:list
```

PHPUnit utilise SQLite en mémoire et ne modifie pas les demandes locales. Tests : rendu des vues, catalogue et seed idempotent, devis store/rideau, validation et consentement, doubles réservations, disponibilités, dates interdites, limitation de débit et CSRF. Les tests JS couvrent le jeton CSRF, les URLs Laravel et l’invalidation d’une heure devenue indisponible.

## Exploitation

Pointer le serveur web uniquement vers `Backend/public`. En production : `APP_ENV=production`, `APP_DEBUG=false`, URL HTTPS et `SESSION_SECURE_COOKIE=true`, puis `php artisan migrate --force` et compilation Vite. Conserver une clé APP_KEY stable et sauvegarder la base. Les dossiers `storage` et `bootstrap/cache` doivent être accessibles en écriture.

Cette livraison couvre le contrat des formulaires et du catalogue. Il n’y a pas d’espace administrateur ni d’envoi automatique d’e-mails : les demandes sont persistées dans `quotes` et `appointments` pour traitement interne. Aucun envoi à un tiers n’est effectué. Le statut `pending` ne constitue pas une confirmation définitive de rendez-vous.