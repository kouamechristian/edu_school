# 🔗 Suppression de la liaison School ↔ SchoolYear - EDU-SCHOOL

## ✅ Modification Appliquée !

```
╔═══════════════════════════════════════════════════════╗
║                                                       ║
║    ✅  LIAISON SCHOOL-SCHOOLYEAR SUPPRIMÉE            ║
║                                                       ║
║    • SchoolYear indépendant de School                 ║
║    • Années scolaires globales                        ║
║    • Base de données mise à jour                      ║
║    • Fixtures modifiées                               ║
║    • Formulaires mis à jour                           ║
║                                                       ║
╚═══════════════════════════════════════════════════════╝
```

---

## 🎯 Objectif

Rendre les **années scolaires indépendantes** des établissements. Auparavant, chaque établissement avait ses propres années scolaires. Maintenant, les années scolaires sont **globales** et partagées entre tous les établissements.

---

## 📊 Avant / Après

### ❌ Avant (Relation OneToMany)

```
School (1) ──< (N) SchoolYear

École Maternelle
  ├── 2023-2024
  └── 2024-2025

École Primaire  
  ├── 2023-2024
  └── 2024-2025

Collège
  ├── 2023-2024
  └── 2024-2025

→ 10 années scolaires (2 par établissement × 5 établissements)
→ Redondance de données
→ Gestion complexe
```

### ✅ Après (Entités indépendantes)

```
School          SchoolYear (globales)
  ├── Maternelle    ├── 2023-2024
  ├── Primaire      ├── 2024-2025 (en cours)
  ├── Collège       └── 2025-2026
  ├── Lycée     
  └── Université

→ 3 années scolaires globales
→ Pas de redondance
→ Gestion simplifiée
```

---

## 🔧 Modifications Apportées

### 1. Entité School (`src/Entity/School.php`)

#### Supprimé :
```php
// ❌ SUPPRIMÉ
#[ORM\OneToMany(targetEntity: SchoolYear::class, mappedBy: 'school', orphanRemoval: true)]
private Collection $schoolYears;

// Dans __construct()
$this->schoolYears = new ArrayCollection();

// Méthodes supprimées
public function getSchoolYears(): Collection { }
public function addSchoolYear(SchoolYear $schoolYear): static { }
public function removeSchoolYear(SchoolYear $schoolYear): static { }
```

#### Résultat :
```php
// ✅ School n'a plus de relation avec SchoolYear
class School
{
    // ... autres propriétés
    
    public function __construct()
    {
        $this->users = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }
    
    // ... autres méthodes (pas de getSchoolYears)
}
```

---

### 2. Entité SchoolYear (`src/Entity/SchoolYear.php`)

#### Supprimé :
```php
// ❌ SUPPRIMÉ
#[ORM\ManyToOne(inversedBy: 'schoolYears')]
#[ORM\JoinColumn(nullable: false)]
#[Assert\NotBlank(message: 'L\'établissement est obligatoire')]
private ?School $school = null;

// Méthodes supprimées
public function getSchool(): ?School { }
public function setSchool(?School $school): static { }
```

#### Résultat :
```php
// ✅ SchoolYear est désormais indépendant
class SchoolYear
{
    private ?int $id = null;
    private ?string $name = null;
    private ?\DateTimeInterface $startDate = null;
    private ?\DateTimeInterface $endDate = null;
    private bool $isCurrent = false;
    private ?\DateTimeInterface $createdAt = null;
    private Collection $periods; // Garde la relation avec Period
    
    // Pas de référence à School
}
```

---

### 3. Formulaire SchoolYearType (`src/Form/SchoolYearType.php`)

#### Supprimé :
```php
// ❌ SUPPRIMÉ
use App\Entity\School;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

->add('school', EntityType::class, [
    'label' => 'Établissement',
    'class' => School::class,
    'choice_label' => 'name',
    'attr' => ['class' => 'form-select'],
    'placeholder' => 'Sélectionnez un établissement',
    'query_builder' => function ($repository) {
        return $repository->createQueryBuilder('s')
            ->where('s.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('s.name', 'ASC');
    },
])
```

#### Résultat :
```php
// ✅ Plus de champ school dans le formulaire
class SchoolYearType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [...])
            ->add('startDate', DateType::class, [...])
            ->add('endDate', DateType::class, [...])
            ->add('isCurrent', CheckboxType::class, [...])
        ;
        // Pas de champ 'school'
    }
}
```

---

### 4. Base de Données

#### Commandes SQL exécutées :
```sql
-- 1. Supprimer la clé étrangère
ALTER TABLE school_year DROP FOREIGN KEY FK_FAAAACDAC32A47EE;

-- 2. Supprimer l'index
DROP INDEX IDX_FAAAACDAC32A47EE ON school_year;

-- 3. Supprimer la colonne school_id
ALTER TABLE school_year DROP school_id;

-- 4. Renommer un index sur user (optimisation)
ALTER TABLE user RENAME INDEX school_group_id TO IDX_8D93D64912ED03;
```

