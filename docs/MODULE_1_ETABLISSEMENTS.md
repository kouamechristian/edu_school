# 🎯 Module 1 - Gestion des Établissements

## 📋 Vue d'ensemble

Ce module permet la gestion complète des établissements scolaires, des années scolaires, des périodes d'évaluation et des niveaux scolaires.

## ✨ Fonctionnalités

### 1. Gestion des Établissements

- ✅ Création et gestion de multiples établissements
- ✅ Configuration par niveau scolaire (Maternelle, Primaire, Collège, Lycée, Université)
- ✅ Informations complètes (nom, code, directeur, adresse, contacts)
- ✅ Activation/désactivation des établissements
- ✅ Statistiques par type d'établissement

### 2. Gestion des Années Scolaires

- ✅ Création d'années scolaires par établissement
- ✅ Définition des dates de début et de fin
- ✅ Marquage de l'année en cours
- ✅ Historique des années passées

### 3. Gestion des Périodes

- ✅ Création de périodes d'évaluation (trimestres, semestres, annuel)
- ✅ Configuration des coefficients
- ✅ Dates de début et de fin pour chaque période

### 4. Gestion des Niveaux

- ✅ Définition des niveaux scolaires par catégorie
- ✅ Ordre d'affichage personnalisé
- ✅ Description et activation/désactivation

## 🗄️ Structure de la Base de Données

### Tables créées

```
school              → Établissements
school_year         → Années scolaires
period              → Périodes d'évaluation
level               → Niveaux scolaires
```

### Relations

```
School (1) ──< (N) SchoolYear
SchoolYear (1) ──< (N) Period
```

## 📁 Fichiers du Module

### Entités
- `src/Entity/School.php`
- `src/Entity/SchoolYear.php`
- `src/Entity/Period.php`
- `src/Entity/Level.php`

### Repositories
- `src/Repository/SchoolRepository.php`
- `src/Repository/SchoolYearRepository.php`
- `src/Repository/PeriodRepository.php`
- `src/Repository/LevelRepository.php`

### Contrôleurs
- `src/Controller/SchoolController.php`
- `src/Controller/SchoolYearController.php`
- `src/Controller/LevelController.php`

### Formulaires
- `src/Form/SchoolType.php`
- `src/Form/SchoolYearType.php`
- `src/Form/PeriodType.php`
- `src/Form/LevelType.php`

### Templates
- `templates/school/` (index, new, edit, show)
- `templates/school_year/` (index, new, edit, show)
- `templates/level/` (index, new, edit, show)

### Migration
- `migrations/Version20251009200013.php`

### Fixtures
- `src/DataFixtures/Module1Fixtures.php`

## 🚀 Installation

### 1. Exécuter la migration

```bash
php bin/console doctrine:migrations:migrate
```

### 2. Charger les données de test (optionnel)

```bash
php bin/console doctrine:fixtures:load --append
```

Cela créera :
- 5 établissements (1 de chaque type)
- 2 années scolaires par établissement (2023-2024 et 2024-2025)
- 3 périodes (trimestres) pour l'année en cours
- 20 niveaux scolaires

## 📖 Guide d'utilisation

### Accès au module

**Route** : `/admin/schools`  
**Rôle requis** : `ROLE_ADMIN`

### Créer un établissement

1. Accéder à `/admin/schools`
2. Cliquer sur "Nouvel Établissement"
3. Remplir le formulaire :
   - Nom de l'établissement
   - Code unique
   - Type (maternelle, primaire, collège, lycée, université)
   - Directeur
   - Adresse, téléphone, email
4. Enregistrer

### Créer une année scolaire

1. Accéder à `/admin/school-years`
2. Cliquer sur "Nouvelle Année Scolaire"
3. Remplir le formulaire :
   - Sélectionner l'établissement
   - Nom (ex: "2024-2025")
   - Date de début et de fin
   - Cocher "Année en cours" si applicable
4. Enregistrer

### Créer des périodes

Les périodes sont créées automatiquement avec les fixtures, mais peuvent être ajoutées manuellement via l'entité Period.

### Créer un niveau scolaire

