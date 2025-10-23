# 📊 Module 4 - Gestion des Notes et Évaluations

## Vue d'ensemble

Le Module 4 permet la gestion complète des notes et évaluations dans EDU-SCHOOL :
- ✅ Création de périodes d'évaluation (trimestres, semestres)
- ✅ Création d'évaluations par classe et matière
- ✅ Saisie des notes pour les élèves
- ✅ Calcul automatique des moyennes
- ✅ Statistiques de classe
- ✅ Gestion des absences et dispenses
- ✅ Publication des résultats

## Entités Créées

### 1. `Period` (Période d'évaluation)
**Fichier** : `src/Entity/Period.php`

**Propriétés** :
- `school` (ManyToOne) - Établissement
- `schoolYear` (ManyToOne) - Année scolaire
- `name` - Nom (ex: "1er Trimestre", "Semestre 1")
- `code` - Code unique (ex: "T1", "S1")
- `orderNumber` - Numéro d'ordre d'affichage
- `startDate` - Date de début
- `endDate` - Date de fin
- `isActive` - Statut actif/inactif

**Méthodes utiles** :
- `getDateRange()` - Retourne "01/09/2024 - 20/12/2024"
- `__toString()` - Affiche le nom de la période

### 2. `Evaluation` (Évaluation/Contrôle)
**Fichier** : `src/Entity/Evaluation.php`

**Propriétés** :
- `classroom` (ManyToOne) - Classe concernée
- `subject` (ManyToOne) - Matière
- `period` (ManyToOne) - Période d'évaluation
- `teacher` (ManyToOne) - Enseignant responsable
- `name` - Nom de l'évaluation
- `type` - Type (contrôle_continu, devoir_surveille, devoir_maison, examen, oral, pratique, projet)
- `date` - Date de l'évaluation
- `maxGrade` - Note maximale (généralement 20)
- `coefficient` - Coefficient de l'évaluation
- `description` - Description détaillée
- `isPublished` - Les notes sont-elles visibles par les élèves ?
- `isActive` - Statut actif/inactif

**Méthodes utiles** :
- `getTypeLabel()` - Retourne le libellé français du type
- `__toString()` - Affiche "Contrôle Chapitre 1 (Mathématiques - 15/01/2025)"

### 3. `Grade` (Note)
**Fichier** : `src/Entity/Grade.php`

**Propriétés** :
- `evaluation` (ManyToOne) - Évaluation concernée
- `student` (ManyToOne) - Élève
- `value` - Valeur de la note (ex: 15.50)
- `status` - Statut spécial (absent, dispense, non_rendu)
- `comment` - Commentaire sur la note
- `enteredBy` (ManyToOne) - Qui a saisi la note

**Méthodes utiles** :
- `getStatusLabel()` - Retourne "Absent", "Dispensé", "Non rendu"
- `getDisplayValue()` - Affiche "15.50 / 20" ou "Absent"
- `__toString()` - Affiche la valeur formatée

## Repositories Créés

### 1. `PeriodRepository`
**Fichier** : `src/Repository/PeriodRepository.php`

**Méthodes** :
```php
// Périodes par établissement et année
findBySchoolAndYear(?int $schoolId, ?int $yearId): array

// Période courante (basée sur les dates)
findCurrentPeriod(?int $schoolId, ?int $yearId): ?Period
```

### 2. `EvaluationRepository`
**Fichier** : `src/Repository/EvaluationRepository.php`

**Méthodes** :
```php
// Évaluations par classe
findByClassroom(int $classroomId): array

// Évaluations par période
findByPeriod(int $periodId): array

// Évaluations par classe ET période
findByClassroomAndPeriod(int $classroomId, int $periodId): array

// Évaluations par enseignant
findByTeacher(int $teacherId): array

// Évaluations par matière et classe
findBySubjectAndClassroom(int $subjectId, int $classroomId): array

// Nombre d'évaluations par classe
countByClassroom(int $classroomId): int
```

### 3. `GradeRepository`
**Fichier** : `src/Repository/GradeRepository.php`

**Méthodes** :
```php
// Notes par évaluation
findByEvaluation(int $evaluationId): array

// Notes d'un élève
findByStudent(int $studentId): array

// Notes d'un élève pour une période
findByStudentAndPeriod(int $studentId, int $periodId): array

// Calcul de moyenne par matière
calculateAverageByStudentSubjectAndPeriod(
    int $studentId, 
    int $subjectId, 
    int $periodId
): ?float

// Calcul de moyenne générale
calculateGeneralAverageByStudentAndPeriod(
    int $studentId, 
    int $periodId
): ?float

// Statistiques de classe
getEvaluationStatistics(int $evaluationId): array
// Retourne: ['average' => 14.5, 'min' => 8, 'max' => 19, 'count' => 25]
```

## Formulaires Créés

