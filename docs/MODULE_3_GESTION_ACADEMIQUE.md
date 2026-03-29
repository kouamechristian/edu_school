# 📚 MODULE 3 - Gestion Académique - EDU-SCHOOL

## ✅ Module Complet et Fonctionnel !

```
╔══════════════════════════════════════════════════════════╗
║                                                          ║
║    ✅  MODULE 3 - GESTION ACADÉMIQUE 100%                ║
║                                                          ║
║    • Classes et groupes                                  ║
║    • Matières et programmes                              ║
║    • Emplois du temps                                    ║
║    • CRUD complets                                       ║
║    • Filtrage par établissement                          ║
║    • Interface moderne                                   ║
║                                                          ║
╚══════════════════════════════════════════════════════════╝
```

---

## 📋 Vue d'Ensemble

Le Module 3 gère toute la partie **académique** de l'école :
- 🏫 **Classes** : Création et gestion des classes par niveau
- 📚 **Matières** : Définition des matières avec coefficients
- 📅 **Emplois du Temps** : Planification des cours

---

## 🗂️ Structure du Module

### 1. Entités (3)

#### Classroom (Classe)
```php
src/Entity/Classroom.php
```

**Propriétés** :
- `id` : Identifiant unique
- `name` : Nom de la classe (ex: "6ème A")
- `code` : Code unique (ex: "6A-2024")
- `school` : Établissement (ManyToOne)
- `schoolYear` : Année scolaire (ManyToOne)
- `level` : Niveau (ManyToOne)
- `capacity` : Capacité maximale
- `mainTeacher` : Professeur principal (ManyToOne → User)
- `room` : Salle de classe
- `description` : Description
- `isActive` : Statut
- `createdAt`, `updatedAt` : Timestamps

**Méthodes utiles** :
- `getFullName()` : Retourne "6ème A (Collège)"

#### Subject (Matière)
```php
src/Entity/Subject.php
```

**Propriétés** :
- `id` : Identifiant unique
- `name` : Nom de la matière (ex: "Mathématiques")
- `code` : Code unique (ex: "MATH")
- `school` : Établissement (nullable - peut être globale)
- `level` : Niveau (nullable - peut être tous niveaux)
- `coefficient` : Coefficient pour la moyenne
- `description` : Programme, objectifs
- `type` : obligatoire | optionnelle | facultative
- `hoursPerWeek` : Heures par semaine
- `color` : Couleur pour l'emploi du temps (hex)
- `isActive` : Statut
- `createdAt`, `updatedAt` : Timestamps

**Méthodes utiles** :
- `getTypeLabel()` : Retourne le label du type

#### Course (Cours)
```php
src/Entity/Course.php
```

**Propriétés** :
- `id` : Identifiant unique
- `classroom` : Classe (ManyToOne)
- `subject` : Matière (ManyToOne)
- `teacher` : Enseignant (ManyToOne → User)
- `dayOfWeek` : Jour (lundi, mardi, ...)
- `startTime` : Heure de début
- `endTime` : Heure de fin
- `room` : Salle
- `notes` : Remarques
- `isActive` : Statut
- `createdAt`, `updatedAt` : Timestamps

**Méthodes utiles** :
- `getDayOfWeekLabel()` : Retourne "Lundi", "Mardi", etc.
- `getDuration()` : Calcule la durée en minutes

---

### 2. Repositories (3)

#### ClassroomRepository
```php
src/Repository/ClassroomRepository.php

Méthodes :
- findActive() : Toutes les classes actives
- findBySchool($schoolId) : Classes d'un établissement
- findBySchoolAndYear($schoolId, $yearId) : Classes filtrées
- findByLevel($levelId) : Classes d'un niveau
- countBySchool($schoolId) : Nombre de classes
```

#### SubjectRepository
```php
src/Repository/SubjectRepository.php

Méthodes :
- findActive() : Toutes les matières actives
- findBySchool($schoolId) : Matières d'un établissement
- findByLevel($levelId) : Matières d'un niveau
- findByType($type) : Matières par type
- findBySchoolAndLevel($schoolId, $levelId) : Matières filtrées
```

