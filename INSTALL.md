# 🚀 Installation Automatique - EDU-SCHOOL

## ⚡ Installation Complète en Une Commande

### Windows (PowerShell)

```powershell
# Copier-coller cette commande complète
php bin/console doctrine:database:create; php bin/console doctrine:migrations:migrate -n; php bin/console doctrine:fixtures:load -n --append; php bin/console cache:clear; echo "✅ Installation terminée ! Utilisez: admin / Admin@123"
```

### Linux / Mac (Bash)

```bash
# Copier-coller cette commande complète
php bin/console doctrine:database:create && \
php bin/console doctrine:migrations:migrate -n && \
php bin/console doctrine:fixtures:load -n --append && \
php bin/console cache:clear && \
echo "✅ Installation terminée ! Utilisez: admin / Admin@123"
```

---

## 📋 Installation Étape par Étape

### Étape 1 : Vérifier les prérequis

```bash
# Vérifier PHP
php -v
# Doit afficher: PHP 8.1 ou supérieur

# Vérifier les extensions
php -m | findstr "pdo_mysql gd zip"
# Doit afficher: pdo_mysql, gd, zip

# Vérifier Composer
composer -V
# Doit afficher: Composer version 2.x
```

### Étape 2 : Installer les dépendances

```bash
composer install
```

### Étape 3 : Configurer la base de données

Éditer `.env.local` :

```env
DATABASE_URL="mysql://root:@127.0.0.1:3306/edu_school?serverVersion=8.0&charset=utf8mb4"
```

**Remplacer** :
- `root` par votre utilisateur MySQL
- `` (vide) par votre mot de passe MySQL si nécessaire
- `127.0.0.1` par votre host si différent
- `edu_school` par le nom de votre base de données

### Étape 4 : Créer la base de données

```bash
php bin/console doctrine:database:create
```

**Résultat attendu** :
```
Created database `edu_school` for connection named default
```

### Étape 5 : Exécuter les migrations

```bash
php bin/console doctrine:migrations:migrate
```

**Répondre** : `yes`

**Résultat attendu** :
```
++ migrating Version20251009200013
++ migrated (took 45ms, used 20M memory)

++ migrating Version20251009201500
++ migrated (took 28ms, used 20M memory)

[OK] Successfully migrated to version: Version20251009201500
```

### Étape 6 : Charger les données de démonstration

```bash
php bin/console doctrine:fixtures:load --append
```

**Résultat attendu** :
```
> loading App\DataFixtures\Module1Fixtures
> loading App\DataFixtures\Module2Fixtures
```

**Données créées** :
- 5 établissements
- 10 années scolaires
- 30 périodes
- 20 niveaux
- 24 utilisateurs

### Étape 7 : Vider le cache

```bash
php bin/console cache:clear
```

### Étape 8 : Installer les assets (optionnel)

```bash
php bin/console assets:install
php bin/console importmap:install
```

### Étape 9 : Démarrer le serveur

```bash
# Avec Symfony CLI (recommandé)
symfony server:start

# OU avec PHP built-in
php -S localhost:8000 -t public/
```

### Étape 10 : Se connecter

**Ouvrir** : `http://localhost:8000/login`

**Identifiants** :
```
Username: admin
Password: Admin@123
```

---

## ✅ Vérification de l'Installation

### Checklist

Après l'installation, vérifiez :

```bash
# 1. Base de données existe
php bin/console doctrine:database:create
# Doit dire: "database already exists"

# 2. Tables créées (5 tables)
php bin/console doctrine:schema:validate
# Doit dire: "[OK] The database schema is in sync with the mapping files."

# 3. Compter les données
php bin/console doctrine:query:sql "SELECT 
    (SELECT COUNT(*) FROM school) as schools,
    (SELECT COUNT(*) FROM school_year) as years,
    (SELECT COUNT(*) FROM level) as levels,
    (SELECT COUNT(*) FROM user) as users"
# Doit afficher: schools=5, years=10, levels=20, users=24

# 4. Vérifier qu'un admin existe
php bin/console doctrine:query:sql "SELECT username, email FROM user WHERE username='admin'"
# Doit afficher: admin | admin@edu-school.com
```

### Test de connexion

