# 🏗️ Architecture du Système EDU-SCHOOL

## 📐 Architecture Globale

### Modèle MVC (Model-View-Controller)

```
┌─────────────┐
│   Client    │
│  (Browser)  │
└──────┬──────┘
       │
       ▼
┌─────────────────────────────────┐
│        SYMFONY FRAMEWORK        │
│  ┌──────────────────────────┐  │
│  │      CONTROLLER          │  │
│  │   - Gestion requêtes     │  │
│  │   - Logique contrôle     │  │
│  │   - Validation           │  │
│  └───────┬──────────────────┘  │
│          │                      │
│  ┌───────▼──────────────────┐  │
│  │       SERVICE            │  │
│  │   - Logique métier       │  │
│  │   - Règles business      │  │
│  │   - Traitement données   │  │
│  └───────┬──────────────────┘  │
│          │                      │
│  ┌───────▼──────────────────┐  │
│  │      REPOSITORY          │  │
│  │   - Accès données        │  │
│  │   - Requêtes complexes   │  │
│  └───────┬──────────────────┘  │
│          │                      │
│  ┌───────▼──────────────────┐  │
│  │       ENTITY             │  │
│  │   - Modèles données      │  │
│  │   - Mapping ORM          │  │
│  └───────┬──────────────────┘  │
│          │                      │
└──────────┼──────────────────────┘
           │
           ▼
    ┌─────────────┐
    │   DATABASE  │
    │   (MySQL)   │
    └─────────────┘
```

## 🎯 Couches de l'application

### 1. Couche Présentation (View)
**Technologie** : Twig Templates

**Responsabilités** :
- Rendu HTML des pages
- Présentation des données
- Interface utilisateur
- Formulaires interactifs

**Emplacement** : `/templates`

```
templates/
├── base.html.twig          # Template de base
├── security/               # Authentification
├── admin/                  # Interface admin
├── teacher/                # Interface enseignant
├── student/                # Interface étudiant
├── parent/                 # Interface parent
└── components/             # Composants réutilisables
```

### 2. Couche Contrôleur (Controller)
**Technologie** : Symfony Controllers

**Responsabilités** :
- Traitement des requêtes HTTP
- Validation des entrées
- Gestion des formulaires
- Redirection et réponses

**Emplacement** : `/src/Controller`

```php
// Exemple de contrôleur
#[Route('/students', name: 'app_student_')]
class StudentController extends AbstractController
{
    #[Route('/', name: 'list')]
    public function list(StudentRepository $repo): Response
    {
        $students = $repo->findAll();
        return $this->render('student/list.html.twig', [
            'students' => $students
        ]);
    }
}
```

### 3. Couche Service (Business Logic)
**Technologie** : Services Symfony

**Responsabilités** :
- Logique métier
- Règles de gestion
- Calculs complexes
- Orchestration

**Emplacement** : `/src/Service`

```php
// Exemple de service
class GradeCalculatorService
{
    public function calculateAverage(Student $student, Period $period): float
    {
        // Logique de calcul de moyenne
    }
    
    public function generateBulletin(Student $student): PDF
    {
        // Génération du bulletin
    }
}
```

### 4. Couche Repository (Data Access)
**Technologie** : Doctrine Repositories

**Responsabilités** :
- Accès aux données
- Requêtes personnalisées
- Optimisation des requêtes
- Agrégation de données

**Emplacement** : `/src/Repository`

```php
class StudentRepository extends ServiceEntityRepository
{
    public function findByClassroom(Classroom $classroom): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.classroom = :classroom')
            ->setParameter('classroom', $classroom)
            ->orderBy('s.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
```

### 5. Couche Entité (Model)
**Technologie** : Doctrine Entities

**Responsabilités** :
- Représentation des données
- Mapping ORM
- Relations entre entités
- Validation des données

**Emplacement** : `/src/Entity`

```php
#[ORM\Entity(repositoryClass: StudentRepository::class)]
class Student
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $firstName = null;

    #[ORM\ManyToOne(targetEntity: Classroom::class)]
    private ?Classroom $classroom = null;
}
```

## 🔄 Flux de données

### Flux de requête typique

```
1. REQUÊTE HTTP
   ↓
2. ROUTEUR (routing.yaml)
   ↓
3. FIREWALL SÉCURITÉ (security.yaml)
   ↓
4. CONTRÔLEUR (Controller)
   ↓
5. SERVICE (Business Logic)
   ↓
6. REPOSITORY (Data Access)
   ↓
7. ENTITÉ (Model)
   ↓
8. BASE DE DONNÉES
   ↓
9. ENTITÉ (Model)
   ↓
10. SERVICE (Processing)
    ↓
11. CONTRÔLEUR (Response)
    ↓
12. VUE TWIG (Rendering)
    ↓
13. RÉPONSE HTTP
```

## 🗄️ Architecture de la base de données

### Schéma relationnel principal

```
┌─────────────┐
│   School    │ (Établissement)
└──────┬──────┘
       │ 1:N
       ▼
┌─────────────┐
│  SchoolYear │ (Année scolaire)
└──────┬──────┘
       │ 1:N
       ▼
┌─────────────┐      ┌─────────────┐
│  Classroom  │◄─────┤   Level     │ (Niveau)
└──────┬──────┘  N:1 └─────────────┘
       │ 1:N
       ▼
┌─────────────┐      ┌─────────────┐
│   Student   │─────►│    User     │
└──────┬──────┘  1:1 └─────────────┘
       │ N:M
       ▼
┌─────────────┐
│   Subject   │ (Matière)
└──────┬──────┘
       │ 1:N
       ▼
┌─────────────┐
│    Grade    │ (Note)
└─────────────┘
```