1. Accéder à `/admin/levels`
2. Cliquer sur "Nouveau Niveau"
3. Remplir le formulaire :
   - Nom (ex: "CP", "6ème", "Terminale S")
   - Catégorie
   - Ordre d'affichage (nombre)
   - Description
4. Enregistrer

## 🔗 Routes disponibles

### Établissements
```
GET  /admin/schools           → Liste des établissements
GET  /admin/schools/new       → Formulaire de création
GET  /admin/schools/{id}      → Détails d'un établissement
GET  /admin/schools/{id}/edit → Formulaire de modification
POST /admin/schools/{id}      → Suppression
POST /admin/schools/{id}/toggle → Activer/Désactiver
```

### Années scolaires
```
GET  /admin/school-years           → Liste des années
GET  /admin/school-years/new       → Formulaire de création
GET  /admin/school-years/{id}      → Détails
GET  /admin/school-years/{id}/edit → Modification
POST /admin/school-years/{id}      → Suppression
POST /admin/school-years/{id}/set-current → Définir comme année courante
```

### Niveaux
```
GET  /admin/levels           → Liste des niveaux
GET  /admin/levels/new       → Formulaire de création
GET  /admin/levels/{id}      → Détails
GET  /admin/levels/{id}/edit → Modification
POST /admin/levels/{id}      → Suppression
POST /admin/levels/{id}/toggle → Activer/Désactiver
```

## 🧪 Tests

### Tester manuellement

1. Créer un établissement
2. Créer une année scolaire pour cet établissement
3. Définir l'année comme courante
4. Créer des niveaux scolaires

### Vérifier en base de données

```bash
php bin/console doctrine:query:sql "SELECT * FROM school"
php bin/console doctrine:query:sql "SELECT * FROM school_year"
php bin/console doctrine:query:sql "SELECT * FROM level"
```

## 📊 Statistiques disponibles

Le tableau de bord affiche :
- Nombre total d'établissements
- Répartition par type (maternelle, primaire, collège, lycée, université)
- Années scolaires actives
- Niveaux scolaires par catégorie

## 🔧 Personnalisation

### Ajouter un nouveau type d'établissement

1. Modifier l'entité `School.php` : ajouter le type dans le `Choice`
2. Mettre à jour le formulaire `SchoolType.php`
3. Créer une nouvelle migration

### Modifier les périodes par défaut

Éditer le fichier `Module1Fixtures.php` pour changer les périodes (trimestres → semestres par exemple).

## 🐛 Dépannage

### Erreur de clé unique sur le code

Le code d'établissement doit être unique. Vérifiez qu'aucun établissement n'utilise déjà ce code.

### L'année ne se définit pas comme courante

Seule une année peut être courante par établissement. La méthode `setAsCurrent()` désactive automatiquement les autres.

### Les fixtures ne se chargent pas

```bash
# Vider la base et recharger
php bin/console doctrine:schema:drop --force --full-database
php bin/console doctrine:migrations:migrate -n
php bin/console doctrine:fixtures:load -n
```

## 📈 Évolutions futures

- [ ] Import Excel des établissements
- [ ] Export PDF de la liste
- [ ] Duplication d'année scolaire
- [ ] Calendrier visuel des périodes
- [ ] Gestion des cycles (cycle 1, 2, 3, 4)
- [ ] Sections et filières (S, ES, L, etc.)

## 💡 Bonnes pratiques

1. **Codes établissements** : Utiliser un format cohérent (ex: MAT001, PRI001, COL001)
2. **Années scolaires** : Toujours au format "YYYY-YYYY" (ex: "2024-2025")
3. **Périodes** : Vérifier que les dates ne se chevauchent pas
4. **Niveaux** : Maintenir l'ordre cohérent pour l'affichage

## 🔗 Liens avec d'autres modules

Ce module est la base pour :
- **Module 2** - Gestion des utilisateurs (rattachement à un établissement)
- **Module 3** - Gestion académique (classes liées aux niveaux)
- **Module 4** - Notes et évaluations (périodes d'évaluation)

---

**Version** : 1.0  
**Date de création** : Octobre 2025  
**Auteur** : Équipe EDU-SCHOOL