1. **Ouvrir** : `http://localhost:8000`
2. **Redirection automatique** vers `/login`
3. **Se connecter** avec `admin` / `Admin@123`
4. **Doit afficher** : Dashboard avec statistiques
5. **Tester la navigation** :
   - `/admin/schools` → 5 établissements
   - `/admin/users` → 24 utilisateurs
   - `/admin/school-years` → 10 années
   - `/admin/levels` → 20 niveaux

---

## 🔧 En cas de Problème

### Erreur : "Access denied for user"

**Solution** :
```bash
# Vérifier la connexion MySQL
mysql -u root -p

# Si ça fonctionne, mettre à jour .env.local avec le bon password
```

### Erreur : "Database does not exist"

**Solution** :
```bash
php bin/console doctrine:database:create
```

### Erreur : "Table doesn't exist"

**Solution** :
```bash
php bin/console doctrine:migrations:migrate -n
```

### Erreur : "No data found"

**Solution** :
```bash
php bin/console doctrine:fixtures:load -n --append
```

### Page blanche

**Solution** :
```bash
# Vider le cache
php bin/console cache:clear

# Vérifier les logs
cat var/log/dev.log
```

---

## 🎯 Post-Installation

### Créer votre propre administrateur

```bash
php bin/console app:create-admin
```

**Interface interactive** :
```
Nom d'utilisateur: votre_login
Email: votre@email.com
Mot de passe: ******
Confirmer: ******
Rôle: [1] ROLE_SUPER_ADMIN
Prénom: Votre prénom
Nom: Votre nom

✅ Administrateur créé avec succès !
```

### Personnaliser l'application

1. **Modifier le nom** dans `templates/base.html.twig`
2. **Ajouter un logo** dans `public/images/`
3. **Personnaliser les couleurs** (variables CSS dans base.html.twig)
4. **Configurer l'email** dans `.env.local`

---

## 📊 Vérification Finale

### Interface

- [ ] Dashboard s'affiche correctement
- [ ] Navigation fonctionne
- [ ] Liste des établissements affiche 5 items
- [ ] Liste des utilisateurs affiche 24 items
- [ ] Recherche fonctionne
- [ ] Filtres fonctionnent
- [ ] Formulaires de création fonctionnent
- [ ] Modification fonctionne
- [ ] Suppression fonctionne (avec confirmation)

### Sécurité

- [ ] Login fonctionne
- [ ] Logout fonctionne
- [ ] Remember Me fonctionne
- [ ] Login throttling fonctionne (3 tentatives)
- [ ] Accès admin bloqué pour non-admin
- [ ] Protection auto-suppression fonctionne

### Base de données

- [ ] 5 tables créées
- [ ] Données de test chargées
- [ ] Relations fonctionnent
- [ ] Index créés

---

## ✅ Installation Réussie !

Si tous les tests passent, vous avez maintenant :

```
    ✨ Une application EDU-SCHOOL fonctionnelle
    🏫 5 établissements de démonstration
    👥 24 utilisateurs de test
    📚 20 niveaux scolaires prédéfinis
    📅 10 années scolaires
    🎨 Interface moderne et responsive
    📖 Documentation complète
```

---

## 🚀 Commencer à Utiliser

### Premiers pas recommandés

1. **Explorer le dashboard** (`/`)
2. **Voir les établissements** (`/admin/schools`)
3. **Voir les utilisateurs** (`/admin/users`)
4. **Créer votre premier établissement** personnalisé
5. **Créer vos premiers utilisateurs** réels
6. **Personnaliser l'interface** selon vos besoins

---

## 📚 Aide et Support

### Documentation

- 📖 `README.md` - Vue d'ensemble
- 📖 `QUICK_START.md` - Démarrage rapide
- 📖 `docs/INSTALLATION.md` - Installation détaillée
- 📖 `docs/USER_GUIDE.md` - Guide utilisateur
- 📖 `docs/MODULE_2_UTILISATEURS.md` - Module 2

### Commandes utiles

```bash
# Voir les routes
php bin/console debug:router

# Voir la configuration
php bin/console debug:config

# Lister les utilisateurs
php bin/console doctrine:query:sql "SELECT username, email FROM user"
```

---

**🎉 Bienvenue dans EDU-SCHOOL !**

---

**Version** : 1.0.0  
**Date** : Octobre 2025  
**Support** : support@edu-school.com