#### CourseRepository
```php
src/Repository/CourseRepository.php

Méthodes :
- findActive() : Tous les cours actifs
- findByClassroom($classroomId) : Cours d'une classe
- findByTeacher($teacherId) : Cours d'un enseignant
- findByDayOfWeek($day, $classroomId) : Cours d'un jour
- findScheduleByClassroom($classroomId) : Emploi du temps complet
```

---

### 3. Formulaires (3)

#### ClassroomType
```php
src/Form/ClassroomType.php

Champs :
- name : Nom de la classe
- code : Code unique
- school : Établissement (pré-rempli)
- schoolYear : Année scolaire (pré-remplie)
- level : Niveau
- capacity : Capacité maximale
- mainTeacher : Professeur principal
- room : Salle
- description : Description
- isActive : Statut
```

#### SubjectType
```php
src/Form/SubjectType.php

Champs :
- name : Nom de la matière
- code : Code unique
- school : Établissement (pré-rempli)
- level : Niveau (optionnel - tous niveaux)
- type : obligatoire/optionnelle/facultative
- coefficient : Coefficient
- hoursPerWeek : Heures par semaine
- color : Couleur (picker)
- description : Programme
- isActive : Statut
```

#### CourseType
```php
src/Form/CourseType.php

Champs :
- classroom : Classe
- subject : Matière
- teacher : Enseignant
- dayOfWeek : Jour de la semaine
- startTime : Heure de début
- endTime : Heure de fin
- room : Salle
- notes : Remarques
- isActive : Statut
```

---

### 4. Contrôleurs (3)

#### ClassroomController
```php
src/Controller/ClassroomController.php

Routes :
- /admin/classrooms              → Liste (filtrée par school + year)
- /admin/classrooms/new          → Création (pré-rempli)
- /admin/classrooms/{id}/show    → Détails
- /admin/classrooms/{id}/edit    → Modification
- /admin/classrooms/{id}/delete  → Suppression
- /admin/classrooms/{id}/toggle  → Activer/Désactiver
```

#### SubjectController
```php
src/Controller/SubjectController.php

Routes :
- /admin/subjects               → Liste (filtrée par school)
- /admin/subjects/new           → Création (pré-rempli)
- /admin/subjects/{id}/show     → Détails
- /admin/subjects/{id}/edit     → Modification
- /admin/subjects/{id}/delete   → Suppression
- /admin/subjects/{id}/toggle   → Activer/Désactiver
```

#### CourseController
```php
src/Controller/CourseController.php

Routes :
- /admin/courses                  → Liste (par classe)
- /admin/courses/new              → Création
- /admin/courses/{id}/show        → Détails
- /admin/courses/{id}/edit        → Modification
- /admin/courses/{id}/delete      → Suppression
- /admin/courses/schedule/{id}    → Emploi du temps
```

---

### 5. Templates (12 fichiers)

#### Classroom (4 templates)
```
templates/classroom/
├── index.html.twig  → Liste des classes
├── new.html.twig    → Formulaire de création
├── edit.html.twig   → Formulaire de modification
└── show.html.twig   → Détails d'une classe
```

#### Subject (4 templates)
```
templates/subject/
├── index.html.twig  → Liste des matières
├── new.html.twig    → Formulaire de création
├── edit.html.twig   → Formulaire de modification
└── show.html.twig   → Détails d'une matière
```

#### Course (4 templates)
```
templates/course/
├── index.html.twig    → Liste des cours (par classe)
├── new.html.twig      → Formulaire de création
├── edit.html.twig     → Formulaire de modification
├── show.html.twig     → Détails d'un cours
└── schedule.html.twig → Emploi du temps visuel
```

---

## 📊 Base de Données

### Tables Créées

#### classroom
```sql
CREATE TABLE classroom (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    school_year_id INT NOT NULL,
    level_id INT NOT NULL,
    main_teacher_id INT DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    capacity INT DEFAULT NULL,
    room VARCHAR(100) DEFAULT NULL,
    description LONGTEXT,
    is_active TINYINT(1) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (school_id) REFERENCES school(id),
    FOREIGN KEY (school_year_id) REFERENCES school_year(id),
    FOREIGN KEY (level_id) REFERENCES level(id),
    FOREIGN KEY (main_teacher_id) REFERENCES user(id)
);
```

