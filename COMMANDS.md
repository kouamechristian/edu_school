# ⚙️ Guide des Commandes - EDU-SCHOOL

## 🚀 Commandes Essentielles

### Installation Initiale

```bash
# 1. Installer les dépendances
composer install

# 2. Créer la base de données
php bin/console doctrine:database:create

# 3. Exécuter les migrations
php bin/console doctrine:migrations:migrate

# 4. Charger les données de test
php bin/console doctrine:fixtures:load --append

# 5. Vider le cache
php bin/console cache:clear

# 6. Installer les assets
php bin/console assets:install
```

---

## 🗄️ Commandes Base de Données

### Gestion de la Base

```bash
# Créer la base de données
php bin/console doctrine:database:create

# Supprimer la base de données
php bin/console doctrine:database:drop --force

# Valider le schéma
php bin/console doctrine:schema:validate

# Voir les informations de mapping
php bin/console doctrine:mapping:info
```

### Migrations

```bash
# Créer une nouvelle migration
php bin/console make:migration

# Voir le statut des migrations
php bin/console doctrine:migrations:status

# Exécuter les migrations
php bin/console doctrine:migrations:migrate

# Exécuter une migration spécifique
php bin/console doctrine:migrations:migrate Version20251009200013

# Revenir en arrière (rollback)
php bin/console doctrine:migrations:migrate prev
```

### Fixtures

```bash
# Charger toutes les fixtures (écrase les données)
php bin/console doctrine:fixtures:load

# Charger en ajoutant (sans écraser)
php bin/console doctrine:fixtures:load --append

# Charger sans confirmation
php bin/console doctrine:fixtures:load -n
```

### Requêtes SQL

```bash
# Exécuter une requête SQL
php bin/console doctrine:query:sql "SELECT * FROM user"

# Compter les utilisateurs
php bin/console doctrine:query:sql "SELECT COUNT(*) as total FROM user"

# Voir tous les établissements
php bin/console doctrine:query:sql "SELECT id, name, code, type FROM school"

# Voir tous les utilisateurs
php bin/console doctrine:query:sql "SELECT id, username, email, user_type FROM user"
```

---

## 🔧 Commandes de Développement

### Création d'Entités

```bash
# Créer une nouvelle entité
php bin/console make:entity

# Exemple: Créer Student
php bin/console make:entity Student

# Ajouter des champs à une entité existante
php bin/console make:entity User
# Puis ajouter les champs interactivement
```

### Création de Contrôleurs

```bash
# Créer un contrôleur
php bin/console make:controller StudentController

# Créer un CRUD complet
php bin/console make:crud Student
```

### Création de Formulaires

```bash
# Créer un formulaire
php bin/console make:form

# Créer un formulaire pour une entité
php bin/console make:form StudentType Student
```

### Création de Commands

```bash
# Créer une command
php bin/console make:command app:my-command
```

---

## 🔒 Commandes Sécurité

### Gestion des Utilisateurs

```bash
# Créer un administrateur (notre command personnalisée)
php bin/console app:create-admin

# Hasher un mot de passe
php bin/console security:hash-password

# Vérifier les vulnérabilités
composer audit

# Check de sécurité Symfony
symfony check:security
```

---

## 🧹 Commandes de Maintenance

### Cache

```bash
# Vider le cache
php bin/console cache:clear

# Vider le cache de production
php bin/console cache:clear --env=prod

# Préchauffer le cache
php bin/console cache:warmup

# Supprimer un pool de cache spécifique
php bin/console cache:pool:clear cache.app
```

### Logs

```bash
# Voir les logs en temps réel (Linux/Mac)
tail -f var/log/dev.log

# Windows PowerShell
Get-Content var/log/dev.log -Wait

# Supprimer les vieux logs
rm var/log/*.log
```

---

## 🔍 Commandes de Débogage

### Routes

```bash
# Lister toutes les routes
php bin/console debug:router

# Rechercher une route
php bin/console debug:router | findstr "user"
php bin/console debug:router | findstr "school"

# Voir les détails d'une route
php bin/console debug:router admin_user_index

# Tester une route
php bin/console router:match /admin/users
```

### Services

```bash
# Lister tous les services
php bin/console debug:container

# Rechercher un service
php bin/console debug:container user

# Voir les détails d'un service
php bin/console debug:container App\Repository\UserRepository
```

### Configuration

```bash
# Voir toute la configuration
php bin/console debug:config

# Voir la config d'un bundle
php bin/console debug:config framework
php bin/console debug:config security
php bin/console debug:config doctrine

# Voir les paramètres
php bin/console debug:container --parameters
```