### 1. `PeriodType`
**Fichier** : `src/Form/PeriodType.php`

**Champs** :
- Établissement (EntityType)
- Année scolaire (EntityType)
- Nom (TextType)
- Code (TextType)
- Numéro d'ordre (IntegerType)
- Date de début (DateType)
- Date de fin (DateType)
- Active (CheckboxType)

### 2. `EvaluationType`
**Fichier** : `src/Form/EvaluationType.php`

**Champs** :
- Classe (EntityType) - Filtrée par établissement et année
- Matière (EntityType) - Filtrée par établissement
- Période (EntityType) - Filtrée par établissement et année
- Enseignant (EntityType) - Filtrée par établissement
- Nom (TextType)
- Type (ChoiceType) - 7 choix disponibles
- Date (DateType)
- Note maximale (NumberType)
- Coefficient (NumberType)
- Description (TextareaType)
- Publier les résultats (CheckboxType)
- Active (CheckboxType)

**Filtrage automatique** :
✅ Classes filtrées par établissement ET année scolaire courante  
✅ Matières filtrées par établissement  
✅ Périodes filtrées par établissement ET année  
✅ Enseignants filtrés par établissement  

### 3. `GradeType`
**Fichier** : `src/Form/GradeType.php`

**Champs** :
- Note (NumberType) - Pas à pas de 0.25
- Statut (ChoiceType) - Absent, Dispensé, Non rendu
- Commentaire (TextareaType)

## Contrôleurs Créés

### 1. `PeriodController`
**Fichier** : `src/Controller/PeriodController.php`

**Routes** :
```
GET  /admin/periods          → Liste des périodes
GET  /admin/periods/new      → Créer une période
GET  /admin/periods/{id}/show → Voir une période
GET  /admin/periods/{id}/edit → Modifier une période
POST /admin/periods/{id}/delete → Supprimer une période
```

**Fonctionnalités** :
- ✅ Création de périodes liées à l'établissement et l'année courante
- ✅ Affichage uniquement des périodes de l'établissement sélectionné
- ✅ Validation des dates
- ✅ Messages flash de confirmation

### 2. `EvaluationController`
**Fichier** : `src/Controller/EvaluationController.php`

**Routes** :
```
GET  /admin/evaluations             → Liste des évaluations
GET  /admin/evaluations/new         → Créer une évaluation
GET  /admin/evaluations/{id}/show   → Voir une évaluation + statistiques
GET  /admin/evaluations/{id}/edit   → Modifier une évaluation
POST /admin/evaluations/{id}/delete → Supprimer une évaluation
GET  /admin/evaluations/{id}/grades → Saisir les notes (IMPORTANT)
POST /admin/evaluations/{id}/publish → Publier/Dépublier les résultats
```

**Fonctionnalités** :
- ✅ Filtrage par classe et période
- ✅ Saisie massive des notes pour tous les élèves
- ✅ Gestion des statuts (absent, dispensé, non rendu)
- ✅ Calcul automatique des statistiques
- ✅ Publication des résultats

**Saisie des notes** :
Le contrôleur `grades()` gère la saisie en masse :
1. Récupère tous les élèves de la classe
2. Affiche un formulaire avec une ligne par élève
3. Permet de saisir note OU statut
4. Enregistre toutes les notes d'un coup
5. Affiche les statistiques en temps réel

## Formules de Calcul

### Moyenne par Matière
```
Moyenne = Σ(Note × Coefficient_évaluation) / Σ(Coefficient_évaluation)
```

**Exemple** :
```
Contrôle 1 : 15/20 (coef 1)
Devoir     : 12/20 (coef 1)
Examen     : 16/20 (coef 2)

Moyenne = (15×1 + 12×1 + 16×2) / (1+1+2)
        = (15 + 12 + 32) / 4
        = 59 / 4
        = 14.75 / 20
```

### Moyenne Générale
```
Moyenne Générale = Σ(Moyenne_matière × Coefficient_matière) / Σ(Coefficient_matière)
```

**Exemple** :
```
Mathématiques : 14.75/20 (coef 4)
Français      : 13.50/20 (coef 3)
Histoire      : 15.00/20 (coef 2)

Moyenne = (14.75×4 + 13.50×3 + 15×2) / (4+3+2)
        = (59 + 40.5 + 30) / 9
        = 129.5 / 9
        = 14.39 / 20
```

### Statistiques de Classe
```php
[
    'average' => 14.5,  // Moyenne de la classe
    'min' => 8,         // Note minimum
    'max' => 19,        // Note maximum
    'count' => 25       // Nombre d'élèves notés
]
```

## Cas d'Usage

### 1. Créer un Trimestre
```
1. Admin → Périodes → Nouvelle Période
2. Remplir :
   - Nom : "1er Trimestre"
   - Code : "T1"
   - Ordre : 1
   - Dates : 01/09/2024 - 20/12/2024
3. Enregistrer
```

