# ⚡ Démarrage Rapide - EDU-SCHOOL

## 🚀 Installation en 10 minutes

### Prérequis

- ✅ PHP 8.1+ installé
- ✅ Composer installé
- ✅ MySQL/MariaDB en cours d'exécution
- ✅ Extensions PHP : `gd`, `zip`, `pdo_mysql`

### Installation rapide

```bash
# 1. Cloner le projet (ou se placer dans le dossier)
cd C:\xampp\htdocs\edu-school

# 2. Installer les dépendances
composer install

# 3. Copier et configurer l'environnement
cp .env .env.local

# 4. Éditer .env.local
# DATABASE_URL="mysql://root:password@127.0.0.1:3306/edu_school"

# 5. Créer la base de données
php bin/console doctrine:database:create

# 6. Exécuter les migrations
php bin/console doctrine:migrations:migrate

# 7. Charger les données de démonstration
php bin/console doctrine:fixtures:load --append

# 8. Installer les assets
php bin/console assets:install
php bin/console importmap:install

# 9. Démarrer le serveur
php -S localhost:8000 -t public/
```

## 🔑 Connexion

### Accéder à l'application

```
URL: http://localhost:8000
```

### Comptes de test

| Rôle | Login | Mot de passe |
|------|-------|--------------|
| Super Admin | `superadmin` | `Admin@123` |
| Admin | `admin` | `Admin@123` |
| Enseignant | `jmartin` | `Teacher@123` |
| Élève | `lucas.dubois` | `Student@123` |
| Parent | `parent1` | `Parent@123` |

## 🎯 Modules disponibles

### ✅ Module 1 - Établissements

**Accès** : `/admin/schools`

**Fonctionnalités** :
- Créer des établissements
- Gérer les années scolaires
- Configurer les niveaux

**Données de test** :
- 5 établissements (1 par type)
- 10 années scolaires
- 20 niveaux scolaires

### ✅ Module 2 - Utilisateurs

**Accès** : `/admin/users`

**Fonctionnalités** :
- Créer des utilisateurs
- Gérer les rôles
- Rechercher et filtrer

**Données de test** :
- 24 utilisateurs
- 6 types différents
- 7 niveaux de rôles

## 📊 Tableau de bord