### Events

```bash
# Lister les événements
php bin/console debug:event-dispatcher

# Voir les listeners d'un événement
php bin/console debug:event-dispatcher kernel.request
```

---

## 🧪 Commandes de Test

### PHPUnit

```bash
# Exécuter tous les tests
php bin/phpunit

# Exécuter un test spécifique
php bin/phpunit tests/Unit/UserTest.php

# Avec coverage HTML
php bin/phpunit --coverage-html var/coverage

# Avec coverage text
php bin/phpunit --coverage-text
```

### Validation

```bash
# Valider les entités
php bin/console doctrine:schema:validate

# Valider le YAML
php bin/console lint:yaml config
php bin/console lint:yaml translations

# Valider le Twig
php bin/console lint:twig templates

# Valider les containers
php bin/console lint:container
```

---

## 📦 Commandes Assets

### Installation

```bash
# Installer les assets dans public/
php bin/console assets:install

# Avec liens symboliques (Linux/Mac)
php bin/console assets:install --symlink

# ImportMap
php bin/console importmap:install
php bin/console importmap:update

# CKEditor
php bin/console ckeditor:install

# elFinder
php bin/console elfinder:install
```

---

## 🌐 Commandes Serveur

### Symfony CLI

```bash
# Démarrer le serveur
symfony server:start

# Démarrer en arrière-plan
symfony server:start -d

# Arrêter le serveur
symfony server:stop

# Voir les logs
symfony server:log

# Voir le statut
symfony server:status
```

### PHP Built-in Server

```bash
# Démarrer sur port 8000
php -S localhost:8000 -t public/

# Sur un port personnalisé
php -S localhost:8080 -t public/

# Accessible depuis le réseau
php -S 0.0.0.0:8000 -t public/
```

---

## 📊 Commandes de Statistiques

### Compter

```bash
# Compter les utilisateurs
php bin/console doctrine:query:sql "SELECT COUNT(*) as total FROM user"

# Compter par type
php bin/console doctrine:query:sql "SELECT user_type, COUNT(*) as count FROM user GROUP BY user_type"

# Compter les établissements
php bin/console doctrine:query:sql "SELECT COUNT(*) as total FROM school"

# Voir les stats complètes
php bin/console doctrine:query:sql "
SELECT 
    (SELECT COUNT(*) FROM school) as schools,
    (SELECT COUNT(*) FROM school_year) as years,
    (SELECT COUNT(*) FROM level) as levels,
    (SELECT COUNT(*) FROM user) as users
"
```

---

## 🔄 Commandes Utilitaires

### Informations Système

```bash
# Version PHP
php -v

# Extensions PHP chargées
php -m

# Configuration PHP
php --ini

# Version Symfony
php bin/console --version

# Version Composer
composer -V
```

### Nettoyage

```bash
# Nettoyer le cache
php bin/console cache:clear

# Supprimer les logs
rm -rf var/log/*  # Linux/Mac
Remove-Item var/log/* -Recurse  # Windows PowerShell

# Nettoyer composer
composer clear-cache

# Nettoyer les sessions
rm -rf var/sessions/*
```

---

## 📝 Commandes Personnalisées

### Notre Command CLI

```bash
# Créer un administrateur
php bin/console app:create-admin

# Sortie:
# 🎓 Création d'un Administrateur EDU-SCHOOL
# Nom d'utilisateur: _
# Email: _
# ...
```

### À Créer (Future)

```bash
# Suggestions de commands à créer
php bin/console app:import-students file.xlsx
php bin/console app:generate-reports
php bin/console app:send-notifications
php bin/console app:backup-database
```

---

## 🚀 Workflow de Développement

### Développement d'une Nouvelle Fonctionnalité

```bash
# 1. Créer une entité
php bin/console make:entity MyEntity

# 2. Créer la migration
php bin/console make:migration

# 3. Exécuter la migration
php bin/console doctrine:migrations:migrate

# 4. Créer le contrôleur
php bin/console make:controller MyEntityController

# 5. Créer le formulaire
php bin/console make:form MyEntityType MyEntity

# 6. Vider le cache
php bin/console cache:clear

# 7. Tester
symfony server:start
```

---

## 🔄 Commandes de Réinitialisation

### Reset Complet de la Base de Données

