# 🔍 Système de Filtrage par Contexte - EDU-SCHOOL

## 📋 Vue d'ensemble

EDU-SCHOOL implémente un système de filtrage automatique basé sur le contexte (établissement et année scolaire sélectionnés). Cela permet de voir uniquement les données pertinentes selon l'établissement et l'année en cours.

## 🏗️ Architecture

### 1. Hiérarchie des Entités

```
SchoolGroup (Groupe d'établissements)
    ├── School (Établissement) [Many-to-One]
    │   ├── SchoolYear (Année scolaire) [One-to-Many]
    │   │   └── Period (Période) [One-to-Many]
    │   ├── Level (Niveau) [One-to-Many, nullable]
    │   └── User (Utilisateurs) [Many-to-Many]
    │
    └── School (Établissement)
        └── ...
```

### 2. Relations

```sql
-- Groupe → Écoles
school.school_group_id → school_group.id

-- École → Années
school_year.school_id → school.id

-- Année → Périodes  
period.school_year_id → school_year.id

-- École → Niveaux (optionnel, NULL = niveau global)
level.school_id → school.id

-- École ↔ Utilisateurs (Many-to-Many)
user_school.user_id → user.id
user_school.school_id → school.id
```

---

## 🎯 Fonctionnement du Filtrage

### 1. Contexte Global

**Service** : `SchoolContextService`

**Variables de session** :
- `current_school_id` - ID de l'établissement en cours
- `current_school_year_id` - ID de l'année scolaire en cours

**Méthodes** :
```php
getCurrentSchool()          // Obtenir l'établissement en cours
setCurrentSchool($school)   // Définir l'établissement en cours
getCurrentSchoolYear()      // Obtenir l'année en cours
setCurrentSchoolYear($year) // Définir l'année en cours
```

### 2. Filtrage Automatique

**Doctrine Filter** : `SchoolFilter`

**Activation** :
- Automatique via `SchoolContextSubscriber`
- S'active sur chaque requête si un établissement est sélectionné
- Filtre transparent (aucun code à ajouter dans les repositories)

**Entités filtrées** :
- ✅ `SchoolYear` - Années de l'établissement en cours uniquement
- ✅ `Level` - Niveaux de l'établissement en cours + niveaux globaux (school_id = NULL)
- 🔄 Futures : `Classroom`, `Subject`, `Course`, `Grade`, `Attendance`, etc.

### 3. Variables Twig Globales

**Injectées automatiquement** :
```twig
{{ current_school }}           {# Établissement en cours #}
{{ current_school_year }}      {# Année en cours #}
{{ available_schools }}        {# Liste des établissements disponibles #}
{{ available_school_years }}   {# Liste des années de l'établissement en cours #}
```

---

## 🔧 Utilisation

### Dans le Header (Déjà implémenté)

```twig
{# Dropdown Établissement #}
{% if current_school %}
    <button>{{ current_school.name }}</button>
    {% for school in available_schools %}
        <a href="{{ path('context_switch_school', {'id': school.id}) }}">
            {{ school.name }}
        </a>
    {% endfor %}
{% endif %}

{# Dropdown Année #}
{% if current_school_year %}
    <button>{{ current_school_year.name }}</button>
    {% for year in available_school_years %}
        <a href="{{ path('context_switch_year', {'id': year.id}) }}">
            {{ year.name }}
        </a>
    {% endfor %}
{% endif %}
```

### Dans les Repositories

```php
// Exemple: Obtenir les années scolaires
public function index(SchoolYearRepository $repository): Response
{
    // Les années de l'établissement en cours UNIQUEMENT
    $schoolYears = $repository->findAll();
    
    // Le filter est appliqué automatiquement !
    // Pas besoin de: ->where('sy.school = :school')
}
```

### Désactiver le Filtre Temporairement

```php
// Si besoin de voir TOUTES les données (ex: page admin)
$this->entityManager->getFilters()->disable('school_filter');

// Faire vos requêtes sans filtrage

// Réactiver
$filter = $this->entityManager->getFilters()->enable('school_filter');
$filter->setParameter('school_id', $currentSchool->getId());
```

---

## 📊 Tables et Colonnes

### school_group (Groupe d'établissements)

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT(PK) | Identifiant |
| name | VARCHAR(255) | Nom du groupe |
| code | VARCHAR(50) | Code unique |
| description | TEXT | Description |
| is_active | BOOLEAN | Actif |
| created_at | DATETIME | Date de création |
| updated_at | DATETIME | Date de modification |

### school (Établissement) - Modifié

