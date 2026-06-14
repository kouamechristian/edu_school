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

> Si tu utilises GeniusPay ou l'IA, renseigne les clés correspondantes
> (`GENIUSPAY_*`, `ANTHROPIC_API_KEY`) directement dans `.env`.

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

## 9. Vérification
- Accéder à l'URL du domaine → la page de connexion doit s'afficher.
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