```bash
# ⚠️ ATTENTION: Supprime toutes les données !

# 1. Supprimer la base
php bin/console doctrine:database:drop --force

# 2. Recréer la base
php bin/console doctrine:database:create

# 3. Exécuter les migrations
php bin/console doctrine:migrations:migrate -n

# 4. Recharger les fixtures
php bin/console doctrine:fixtures:load -n

# 5. Vider le cache
php bin/console cache:clear
```

### Une seule commande (Windows PowerShell)

```powershell
php bin/console doctrine:database:drop --force; php bin/console doctrine:database:create; php bin/console doctrine:migrations:migrate -n; php bin/console doctrine:fixtures:load -n; php bin/console cache:clear
```

### Une seule commande (Linux/Mac)

```bash
php bin/console doctrine:database:drop --force && \
php bin/console doctrine:database:create && \
php bin/console doctrine:migrations:migrate -n && \
php bin/console doctrine:fixtures:load -n && \
php bin/console cache:clear
```

---

## 📊 Commandes de Monitoring

### Performances

```bash
# Profiler une page
# Accéder à la page puis consulter /_profiler

# Voir les requêtes Doctrine lentes
# Dans le profiler web

# Temps d'exécution
time php bin/console cache:clear
```

### Mémoire

```bash
# Voir l'utilisation mémoire
php -i | findstr memory_limit

# Augmenter temporairement
php -d memory_limit=512M bin/console make:migration
```

---

## 🎯 Commandes par Module

### Module 1 - Établissements

```bash
# Voir les établissements
php bin/console doctrine:query:sql "SELECT * FROM school"

# Compter par type
php bin/console doctrine:query:sql "
SELECT type, COUNT(*) as count 
FROM school 
GROUP BY type
"

# Voir les années scolaires
php bin/console doctrine:query:sql "
SELECT sy.name, s.name as school 
FROM school_year sy 
JOIN school s ON sy.school_id = s.id
"
```

### Module 2 - Utilisateurs

```bash
# Créer un admin
php bin/console app:create-admin

# Lister les utilisateurs
php bin/console doctrine:query:sql "SELECT username, email, user_type FROM user"

# Compter par type
php bin/console doctrine:query:sql "
SELECT user_type, COUNT(*) as count 
FROM user 
GROUP BY user_type
"

# Hasher un mot de passe
php bin/console security:hash-password MyPassword123
```

---

## 🔧 Commandes Composer

### Dépendances

```bash
# Installer les dépendances
composer install

# Mettre à jour les dépendances
composer update

# Installer une nouvelle dépendance
composer require vendor/package

# Installer une dépendance de dev
composer require --dev vendor/package

# Supprimer une dépendance
composer remove vendor/package
```

### Informations

```bash
# Voir les dépendances installées
composer show

# Voir les dépendances obsolètes
composer outdated

# Vérifier la sécurité
composer audit

# Valider composer.json
composer validate
```

---

## 📝 Aide et Documentation

### Aide des Commandes

```bash
# Lister toutes les commandes
php bin/console list

# Aide sur une commande
php bin/console help doctrine:migrations:migrate

# Version de chaque command
php bin/console --version
```

---

## 🎯 Commandes Rapides Fréquentes

### Top 10 Commandes les Plus Utilisées

```bash
# 1. Vider le cache
php bin/console cache:clear

# 2. Exécuter les migrations
php bin/console doctrine:migrations:migrate

# 3. Charger les fixtures
php bin/console doctrine:fixtures:load --append

# 4. Lister les routes
php bin/console debug:router

# 5. Créer une entité
php bin/console make:entity

# 6. Créer un contrôleur
php bin/console make:controller

# 7. Créer une migration
php bin/console make:migration

# 8. Créer un admin
php bin/console app:create-admin

# 9. Démarrer le serveur
symfony server:start

# 10. Voir les logs
tail -f var/log/dev.log
```

---

## 💡 Astuces

### Alias Utiles

Ajoutez à votre `.bashrc` ou `.zshrc` (Linux/Mac) :

```bash
alias sf='php bin/console'
alias sfc='php bin/console cache:clear'
alias sfm='php bin/console make:migration'
alias sfmm='php bin/console doctrine:migrations:migrate'
alias sff='php bin/console doctrine:fixtures:load --append'
alias sfr='php bin/console debug:router'
```

Utilisation :
```bash
sf cache:clear
sfc
sfm
sfmm
```

### PowerShell (Windows)

Créer `profile.ps1` :

```powershell
function sf { php bin/console $args }
function sfc { php bin/console cache:clear }
function sfm { php bin/console make:migration }
function sfmm { php bin/console doctrine:migrations:migrate -n }
```

---