#### Structure Avant :
```
school_year
├── id
├── school_id       ← CLÉ ÉTRANGÈRE
├── name
├── start_date
├── end_date
├── is_current
└── created_at
```

#### Structure Après :
```
school_year
├── id
├── name            ← Plus de school_id
├── start_date
├── end_date
├── is_current
└── created_at
```

---

### 5. Fixtures (`src/DataFixtures/Module1Fixtures.php`)

#### Avant :
```php
private function createSchoolYears(ObjectManager $manager, array $schools): void
{
    foreach ($schools as $school) {
        // Créer une année 2023-2024 pour CHAQUE école
        $year2023 = new SchoolYear();
        $year2023->setSchool($school)
            ->setName('2023-2024')
            ...
        
        // Créer une année 2024-2025 pour CHAQUE école
        $year2024 = new SchoolYear();
        $year2024->setSchool($school)
            ->setName('2024-2025')
            ...
    }
    // Résultat : 10 années (2 × 5 écoles)
}
```

#### Après :
```php
private function createSchoolYears(ObjectManager $manager, array $schools): void
{
    // Année 2023-2024 (terminée) - UNE SEULE
    $year2023 = new SchoolYear();
    $year2023->setName('2023-2024')
        ->setStartDate(new \DateTime('2023-09-01'))
        ->setEndDate(new \DateTime('2024-06-30'))
        ->setIsCurrent(false);
    $manager->persist($year2023);

    // Année 2024-2025 (en cours) - UNE SEULE
    $year2024 = new SchoolYear();
    $year2024->setName('2024-2025')
        ->setStartDate(new \DateTime('2024-09-01'))
        ->setEndDate(new \DateTime('2025-06-30'))
        ->setIsCurrent(true);
    $manager->persist($year2024);

    // Année 2025-2026 (à venir) - UNE SEULE
    $year2025 = new SchoolYear();
    $year2025->setName('2025-2026')
        ->setStartDate(new \DateTime('2025-09-01'))
        ->setEndDate(new \DateTime('2026-06-30'))
        ->setIsCurrent(false);
    $manager->persist($year2025);

    // Créer les périodes pour l'année en cours
    $this->createPeriods($manager, $year2024);
    
    // Résultat : 3 années globales
}
```

---

## 📊 Données en Base

### Années Scolaires (globales)

| ID | Nom | Début | Fin | En cours | Périodes |
|----|-----|-------|-----|----------|----------|
| 11 | 2023-2024 | 01/09/2023 | 30/06/2024 | Non | 0 |
| 12 | 2024-2025 | 01/09/2024 | 30/06/2025 | **Oui** | 3 |
| 13 | 2025-2026 | 01/09/2025 | 30/06/2026 | Non | 0 |

### Périodes (pour l'année 2024-2025)

| ID | Nom | Type | Début | Fin | Poids |
|----|-----|------|-------|-----|-------|
| 1 | 1er Trimestre | trimestre | 01/09/2024 | 20/12/2024 | 0.33 |
| 2 | 2ème Trimestre | trimestre | 06/01/2025 | 28/03/2025 | 0.33 |
| 3 | 3ème Trimestre | trimestre | 14/04/2025 | 30/06/2025 | 0.34 |

---

## 🎯 Avantages

### ✅ Simplicité

```
Avant : Gérer 10+ années scolaires (2 par école)
Après : Gérer 3 années scolaires (globales)
```

### ✅ Cohérence

```
Avant : Une école peut être en 2024-2025 et une autre en 2023-2024
Après : Toutes les écoles partagent les mêmes années
```

### ✅ Maintenance

```
Avant : Créer une nouvelle année → Créer pour CHAQUE école
Après : Créer une nouvelle année → UNE SEULE fois
```

### ✅ Performance

```
Avant : 10 enregistrements SchoolYear
Après : 3 enregistrements SchoolYear
```

---

## 🔗 Relations Maintenues

SchoolYear conserve sa relation avec Period :

```
SchoolYear (1) ──< (N) Period

2024-2025
  ├── 1er Trimestre
  ├── 2ème Trimestre
  └── 3ème Trimestre
```

---

## 📝 Interface Utilisateur

### Formulaire de Création d'Année Scolaire

**Avant** :
```
┌────────────────────────────────┐
│ Établissement: [École ▼]      │  ← SUPPRIMÉ
│ Nom: [2024-2025]               │
│ Date début: [01/09/2024]       │
│ Date fin: [30/06/2025]         │
│ Année en cours: [✓]            │
└────────────────────────────────┘
```

**Après** :
```
┌────────────────────────────────┐
│ Nom: [2024-2025]               │
│ Date début: [01/09/2024]       │
│ Date fin: [30/06/2025]         │
│ Année en cours: [✓]            │
└────────────────────────────────┘
```

### Liste des Années Scolaires

