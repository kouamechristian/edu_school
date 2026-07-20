# 🚀 Déploiement en production (EDU-SCHOOL)

Guide de mise en ligne via `git clone` / `git pull` sur le serveur.

## 1. Prérequis serveur
- PHP **8.1+** avec extensions : `ctype`, `iconv`, `intl`, `pdo_mysql`, `gd`, `zip`, `mbstring`
- Composer
- MySQL 8.0 (base `c0eduschool` + utilisateur `c0eduschool`)
- Le `DocumentRoot` du domaine doit pointer sur le dossier **`public/`** du projet.

## 2. Récupération du code
```bash
git clone https://github.com/kouamechristian/edu_school.git
cd edu_school
```

Pour les mises à jour ultérieures :
```bash
git pull origin master
```

## 3. Configuration
Le fichier `.env` est déjà configuré pour la production :
```env
APP_ENV=prod
APP_DEBUG=0
DATABASE_URL="mysql://c0eduschool:EduSchoolDb2026@127.0.0.1:3306/c0eduschool?serverVersion=8.0.0&charset=utf8mb4"
```

> Si tu utilises l'IA, renseigne la clé correspondante
> (`ANTHROPIC_API_KEY`) directement dans `.env`.

## 4. Installation des dépendances (mode prod)
```bash
composer install --no-dev --optimize-autoloader
```

## 5. Base de données
```bash
# Crée la base si elle n'existe pas encore
php bin/console doctrine:database:create --if-not-exists
# Applique toutes les migrations
php bin/console doctrine:migrations:migrate --no-interaction
```

## 6. Assets
```bash
php bin/console importmap:install
php bin/console asset-map:compile
php bin/console ckeditor:install --clear=drop
php bin/console assets:install public
```

## 7. Cache de production
```bash
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

## 8. Permissions (Linux)
```bash
chmod -R 775 var public/uploads
# Ajuste le propriétaire au user du serveur web (ex. www-data)
chown -R www-data:www-data var public/uploads
```

## 9. Paiement en ligne GeniusPay (espace parent)

Le règlement de la scolarité en ligne se configure **par établissement**, dans
*Administration → Établissements → Paiement en ligne*.

### 9.1 URL publique
Renseigner dans `.env.local` l'URL réellement servie :
```env
APP_BASE_URL=https://edu-school.31.207.39.182.nip.io
```
Elle ne sert qu'aux liens générés hors requête HTTP (CLI, e-mails) : pendant une
requête, Symfony utilise l'hôte réel.

### 9.2 Clés marchandes
> ⚠️ **Les clés sont chiffrées avec `APP_SECRET`.** Elles ne sont donc **pas
> transposables d'un environnement à l'autre** : copier la base de développement
> vers la production rendra les clés illisibles (le paiement en ligne se
> désactivera de lui-même, sans erreur visible). **Ressaisir les clés
> directement en production.**

Saisir la clé publique, la clé secrète et le secret de webhook, puis activer.
Utiliser les clés `pk_live_…` / `sk_live_…` pour encaisser réellement.

### 9.3 Webhook
Déclarer dans le tableau de bord GeniusPay l'URL **complète** :
```
https://edu-school.31.207.39.182.nip.io/webhook/geniuspay
```
Événements : `payment.success`, `payment.failed`, `payment.cancelled`, `payment.expired`.

> ⚠️ Le **secret de webhook n'est affiché qu'une seule fois**, à la création.
> Le noter immédiatement : il n'est plus récupérable ensuite, il faudrait
> recréer le webhook.

Une URL en `localhost` ou `127.0.0.1` ne fonctionnera **jamais** : ces adresses
désignent le serveur de GeniusPay. L'écran d'administration le signale.

### 9.4 Contrôle
Cliquer sur « tester » depuis GeniusPay. Réponse attendue :
```json
{"received":true,"test":true,"school":"NOM DE L'ÉTABLISSEMENT"}
```
Un `401` signifie que le secret enregistré ne correspond pas à celui du webhook.

## 10. Vérification
- Accéder à l'URL du domaine → la page de connexion doit s'afficher.
- `/documentation` doit s'ouvrir **sans connexion** (page publique).
- En cas d'erreur 500, consulter `var/log/prod.log`.

## ♻️ Procédure de mise à jour rapide
```bash
git pull origin master
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console asset-map:compile
php bin/console cache:clear --env=prod
```

---
**Note sécurité** : les secrets (mot de passe BDD, `APP_SECRET`) sont versionnés
dans `.env` à la demande. Si le dépôt devient public, pense à régénérer
l'`APP_SECRET` et le mot de passe BDD.