| Colonne | Type | Description |
|---------|------|-------------|
| ... | ... | Colonnes existantes |
| **school_group_id** | INT(FK) | **Nouveau** : Groupe d'appartenance |

### level (Niveau) - Modifié

| Colonne | Type | Description |
|---------|------|-------------|
| ... | ... | Colonnes existantes |
| **school_id** | INT(FK) | **Nouveau** : École spécifique (NULL = global) |

---

## 🎯 Données de Test

### Groupes Créés (3)

```
1. Groupe Enseignement Fondamental (GRP001)
   └── Maternelle + Primaire

2. Groupe Enseignement Secondaire (GRP002)
   └── Collège + Lycée

3. Groupe Enseignement Supérieur (GRP003)
   └── Université
```

### Répartition

```
Groupe 1 (Fondamental):
  ├── École Maternelle Les Petits Bambins (MAT001)
  └── École Primaire Jean Moulin (PRI001)

Groupe 2 (Secondaire):
  ├── Collège Pierre et Marie Curie (COL001)
  └── Lycée Victor Hugo (LYC001)

Groupe 3 (Supérieur):
  └── Université Paris Sciences (UNI001)
```

### Utilisateurs - Établissements

```
superadmin:     TOUS les établissements (5)
admin:          TOUS les établissements (5)
directeur1:     Maternelle (1)
directeur2:     Primaire (1)
Enseignants:    1 établissement chacun
Personnel:      Maternelle
Élèves:         Rotation sur les 5
Parents:        Maternelle
```

---

## 🎨 Comportement de l'Interface

### Sélection d'un Établissement

**Avant** :
```
Années scolaires visibles: TOUTES (10 années de 5 établissements)
Niveaux visibles: TOUS (20 niveaux globaux)
```

**Après sélection "École Primaire Jean Moulin"** :
```
Années scolaires visibles: 2 années (2023-2024, 2024-2025) de l'école primaire UNIQUEMENT
Niveaux visibles: 20 niveaux globaux + niveaux spécifiques à l'école primaire
Utilisateurs liés: Seulement ceux assignés à cette école
```

### Changement d'Établissement

```
1. Utilisateur clique sur dropdown établissement
2. Sélectionne "Collège Pierre et Marie Curie"
3. → context_switch_school route appelée
4. → Session mise à jour
5. → Filter Doctrine reconfigur é avec new school_id
6. → Année bascule automatiquement vers l'année en cours du collège
7. → Toutes les listes sont filtrées automatiquement
```

---

## 🔧 Configuration

### doctrine.yaml

```yaml
orm:
    filters:
        school_filter:
            class: App\Doctrine\Filter\SchoolFilter
            enabled: false  # Activé dynamiquement par le subscriber
```

### SchoolFilter.php

```php
// Entités filtrées
$filteredEntities = [
    SchoolYear::class,  // Années de l'école en cours
    Level::class,       // Niveaux de l'école + globaux
    // À ajouter : Classroom, Subject, Grade, etc.
];

// Logique de filtrage
if ($entity === SchoolYear::class) {
    return "school_id = {$school_id}";
}

if ($entity === Level::class) {
    return "(school_id = {$school_id} OR school_id IS NULL)";
}
```

---

## 🚀 Ajouter le Filtrage à une Nouvelle Entité

### Exemple : Entité Classroom (future)

**Étape 1** : Ajouter la relation dans l'entité

```php
#[ORM\Entity]
class Classroom
{
    #[ORM\ManyToOne(targetEntity: School::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?School $school = null;
    
    // ... autres propriétés
}
```

**Étape 2** : Ajouter l'entité au filter

```php
// Dans SchoolFilter.php
$filteredEntities = [
    SchoolYear::class,
    Level::class,
    Classroom::class,  // Nouveau
];

// Ajouter la logique de filtrage
if ($targetEntity->getName() === Classroom::class) {
    return sprintf('%s.school_id = %s', $targetTableAlias, $schoolId);
}
```

**C'est tout !** Le filtrage est automatique partout.

---

## 💡 Cas d'Usage

### 1. Multi-Écoles

Un groupe scolaire avec plusieurs établissements :
```
- École Maternelle Bambins
- École Primaire Jean Moulin  
- Collège Curie
```

**Utilisateur connecté** : Directeur avec accès aux 3 établissements

**Comportement** :
- Par défaut : Première école (Maternelle)
- Peut basculer vers Primaire → Voir uniquement données du Primaire
- Peut basculer vers Collège → Voir uniquement données du Collège

### 2. Enseignant Multi-Écoles

Un enseignant enseigne dans 2 établissements :
```
- Collège Curie (Mathématiques 4ème)
- Lycée Hugo (Mathématiques Terminale)
```