#### subject
```sql
CREATE TABLE subject (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT DEFAULT NULL,
    level_id INT DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    coefficient NUMERIC(5, 2),
    description LONGTEXT,
    type VARCHAR(50),
    hours_per_week INT,
    color VARCHAR(7),
    is_active TINYINT(1) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (school_id) REFERENCES school(id),
    FOREIGN KEY (level_id) REFERENCES level(id)
);
```

#### course
```sql
CREATE TABLE course (
    id INT AUTO_INCREMENT PRIMARY KEY,
    classroom_id INT NOT NULL,
    subject_id INT NOT NULL,
    teacher_id INT DEFAULT NULL,
    day_of_week VARCHAR(20) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room VARCHAR(100),
    notes LONGTEXT,
    is_active TINYINT(1) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (classroom_id) REFERENCES classroom(id),
    FOREIGN KEY (subject_id) REFERENCES subject(id),
    FOREIGN KEY (teacher_id) REFERENCES user(id)
);
```

### Relations

```
School (1) ──< (N) Classroom
SchoolYear (1) ──< (N) Classroom
Level (1) ──< (N) Classroom
User (1) ──< (N) Classroom (mainTeacher)

School (1) ──< (N) Subject
Level (1) ──< (N) Subject

Classroom (1) ──< (N) Course
Subject (1) ──< (N) Course
User (1) ──< (N) Course (teacher)
```

---

## 📊 Données de Test

### Matières Créées

**École Maternelle** (4 matières) :
| ID | Nom | Code | Coef | Heures | Couleur |
|----|-----|------|------|--------|---------|
| 1 | Langage oral | LANG-MAT | 1.0 | 6h | #FF6384 |
| 2 | Activités artistiques | ART-MAT | 1.0 | 4h | #36A2EB |
| 3 | Activités physiques | SPORT-MAT | 1.0 | 3h | #FFCE56 |
| 4 | Découverte du monde | DEC-MAT | 1.0 | 4h | #4BC0C0 |

**École Primaire** (6 matières) :
| ID | Nom | Code | Coef | Heures | Couleur |
|----|-----|------|------|--------|---------|
| 5 | Français | FR-PRI | 3.0 | 8h | #FF6384 |
| 6 | Mathématiques | MATH-PRI | 3.0 | 5h | #36A2EB |
| 7 | Sciences | SCI-PRI | 2.0 | 3h | #4BC0C0 |
| 8 | Histoire-Géographie | HG-PRI | 2.0 | 3h | #FFCE56 |
| 9 | Arts plastiques | ART-PRI | 1.0 | 2h | #9966FF |
| 10 | EPS | EPS-PRI | 1.0 | 3h | #FF9F40 |

### Classes Créées

**École Maternelle** (3 classes) :
| ID | Nom | Code | Niveau | Année | Capacité | Salle |
|----|-----|------|--------|-------|----------|-------|
| 1 | Petite Section A | MAT-PS-A | PS | 2024-2025 | 25 | Salle 1 |
| 2 | Moyenne Section A | MAT-MS-A | MS | 2024-2025 | 25 | Salle 2 |
| 3 | Grande Section A | MAT-GS-A | GS | 2024-2025 | 25 | Salle 3 |

**École Primaire** (5 classes) :
| ID | Nom | Code | Niveau | Année | Capacité | Salle |
|----|-----|------|--------|-------|----------|-------|
| 4 | CP B | PRI-CP-B | CP | 2024-2025 | 30 | Salle 10 |
| 5 | CE1 B | PRI-CE1-B | CE1 | 2024-2025 | 30 | Salle 11 |
| 6 | CE2 B | PRI-CE2-B | CE2 | 2024-2025 | 30 | Salle 12 |
| 7 | CM1 B | PRI-CM1-B | CM1 | 2024-2025 | 30 | Salle 13 |
| 8 | CM2 B | PRI-CM2-B | CM2 | 2024-2025 | 30 | Salle 14 |

### Cours Créés