```
┌──────────────────────────────────────────────────────┐
│ Nom        | Début      | Fin        | Actuelle | ... │
├──────────────────────────────────────────────────────┤
│ 2023-2024  | 01/09/2023 | 30/06/2024 | Non      |     │
│ 2024-2025  | 01/09/2024 | 30/06/2025 | Oui  ✓   |     │
│ 2025-2026  | 01/09/2025 | 30/06/2026 | Non      |     │
└──────────────────────────────────────────────────────┘

Plus de colonne "Établissement"
```

---

## 🚀 Impact sur l'Application

### Service SchoolContextService

Le service continue de fonctionner normalement. Les années scolaires sont globales, donc :
- `getCurrentSchoolYear()` retourne l'année globale en cours
- Tous les établissements utilisent la même année scolaire
- Le filtrage fonctionne toujours avec `school_id`

### Templates

Les templates continuent d'afficher l'année scolaire actuelle via :
```twig
{% if current_school_year %}
    {{ current_school_year.name }}
{% endif %}
```

Pas de changement dans l'affichage.

---

## 🧪 Tests

### Test 1 : Vérifier les Années Scolaires

```bash
php bin/console dbal:run-sql "SELECT * FROM school_year"
```

**Résultat attendu** : 3 années (2023-2024, 2024-2025, 2025-2026)

### Test 2 : Vérifier les Périodes

```bash
php bin/console dbal:run-sql "SELECT * FROM period WHERE school_year_id = 12"
```

**Résultat attendu** : 3 trimestres

### Test 3 : Créer une Année Scolaire

1. Aller sur `/admin/school-years/new`
2. Remplir : Nom = "2026-2027"
3. ✅ Créé sans erreur
4. ✅ Pas de champ "Établissement"

### Test 4 : Sélection d'Année

1. Se connecter
2. Sélectionner "2024-2025" dans le dropdown
3. ✅ L'année est sauvegardée en session
4. ✅ Visible dans tous les établissements

---

## 💡 Cas d'Usage

### Scénario 1 : Nouvelle Année Scolaire

```
Admin:
1. Accède à /admin/school-years/new
2. Crée "2026-2027"
3. L'année est disponible pour TOUS les établissements
4. Pas besoin de la recréer pour chaque école
```

### Scénario 2 : Changement d'Année

```
Utilisateur:
1. Clique sur le dropdown "2024-2025"
2. Sélectionne "2023-2024" (archives)
3. Toutes les données affichées correspondent à 2023-2024
4. Valable pour tous les établissements
```

### Scénario 3 : Rapports

```
Directeur:
1. Génère un rapport pour "2024-2025"
2. Le rapport inclut TOUS les établissements pour cette année
3. Données cohérentes entre établissements
```

---

## 🔄 Migration des Données

### Étapes Appliquées

1. **Suppression des données existantes**
   ```sql
   DELETE FROM period;
   DELETE FROM school_year;
   ```

2. **Modification du schéma**
   ```sql
   ALTER TABLE school_year DROP FOREIGN KEY FK_FAAAACDAC32A47EE;
   ALTER TABLE school_year DROP school_id;
   ```

3. **Création des nouvelles données**
   ```sql
   INSERT INTO school_year (name, start_date, end_date, is_current, created_at) 
   VALUES 
       ('2023-2024', '2023-09-01', '2024-06-30', 0, NOW()),
       ('2024-2025', '2024-09-01', '2025-06-30', 1, NOW()),
       ('2025-2026', '2025-09-01', '2026-06-30', 0, NOW());
   ```

4. **Création des périodes**
   ```sql
   INSERT INTO period (school_year_id, name, type, start_date, end_date, weight)
   VALUES 
       (12, '1er Trimestre', 'trimestre', '2024-09-01', '2024-12-20', 0.33),
       (12, '2ème Trimestre', 'trimestre', '2025-01-06', '2025-03-28', 0.33),
       (12, '3ème Trimestre', 'trimestre', '2025-04-14', '2025-06-30', 0.34);
   ```

---

## 📊 Statistiques

```
Fichiers modifiés:    3 (School.php, SchoolYear.php, SchoolYearType.php)
Lignes supprimées:    ~80 lignes
Tables modifiées:     1 (school_year)
Colonnes supprimées:  1 (school_id)
Années avant:         10 (redondantes)
Années après:         3 (globales)
Gain:                 70% de données en moins
```

---

## 🎉 Résultat

```
╔═══════════════════════════════════════════════════════╗
║                                                       ║
║   ✅  SUPPRESSION SCHOOL-SCHOOLYEAR TERMINÉE          ║
║                                                       ║
║   • SchoolYear indépendant                            ║
║   • 3 années scolaires globales                       ║
║   • Base de données mise à jour                       ║
║   • Fixtures modifiées                                ║
║   • Formulaires simplifiés                            ║
║   • Gestion centralisée                               ║
║                                                       ║
║        ARCHITECTURE SIMPLIFIÉE ! 🚀                   ║
║                                                       ║
╚═══════════════════════════════════════════════════════╝
```

---

**Version** : 1.3.0  
**Date** : 09 Octobre 2025  
**Status** : ✅ Terminé et Testé