## 🎯 Commandes par Tâche

### Je veux créer...

#### Un établissement
```bash
# Via interface: http://localhost:8000/admin/schools/new
# Via SQL:
php bin/console doctrine:query:sql "
INSERT INTO school (name, code, type, created_at, updated_at, is_active) 
VALUES ('Mon École', 'ECO001', 'primaire', NOW(), NOW(), 1)
"
```

#### Un utilisateur
```bash
# Via command:
php bin/console app:create-admin

# Via interface: http://localhost:8000/admin/users/new
```

#### Une migration
```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

---

## 📊 Commandes de Statistiques

```bash
# Compter tout
php bin/console doctrine:query:sql "
SELECT 
    'Établissements' as Type, COUNT(*) as Total FROM school
UNION ALL
SELECT 'Années Scolaires', COUNT(*) FROM school_year
UNION ALL
SELECT 'Niveaux', COUNT(*) FROM level
UNION ALL
SELECT 'Utilisateurs', COUNT(*) FROM user
"

# Voir les derniers utilisateurs créés
php bin/console doctrine:query:sql "
SELECT username, email, created_at 
FROM user 
ORDER BY created_at DESC 
LIMIT 10
"

# Voir les utilisateurs actifs
php bin/console doctrine:query:sql "
SELECT COUNT(*) as actifs 
FROM user 
WHERE is_active = 1
"
```

---

## 🆘 Commandes de Dépannage

### Problèmes Fréquents

```bash
# Cache corrompu
php bin/console cache:clear --no-warmup
rm -rf var/cache/*

# Schéma désynchronisé
php bin/console doctrine:schema:update --dump-sql
php bin/console doctrine:schema:update --force

# Migrations bloquées
php bin/console doctrine:migrations:status
php bin/console doctrine:migrations:migrate --allow-no-migration

# Permissions (Linux/Mac)
chmod -R 777 var/
chown -R www-data:www-data var/
```

---

## 📦 Commandes Complètes

### Installation Complète en Une Commande

**Windows PowerShell** :
```powershell
composer install; php bin/console doctrine:database:create; php bin/console doctrine:migrations:migrate -n; php bin/console doctrine:fixtures:load -n --append; php bin/console cache:clear; php -S localhost:8000 -t public/
```

**Linux/Mac Bash** :
```bash
composer install && \
php bin/console doctrine:database:create && \
php bin/console doctrine:migrations:migrate -n && \
php bin/console doctrine:fixtures:load -n --append && \
php bin/console cache:clear && \
php -S localhost:8000 -t public/
```

### Réinitialisation Complète

**Windows PowerShell** :
```powershell
php bin/console doctrine:database:drop --force; php bin/console doctrine:database:create; php bin/console doctrine:migrations:migrate -n; php bin/console doctrine:fixtures:load -n; php bin/console cache:clear
```

**Linux/Mac Bash** :
```bash
php bin/console doctrine:database:drop --force && \
php bin/console doctrine:database:create && \
php bin/console doctrine:migrations:migrate -n && \
php bin/console doctrine:fixtures:load -n && \
php bin/console cache:clear
```

---

## 📚 Ressources

### Documentation Symfony

```bash
# Ouvrir la doc Symfony
https://symfony.com/doc/current/index.html

# Doctrine ORM
https://www.doctrine-project.org/projects/doctrine-orm/en/latest/
```

### Notre Documentation

- `README.md` - Vue d'ensemble
- `QUICK_START.md` - Démarrage rapide
- `docs/INSTALLATION.md` - Installation détaillée
- `COMMANDS.md` - Ce fichier

---

## 🎉 Commandes de Célébration

```bash
# Après avoir terminé un module
echo "✅ Module terminé avec succès !"

# Vérifier que tout fonctionne
php bin/console doctrine:query:sql "SELECT 'EDU-SCHOOL' as Project, 'Ready' as Status"

# Afficher les stats
php bin/console doctrine:query:sql "
SELECT 
    (SELECT COUNT(*) FROM user) as users,
    (SELECT COUNT(*) FROM school) as schools
"
```

---

## 🎯 Commande du Jour

```bash
# La commande la plus utile:
php bin/console
# Affiche toutes les commandes disponibles avec description
```

---

**Nombre total de commandes disponibles** : 100+  
**Commandes personnalisées** : 1 (app:create-admin)  
**Commandes Symfony** : 90+  
**Commandes Doctrine** : 20+  

---

**Dernière mise à jour** : 09 Octobre 2025  
**Version** : 1.0.0