**Petite Section A** (5 cours) :
- Lundi 08h-09h : Langage oral
- Lundi 09h-10h : Langage oral
- Lundi 10h-11h : Activités artistiques
- Mardi 08h-09h : Activités physiques
- Mardi 09h-10h : Découverte du monde

**CP B** (6 cours) :
- Lundi 08h-09h : Français
- Lundi 09h-10h : Mathématiques
- Lundi 10h-11h : Sciences
- Mardi 08h-09h : Français
- Mardi 09h-10h : Mathématiques
- Mercredi 08h-10h : EPS

---

## 🎨 Interface Utilisateur

### Navigation

```
Menu Sidebar > Académique
  ├── Classes
  ├── Matières
  ├── Emplois du Temps
  └── Élèves (à venir)
```

### Page Classes (/admin/classrooms)

```
┌─────────────────────────────────────────────────────────────────────┐
│ 🏫 Gestion des Classes                   [+ Nouvelle Classe]       │
├─────────────────────────────────────────────────────────────────────┤
│ Code       | Nom             | Niveau | Année     | Prof. | Actions│
├─────────────────────────────────────────────────────────────────────┤
│ MAT-PS-A   | Petite Sect. A  | PS     | 2024-2025 | -     | 👁️ 📅 ✏️│
│ MAT-MS-A   | Moyenne Sect. A | MS     | 2024-2025 | -     | 👁️ 📅 ✏️│
│ MAT-GS-A   | Grande Sect. A  | GS     | 2024-2025 | -     | 👁️ 📅 ✏️│
└─────────────────────────────────────────────────────────────────────┘

Filtré par : École Maternelle (basculement automatique)
```

### Page Matières (/admin/subjects)

```
┌──────────────────────────────────────────────────────────────────────┐
│ 📚 Gestion des Matières                  [+ Nouvelle Matière]       │
├──────────────────────────────────────────────────────────────────────┤
│ Code      | Nom           | Type  | Coef | H/sem | Couleur | Actions│
├──────────────────────────────────────────────────────────────────────┤
│ LANG-MAT  | Langage oral  | Oblig | 1.0  | 6h    | 🔴     | 👁️ ✏️ 🗑️│
│ ART-MAT   | Act. artist.  | Oblig | 1.0  | 4h    | 🔵     | 👁️ ✏️ 🗑️│
│ SPORT-MAT | Act. phys.    | Oblig | 1.0  | 3h    | 🟡     | 👁️ ✏️ 🗑️│
│ DEC-MAT   | Découverte    | Oblig | 1.0  | 4h    | 🟢     | 👁️ ✏️ 🗑️│
└──────────────────────────────────────────────────────────────────────┘

Filtré par : École Maternelle
```

### Page Emploi du Temps (/admin/courses/schedule/{id})

```
┌──────────────────────────────────────────────────────────────────────┐
│ 📅 Emploi du Temps - Petite Section A (2024-2025)                   │
├──────────────────────────────────────────────────────────────────────┤
│ Horaire │ Lundi          │ Mardi          │ Mercredi│ Jeudi│ Vendr.│
├─────────┼────────────────┼────────────────┼─────────┼──────┼───────┤
│ 08h-09h │ 🔴 Langage oral│ 🟡 Act. phys.  │         │      │       │
│         │ Jean MARTIN    │ Jean MARTIN    │         │      │       │
│         │ Salle 1        │ Salle 1        │         │      │       │
├─────────┼────────────────┼────────────────┼─────────┼──────┼───────┤
│ 09h-10h │ 🔴 Langage oral│ 🟢 Découverte  │         │      │       │
│         │ Jean MARTIN    │ Jean MARTIN    │         │      │       │
│         │ Salle 1        │ Salle 1        │         │      │       │
├─────────┼────────────────┼────────────────┼─────────┼──────┼───────┤
│ 10h-11h │ 🔵 Act. artist.│                │         │      │       │
│         │ Jean MARTIN    │                │         │      │       │
│         │ Salle 1        │                │         │      │       │
└─────────┴────────────────┴────────────────┴─────────┴──────┴───────┘

Couleurs : 🔴 Langage | 🔵 Artistique | 🟡 Physique | 🟢 Découverte
```