**Comportement** :
- Bascule entre Collège et Lycée
- Voit les classes, notes, élèves de l'établissement sélectionné
- Peut saisir des notes différentes dans chaque établissement

### 3. Élève Transféré

Un élève était au Collège, maintenant au Lycée :
```
- Historique: Collège Curie (2023-2024)
- Actuel: Lycée Hugo (2024-2025)
```

**Comportement** :
- Peut voir son historique en changeant d'établissement ET d'année
- Données bien séparées par établissement

---

## ⚙️ Gestion Manuelle

### Désactiver le Filtrage pour une Route

```php
#[Route('/admin/all-data', name: 'admin_all_data')]
public function allData(EntityManagerInterface $em): Response
{
    // Désactiver temporairement
    $em->getFilters()->disable('school_filter');
    
    // Toutes les données, tous établissements
    $allSchoolYears = $this->schoolYearRepository->findAll();
    
    // Réactiver
    $filter = $em->getFilters()->enable('school_filter');
    $filter->setParameter('school_id', $this->contextService->getCurrentSchool()->getId());
    
    return $this->render(...);
}
```

### Filtrer Manuellement dans DQL

```php
// Si le filter est désactivé ou pour plus de contrôle
$qb = $this->createQueryBuilder('sy')
    ->join('sy.school', 's')
    ->where('s.id = :schoolId')
    ->setParameter('schoolId', $currentSchool->getId());
```

---

## 📊 Schéma du Filtrage

```
Requête HTTP
    ↓
SchoolContextSubscriber (Event Listener)
    ├── Récupère l'établissement en session
    ├── Active le SchoolFilter Doctrine
    ├── Configure school_id parameter
    └── Injecte variables Twig globales
    ↓
Controller (execute)
    ↓
Repository (findAll, find, etc.)
    ↓
Doctrine Filter (appliqué automatiquement)
    ├── SchoolYear: WHERE school_id = X
    ├── Level: WHERE (school_id = X OR school_id IS NULL)
    └── Futures entités filtrées automatiquement
    ↓
Résultats filtrés retournés
    ↓
Vue Twig (affiche uniquement données filtrées)
```

---

## 🔒 Sécurité

### Isolation des Données

- ✅ Un utilisateur ne voit QUE les données de ses établissements
- ✅ Le filtre s'applique partout automatiquement
- ✅ Impossible de voir les données d'un autre établissement (sauf admin)
- ✅ Changement d'établissement = changement complet de contexte

### Exceptions

- **Super Admins** : Peuvent désactiver le filtre si nécessaire
- **Routes `/admin/global-*`** : Peuvent désactiver pour voir toutes les données
- **API** : Doit gérer le filtrage explicitement

---

## 📈 Avantages

### Performance
- ✅ Index sur school_id pour requêtes rapides
- ✅ Moins de données chargées
- ✅ Filtrage au niveau SQL

### Maintenabilité
- ✅ Pas de code de filtrage dans chaque repository
- ✅ Configuration centralisée
- ✅ Facile d'ajouter de nouvelles entités

### UX
- ✅ Données pertinentes uniquement
- ✅ Pas de confusion entre établissements
- ✅ Navigation intuitive
- ✅ Changement de contexte en 1 clic

---

## 🧪 Tests

### Tester le Filtrage

```bash
# 1. Se connecter
http://localhost:8000/login
Login: admin / Admin@123

# 2. Noter l'établissement affiché dans le header
→ "École Maternelle Les Petits Bambins"

# 3. Aller sur /admin/school-years
→ Doit afficher 2 années (2023-2024, 2024-2025) de la maternelle

# 4. Changer pour "Collège Pierre et Marie Curie"
Cliquer dropdown → Sélectionner Collège

# 5. Retourner sur /admin/school-years
→ Doit afficher 2 années du Collège UNIQUEMENT

# 6. Vérifier le changement d'année automatique
→ L'année affichée doit être celle du Collège
```

### SQL pour Vérification

```sql
-- Voir les années de l'école ID=1 (Maternelle)
SELECT * FROM school_year WHERE school_id = 1;

-- Voir les années de l'école ID=3 (Collège)
SELECT * FROM school_year WHERE school_id = 3;

-- Voir les relations user-school
SELECT u.username, s.name 
FROM user u
JOIN user_school us ON u.id = us.user_id
JOIN school s ON us.school_id = s.id;
```

---

## 🎨 Interface Utilisateur

### Header Dynamique

```
┌────────────────────────────────────────────────────────┐
│ [☰] [🏫 École Primaire Jean Moulin ▼] [📅 2024-2025 ▼]│
│                                          [🔍] [🔔] [👤]│
└────────────────────────────────────────────────────────┘
```