### Tables principales

#### Établissement et structure
- `school` - Établissements scolaires
- `school_year` - Années scolaires
- `level` - Niveaux (Maternelle, Primaire, etc.)
- `classroom` - Classes
- `section` - Sections/Filières

#### Utilisateurs
- `user` - Utilisateurs du système
- `student` - Élèves/Étudiants
- `teacher` - Enseignants
- `parent` - Parents
- `staff` - Personnel administratif

#### Académique
- `subject` - Matières
- `course` - Cours
- `schedule` - Emploi du temps
- `grade` - Notes
- `period` - Périodes (Trimestre, Semestre)
- `attendance` - Présences/Absences

#### Gestion
- `payment` - Paiements
- `fee` - Frais de scolarité
- `document` - Documents
- `notification` - Notifications

## 🔐 Architecture de sécurité

### Authentification en couches

```
┌──────────────────────────────┐
│   Login Form                 │
└──────────────┬───────────────┘
               │
               ▼
┌──────────────────────────────┐
│   MainAuthenticator          │
│   - Validation credentials   │
│   - CSRF Token check         │
└──────────────┬───────────────┘
               │
               ▼
┌──────────────────────────────┐
│   User Provider              │
│   - Load user from DB        │
└──────────────┬───────────────┘
               │
               ▼
┌──────────────────────────────┐
│   Password Verification      │
│   - Hash comparison          │
└──────────────┬───────────────┘
               │
               ▼
┌──────────────────────────────┐
│   Role Hierarchy             │
│   - Check permissions        │
└──────────────┬───────────────┘
               │
               ▼
┌──────────────────────────────┐
│   Access Control             │
│   - Grant/Deny access        │
└──────────────────────────────┘
```

### Firewall Configuration

```yaml
firewalls:
    main:
        lazy: true
        provider: app_user_provider
        custom_authenticator: App\Security\MainAuthenticator
        logout: ~
        remember_me:
            secret: '%env(APP_SECRET)%'
            lifetime: 31536000
        login_throttling:
            max_attempts: 3
            interval: '2 minutes'
```

## 📡 Architecture API

### API RESTful

```
┌─────────────┐
│   Client    │
└──────┬──────┘
       │ HTTP Request (JSON)
       ▼
┌──────────────────────────────┐
│   API Controller             │
│   /api/*                     │
└──────┬───────────────────────┘
       │
       ▼
┌──────────────────────────────┐
│   Serializer                 │
│   - JSON ↔ Entity            │
└──────┬───────────────────────┘
       │
       ▼
┌──────────────────────────────┐
│   Service Layer              │
└──────┬───────────────────────┘
       │
       ▼
┌──────────────────────────────┐
│   Repository                 │
└──────┬───────────────────────┘
       │
       ▼
┌──────────────────────────────┐
│   Database                   │
└──────────────────────────────┘
```

### Endpoints Structure

```
/api/
├── /students          # Gestion des élèves
├── /teachers          # Gestion des enseignants
├── /classrooms        # Gestion des classes
├── /subjects          # Gestion des matières
├── /grades            # Gestion des notes
├── /attendance        # Gestion des absences
├── /payments          # Gestion des paiements
└── /statistics        # Statistiques (PUBLIC)
```

## ⚡ Performance et Optimisation

### Stratégies de cache

1. **Cache HTTP**
   - Cache navigateur
   - Headers Cache-Control
   - ETags

2. **Cache applicatif**
   - Symfony Cache
   - Cache des requêtes Doctrine
   - Cache des templates Twig

3. **Cache base de données**
   - Query Result Cache
   - Metadata Cache

### Optimisation des requêtes

```php
// Eager Loading pour éviter N+1
$students = $this->studentRepository
    ->createQueryBuilder('s')
    ->leftJoin('s.classroom', 'c')
    ->leftJoin('s.grades', 'g')
    ->addSelect('c', 'g')
    ->getQuery()
    ->getResult();
```

## 📊 Patterns utilisés

### 1. Repository Pattern
- Abstraction de l'accès aux données
- Centralisation des requêtes

### 2. Service Layer Pattern
- Logique métier isolée
- Réutilisabilité du code

### 3. Dependency Injection
- Inversion de contrôle
- Testabilité

### 4. Event Dispatcher
- Découplage des composants
- Extensibilité

### 5. Factory Pattern
- Création d'objets complexes
- Centralisation de la logique de création

## 🔄 Gestion des événements

```php
// Event Subscriber
class StudentSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            'student.created' => 'onStudentCreated',
            'student.updated' => 'onStudentUpdated',
        ];
    }

    public function onStudentCreated(StudentEvent $event)
    {
        // Send welcome email
        // Create default documents
        // Log action
    }
}
```

## 📱 Architecture responsive

### Breakpoints
- Mobile: < 768px
- Tablet: 768px - 1024px
- Desktop: > 1024px

### Composants adaptatifs
- Navigation responsive
- Tables avec scroll horizontal
- Formulaires adaptés au touch

## 🔌 Intégrations externes

### Services tiers
- **Email** : SMTP / API email
- **SMS** : API SMS pour notifications
- **Paiement** : Gateway de paiement
- **Cloud Storage** : Pour documents
- **Backup** : Sauvegarde automatique

---

**Document maintenu par** : Équipe Architecture EDU-SCHOOL  
**Dernière révision** : Octobre 2025