---

## 🔄 Filtrage Contextualisé

### Principe

Toutes les données sont **automatiquement filtrées** selon l'établissement sélectionné par l'utilisateur.

### Exemple

```
User sélectionne : École Maternelle
        ↓
/admin/classrooms
        ↓
Affiche : 3 classes (PS A, MS A, GS A)
Masque : 5 classes du Primaire

/admin/subjects
        ↓
Affiche : 4 matières (Langage, Art, Sport, Découverte)
Masque : 6 matières du Primaire

/admin/courses
        ↓
Liste : Classes de la Maternelle uniquement
Emploi du temps : Classes de la Maternelle uniquement
```

### Avantages

- ✅ **Pertinence** : Données contextualisées
- ✅ **Clarté** : Pas de confusion entre établissements
- ✅ **Performance** : Requêtes optimisées
- ✅ **UX** : Navigation fluide et intuitive

---

## 🚀 Utilisation

### Créer une Classe

```
1. Sélectionner l'établissement (ex: École Maternelle)
2. Aller sur /admin/classrooms
3. Cliquer "Nouvelle Classe"
4. Formulaire pré-rempli avec :
   - Établissement : École Maternelle ✅
   - Année : 2024-2025 ✅
5. Remplir :
   - Nom : "Petite Section B"
   - Code : "MAT-PS-B"
   - Niveau : Petite Section (PS)
   - Capacité : 25
   - Salle : Salle 4
6. Enregistrer
7. ✅ Classe créée et visible dans la liste
```

### Créer une Matière

```
1. Sélectionner l'établissement
2. Aller sur /admin/subjects
3. Cliquer "Nouvelle Matière"
4. Formulaire pré-rempli avec l'établissement
5. Remplir :
   - Nom : "Anglais"
   - Code : "ENG-PRI"
   - Type : Obligatoire
   - Coefficient : 2.0
   - Heures/semaine : 3
   - Couleur : #FF5733
6. Enregistrer
7. ✅ Matière créée
```

### Créer un Emploi du Temps

```
1. Aller sur /admin/courses
2. Sélectionner une classe dans le dropdown
3. Cliquer "Nouveau Cours"
4. Remplir :
   - Classe : CP B
   - Matière : Français
   - Enseignant : Sophie DUPRÉ
   - Jour : Lundi
   - Heure début : 08:00
   - Heure fin : 09:00
   - Salle : Salle 10
5. Enregistrer
6. Répéter pour tous les créneaux
7. Cliquer "Voir l'emploi du temps"
8. ✅ Emploi du temps visuel affiché
```

---

## 📈 Statistiques

```
Entités créées:         3 (Classroom, Subject, Course)
Repositories créés:     3
Formulaires créés:      3
Contrôleurs créés:      3
Templates créés:        12
Routes créées:          18
Tables créées:          3
Fixtures:               1 (Module3Fixtures)

Classes test:           8 (3 maternelle + 5 primaire)
Matières test:          10 (4 maternelle + 6 primaire)
Cours test:             11 (5 maternelle + 6 primaire)

Total lignes de code:   ~2500 lignes
```

---

## 🧪 Tests Manuels

### Test 1 : Accès aux Classes

```bash
# URL: http://localhost:8000/admin/classrooms
# 1. Se connecter
# 2. Sélectionner "École Maternelle"
# ✅ Doit afficher 3 classes (PS, MS, GS)
# 3. Changer pour "École Primaire"
# ✅ Doit afficher 5 classes (CP, CE1, CE2, CM1, CM2)
```

### Test 2 : Création de Classe

```bash
# 1. Aller sur /admin/classrooms/new
# ✅ École et année pré-remplies
# 2. Remplir le formulaire
# 3. Enregistrer
# ✅ Classe créée et visible
```

### Test 3 : Emploi du Temps

```bash
# 1. Aller sur /admin/courses
# 2. Sélectionner "Petite Section A"
# ✅ Liste des 5 cours affichée
# 3. Cliquer "Voir l'emploi du temps"
# ✅ Grille horaire affichée avec les cours
```

### Test 4 : Filtrage