**Dropdowns** :

**Établissement** :
```
Changer d'établissement
  • École Maternelle Les Petits Bambins
  • École Primaire Jean Moulin           ✓ (actuel)
  • Collège Pierre et Marie Curie
  • Lycée Victor Hugo
  • Université Paris Sciences
```

**Année Scolaire** :
```
Changer d'année scolaire
  • 2023-2024
  • 2024-2025                            ✓ (actuel) [En cours]
```

---

## 📚 Exemples de Code

### Contrôleur avec Filtrage Automatique

```php
#[Route('/admin/school-years', name: 'admin_school_year_index')]
public function index(SchoolYearRepository $repository): Response
{
    // Automatiquement filtré par établissement en cours
    $schoolYears = $repository->findAll();
    
    return $this->render('school_year/index.html.twig', [
        'school_years' => $schoolYears,
    ]);
}
```

### Service avec Contexte

```php
class MyService
{
    public function __construct(
        private SchoolContextService $contextService
    ) {}
    
    public function doSomething(): void
    {
        $school = $this->contextService->getCurrentSchool();
        $year = $this->contextService->getCurrentSchoolYear();
        
        // Utiliser le contexte pour la logique métier
        if ($school->getType() === 'maternelle') {
            // Logique spécifique maternelle
        }
    }
}
```

---

## 🔄 Workflow Complet

### Connexion Utilisateur

```
1. Login → Authentification
2. Redirect vers Dashboard
3. SchoolContextSubscriber s'exécute:
   ├── Cherche établissement en session
   ├── Si aucun: Prend le 1er établissement de l'utilisateur
   ├── Active le SchoolFilter avec cet établissement
   ├── Charge l'année en cours de cet établissement
   └── Injecte les variables Twig
4. Dashboard s'affiche avec données filtrées
```

### Changement d'Établissement

```
1. Clic sur dropdown établissement
2. Sélection "Lycée Victor Hugo"
3. Route context_switch_school appelée
4. SchoolContextService met à jour la session
5. Recherche l'année en cours du Lycée
6. Met à jour la session de l'année
7. Redirect vers dashboard
8. Nouveau contexte appliqué:
   ├── Filter reconfiguré avec nouveau school_id
   ├── Toutes les requêtes filtrées automatiquement
   └── Interface mise à jour
```

---

## 📝 Bonnes Pratiques

### 1. Niveaux Globaux vs Spécifiques

```php
// Niveau GLOBAL (disponible partout)
$level->setSchool(null);  // school_id = NULL

// Niveau SPÉCIFIQUE à une école
$level->setSchool($school);  // school_id = X
```

**Exemple** :
- "CP", "CE1", "6ème" → Globaux (NULL)
- "CP Bilingue" → Spécifique à une école

### 2. Utilisateurs Multi-Écoles

```php
// Lier un enseignant à 2 écoles
$user->addSchool($ecole1);
$user->addSchool($ecole2);

// Il peut basculer entre les 2
// Verra les données de l'école sélectionnée uniquement
```

### 3. Super Admins

```php
// Super admins liés à TOUTES les écoles
foreach ($schools as $school) {
    $superAdmin->addSchool($school);
}

// Peuvent basculer entre toutes les écoles
// Voient tout dans chaque école
```

---

## 🚀 Pour les Modules Futurs

Lors de la création de nouvelles entités liées à un établissement :

```php
#[ORM\Entity]
class Classroom  // Exemple
{
    #[ORM\ManyToOne(targetEntity: School::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?School $school = null;
    
    // ... autres propriétés
}
```

Puis ajouter au filter :

```php
// Dans SchoolFilter.php
$filteredEntities = [
    SchoolYear::class,
    Level::class,
    Classroom::class,  // Ajouter ici
];
```

**C'est tout !** Filtrage automatique partout.

---

## ✅ Checklist de Vérification

- [x] SchoolGroup créé
- [x] School lié à SchoolGroup
- [x] User lié à School (Many-to-Many)
- [x] Level lié à School (optionnel)
- [x] SchoolFilter configuré
- [x] SchoolContextService créé
- [x] SchoolContextSubscriber créé
- [x] Routes de basculement créées
- [x] Header avec dropdowns
- [x] Fixtures avec données de test
- [x] 3 groupes créés
- [x] 5 écoles liées aux groupes
- [x] 24 utilisateurs liés aux écoles
- [x] Filtrage automatique actif

---

**Version** : 1.1.0  
**Date** : 09 Octobre 2025  
**Auteur** : Équipe EDU-SCHOOL