**Accès** : `/` (page d'accueil)

**Affichage** :
- 📈 Statistiques en temps réel
- 📅 Activités récentes
- 🎯 Actions rapides
- 📊 Graphiques interactifs

## 🔧 Commandes utiles

### Créer un administrateur

```bash
php bin/console app:create-admin
```

Suivez les instructions interactives.

### Vider le cache

```bash
php bin/console cache:clear
```

### Voir les routes

```bash
php bin/console debug:router
```

### Voir les routes d'un module

```bash
php bin/console debug:router | findstr "admin_school"
php bin/console debug:router | findstr "admin_user"
```

### Créer une nouvelle entité

```bash
php bin/console make:entity
```

### Créer une migration

```bash
php bin/console make:migration
```

### Exécuter les migrations

```bash
php bin/console doctrine:migrations:migrate
```

## 🎨 Structure des URLs

### Pages publiques
```
/login                    → Connexion
/                         → Dashboard (redirige vers login si non connecté)
```

### Administration (ROLE_ADMIN requis)
```
/admin/schools            → Gestion des établissements
/admin/school-years       → Gestion des années scolaires
/admin/levels             → Gestion des niveaux
/admin/users              → Gestion des utilisateurs
```

## 📚 Documentation

### Guides disponibles

1. **README.md** - Vue d'ensemble générale
2. **docs/INDEX.md** - Index de toute la documentation
3. **docs/INSTALLATION.md** - Installation détaillée
4. **docs/USER_GUIDE.md** - Guide utilisateur complet
5. **docs/MODULE_1_ETABLISSEMENTS.md** - Module 1
6. **docs/MODULE_2_UTILISATEURS.md** - Module 2
7. **docs/TEMPLATE_GUIDE.md** - Guide du template
8. **MODULES_SUMMARY.md** - Récapitulatif des modules
9. **CHANGELOG.md** - Historique des modifications

### Démarrage rapide par module

- **Module 1** : Voir `docs/MODULE_1_ETABLISSEMENTS.md`
- **Module 2** : Voir `docs/QUICK_START_MODULE2.md`

## 🐛 Résolution de problèmes

### Erreur de connexion à la base de données

```bash
# Vérifier MySQL
# Dans XAMPP, démarrer MySQL

# Vérifier les credentials dans .env.local
DATABASE_URL="mysql://root:@127.0.0.1:3306/edu_school"
```

### Extensions PHP manquantes

```
Problème 1: ext-zip manquant
Problème 2: ext-gd manquant
```

**Solution** :
1. Ouvrir `C:\xampp\php\php.ini`
2. Décommenter les lignes :
   ```ini
   extension=gd
   extension=zip
   ```
3. Redémarrer Apache

### Page blanche après installation

```bash
# Vérifier les logs
tail -f var/log/dev.log

# Vider le cache
php bin/console cache:clear

# Vérifier les permissions
chmod -R 777 var/  # (Linux/Mac)
```

### Erreur "MainAuthenticator not found"

Le fichier existe déjà : `src/Security/MainAuthenticator.php`

Si l'erreur persiste :
```bash
php bin/console cache:clear
```

## ✅ Checklist de vérification

Après l'installation, vérifiez que :

- [ ] La base de données `edu_school` existe
- [ ] Les 5 tables sont créées (school, school_year, period, level, user)
- [ ] Les fixtures sont chargées (24 utilisateurs)
- [ ] Vous pouvez vous connecter avec `admin` / `Admin@123`
- [ ] La page d'accueil affiche le dashboard
- [ ] `/admin/schools` affiche 5 établissements
- [ ] `/admin/users` affiche 24 utilisateurs
- [ ] La navigation fonctionne
- [ ] Les graphiques s'affichent sur le dashboard

## 🎯 Premiers pas recommandés

### 1. Explorer l'interface (5 min)

- Se connecter avec `admin` / `Admin@123`
- Visiter le tableau de bord
- Explorer les menus de navigation
- Voir les statistiques

### 2. Créer votre premier établissement (5 min)

- Aller sur `/admin/schools`
- Cliquer "Nouvel Établissement"
- Remplir le formulaire
- Enregistrer

### 3. Créer une année scolaire (3 min)

- Aller sur `/admin/school-years`
- Cliquer "Nouvelle Année Scolaire"
- Sélectionner votre établissement
- Définir les dates
- Cocher "Année en cours"
- Enregistrer

### 4. Créer vos premiers utilisateurs (5 min)

- Aller sur `/admin/users`
- Créer un enseignant
- Créer un élève
- Tester la recherche et les filtres

### 5. Personnaliser (10 min)

- Modifier le logo dans `templates/base.html.twig`
- Personnaliser les couleurs (variables CSS)
- Ajouter vos informations d'établissement

## 📈 Prochaines étapes

Après avoir exploré les modules existants :

1. **Module 3** - Créer des classes et affecter des élèves
2. **Module 4** - Mettre en place le système de notation
3. **Module 5** - Configurer la gestion des absences
4. **Module 6** - Paramétrer la gestion financière

## 💡 Astuces

### Travailler en mode développement

```bash
# Toujours en mode dev pour voir les erreurs
APP_ENV=dev
APP_DEBUG=true
```

### Profiler Symfony

Le profiler est accessible en bas de chaque page (barre d'outils de debug).

### Consulter les logs

```bash
# Logs Symfony
tail -f var/log/dev.log

# Logs PHP
# Windows XAMPP
tail -f C:\xampp\apache\logs\error.log
```

### Nettoyer et recommencer

```bash
# Supprimer la base
php bin/console doctrine:database:drop --force

# Recréer tout
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load -n
```

## 🆘 Besoin d'aide ?

### Documentation
- 📖 Lire `docs/INDEX.md` pour l'index complet
- 📖 Consulter `docs/USER_GUIDE.md` pour l'utilisation
- 📖 Voir `docs/INSTALLATION.md` pour les problèmes d'installation

### Support
- 📧 Email : support@edu-school.com
- 📚 Documentation complète dans `/docs`
- 🐛 Issues : GitHub Issues

---

## ⏱️ Temps estimé

- **Installation** : 10 minutes
- **Configuration** : 5 minutes
- **Exploration** : 15 minutes
- **Premier établissement** : 5 minutes
- **Premiers utilisateurs** : 10 minutes

**Total** : ~45 minutes pour être opérationnel

---

**🎉 Félicitations ! Vous êtes prêt à utiliser EDU-SCHOOL !**

---

**Version** : 1.0.0  
**Date** : Octobre 2025