```bash
# 1. École Maternelle sélectionnée
# 2. Aller sur /admin/subjects
# ✅ 4 matières (maternelle uniquement)
# 3. Changer pour École Primaire
# ✅ 6 matières (primaire uniquement)
```

---

## 🎯 Fonctionnalités

### CRUD Complet

```
✅ Create   → Créer classes, matières, cours
✅ Read     → Lister et voir détails
✅ Update   → Modifier
✅ Delete   → Supprimer
✅ Toggle   → Activer/Désactiver
```

### Filtrage

```
✅ Par établissement
✅ Par année scolaire
✅ Par niveau
✅ Par classe (pour les cours)
```

### Emploi du Temps

```
✅ Vue tabulaire (grille horaire)
✅ Couleurs par matière
✅ Informations complètes (prof, salle)
✅ Organisation par jour
```

---

## 📱 Navigation

### Sidebar

```
Administration
  ├── Groupes d'Établissements
  ├── Établissements
  ├── Années Scolaires
  ├── Niveaux
  └── Utilisateurs

Académique                       ← NOUVEAU
  ├── Classes                    ← NOUVEAU
  ├── Matières                   ← NOUVEAU
  ├── Emplois du Temps           ← NOUVEAU
  └── Élèves (à venir)
```

### Breadcrumb

```
Dashboard → Académique → Classes → Détails Classe → Emploi du Temps
```

---

## 💡 Cas d'Usage

### Directeur de Maternelle

```
Marie DUPONT (Directrice)
Établissement : École Maternelle

Actions possibles :
1. Créer les 3 classes (PS, MS, GS)
2. Définir les 4 matières
3. Planifier l'emploi du temps
4. Assigner les enseignants
5. Définir les salles

Résultat :
- 3 classes opérationnelles
- 4 matières avec coefficients
- Emplois du temps complets
```

### Enseignant

```
Jean MARTIN (Enseignant)
Classes : Petite Section A

Actions possibles :
1. Consulter son emploi du temps
2. Voir les matières qu'il enseigne
3. Vérifier les salles

Résultat :
- Planning clair
- Informations à jour
```

---

## 🔗 Intégration avec Module 1 & 2

### Relations

```
Module 1 (Établissements)
  ├── School
  ├── SchoolYear
  └── Level
        ↓
Module 3 (Académique)
  ├── Classroom (utilise School, SchoolYear, Level)
  ├── Subject (utilise School, Level)
  └── Course (utilise Classroom, Subject)

Module 2 (Utilisateurs)
  └── User
        ↓
Module 3
  ├── Classroom.mainTeacher (User)
  └── Course.teacher (User)
```

### Flux de Données

```
1. Sélection Établissement (Module 1)
   └─> Classes filtrées (Module 3)
        └─> Cours filtrés (Module 3)

2. Création Niveau (Module 1)
   └─> Disponible pour Classes (Module 3)

3. Création Utilisateur Enseignant (Module 2)
   └─> Assignable aux Cours (Module 3)
```

---

## 🎉 Résultat Final

```
╔══════════════════════════════════════════════════════════╗
║                                                          ║
║   ✅  MODULE 3 - GESTION ACADÉMIQUE TERMINÉ              ║
║                                                          ║
║   • 3 entités (Classroom, Subject, Course)               ║
║   • 3 repositories avec méthodes de filtrage             ║
║   • 3 formulaires intelligents                           ║
║   • 3 contrôleurs CRUD complets                          ║
║   • 12 templates Twig professionnels                     ║
║   • 18 routes fonctionnelles                             ║
║   • Filtrage par établissement                           ║
║   • Emploi du temps visuel                               ║
║   • 8 classes de test                                    ║
║   • 10 matières de test                                  ║
║   • 11 cours de test                                     ║
║                                                          ║
║        100% OPÉRATIONNEL ! 🚀                            ║
║                                                          ║
╚══════════════════════════════════════════════════════════╝
```

---

**Version** : 2.0.0  
**Date** : 10 Octobre 2025  
**Status** : ✅ Module 3 Complet et Testé  
**Prochaine étape** : Module 4 - Gestion des Notes et Évaluations