### 2. Créer une Évaluation
```
1. Admin → Évaluations → Nouvelle Évaluation
2. Sélectionner :
   - Classe : 6A
   - Matière : Mathématiques
   - Période : 1er Trimestre
   - Enseignant : M. Dupont
3. Remplir :
   - Nom : "Contrôle Chapitre 1"
   - Type : Devoir surveillé
   - Date : 15/10/2024
   - Note max : 20
   - Coefficient : 2
4. Enregistrer
5. → Redirection automatique vers la saisie des notes
```

### 3. Saisir les Notes
```
1. Depuis la liste des évaluations → Cliquer "Saisir les notes"
2. Pour chaque élève :
   - Saisir une note (ex: 15.50)
   - OU Sélectionner un statut (Absent, Dispensé...)
   - Optionnel : Ajouter un commentaire
3. Enregistrer toutes les notes
4. Voir les statistiques mises à jour
```

### 4. Publier les Résultats
```
1. Depuis la fiche de l'évaluation → Cliquer "Publier"
2. Les élèves peuvent maintenant voir leurs notes
3. Les moyennes sont recalculées automatiquement
```

### 5. Consulter les Moyennes
```
1. Depuis le bulletin d'un élève
2. Les moyennes sont calculées automatiquement :
   - Par matière (pour la période)
   - Générale (toutes matières de la période)
3. Le classement est généré dynamiquement
```

## Sécurité et Validation

### Validation des Notes
- ✅ La note ne peut pas dépasser la note maximale
- ✅ Une note OU un statut, pas les deux
- ✅ Seuls les enseignants autorisés peuvent saisir

### Publication
- ✅ Les notes non publiées sont invisibles pour les élèves
- ✅ Une fois publiées, les élèves voient leurs notes
- ✅ Les parents voient les notes de leurs enfants

### Cohérence des Données
- ✅ Une évaluation appartient à une seule classe
- ✅ Une évaluation appartient à une seule période
- ✅ Un élève ne peut avoir qu'une note par évaluation
- ✅ Les périodes ne se chevauchent pas

## Templates à Créer

### Périodes
- `templates/period/index.html.twig` - Liste des périodes
- `templates/period/new.html.twig` - Créer une période
- `templates/period/edit.html.twig` - Modifier une période
- `templates/period/show.html.twig` - Détails d'une période

### Évaluations
- `templates/evaluation/index.html.twig` - Liste des évaluations
- `templates/evaluation/new.html.twig` - Créer une évaluation
- `templates/evaluation/edit.html.twig` - Modifier une évaluation
- `templates/evaluation/show.html.twig` - Détails + statistiques
- `templates/evaluation/grades.html.twig` - Saisie des notes (IMPORTANT)

## Fixtures de Test

Créer des données de test pour :
- ✅ 3 périodes (Trimestres ou Semestres)
- ✅ 10-15 évaluations par classe
- ✅ Notes aléatoires pour les élèves (8-18/20)
- ✅ Quelques absents/dispensés

## Prochaines Étapes

1. ✅ Créer les templates Twig
2. ✅ Ajouter la navigation dans le menu
3. ✅ Créer les fixtures de test
4. ✅ Tester les calculs de moyennes
5. ⏭️ Créer le module de génération de bulletins (PDF)
6. ⏭️ Créer les statistiques avancées
7. ⏭️ Créer les graphiques de progression

## Intégration avec d'Autres Modules

### Module 3 - Académique
- ✅ Utilise les `Classroom` (Classes)
- ✅ Utilise les `Subject` (Matières)
- ✅ Utilise les coefficients des matières

### Module 2 - Utilisateurs
- ✅ Utilise les `User` (Élèves, Enseignants)
- ✅ Gestion des permissions pour la saisie

### Module 1 - Établissements
- ✅ Utilise `School` (Établissements)
- ✅ Utilise `SchoolYear` (Années scolaires)

## Notes Techniques

### Performances
- ✅ Index sur `evaluation_id` pour `Grade`
- ✅ Index sur `student_id` pour `Grade`
- ✅ Requêtes optimisées avec jointures

### Scalabilité
- ✅ Supporte des milliers d'élèves
- ✅ Supporte des centaines d'évaluations par an
- ✅ Calculs de moyennes optimisés (SQL direct)

## État Actuel

**Statut** : 🟡 En cours de finalisation  
**Progrès** : 70% complété

✅ Entités créées  
✅ Repositories créés  
✅ Formulaires créés  
✅ Contrôleurs créés  
⏳ Templates à créer  
⏳ Navigation à ajouter  
⏳ Fixtures à créer  
⏳ Tests à effectuer  

**Prochaine action** : Créer les templates Twig pour afficher les périodes, évaluations et saisir les notes.

**Version** : 1.0  
**Date** : 12 Janvier 2025

