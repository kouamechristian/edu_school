# ⏰ Intégration TimeSlot et Filtrage Course - EDU-SCHOOL

## ✅ Fonctionnalités Implémentées !

```
╔════════════════════════════════════════════════════════════╗
║                                                            ║
║    ✅  PLAGES HORAIRES PAR ÉTABLISSEMENT                   ║
║                                                            ║
║    • Entité TimeSlot créée                                 ║
║    • Course lié à TimeSlot                                 ║
║    • Horaires personnalisés par école                      ║
║    • Filtrage complet dans CourseType                      ║
║    • Emploi du temps dynamique                             ║
║                                                            ║
╚════════════════════════════════════════════════════════════╝
```

---

## 🎯 Objectif

Permettre à **chaque établissement** d'enregistrer ses **propres plages horaires** et utiliser ces horaires dans les emplois du temps au lieu de créneaux fixes.

---

## 🔧 Modifications Apportées

### 1. Entité TimeSlot (Nouvelle)

**Fichier** : `src/Entity/TimeSlot.php`

```php
class TimeSlot
{
    private ?int $id;
    private ?School $school;           // Établissement
    private ?string $name;             // "1ère heure", "Récréation"
    private ?\DateTimeInterface $startTime;  // 08:00
    private ?\DateTimeInterface $endTime;    // 09:00
    private ?string $type;             // cours|pause|dejeuner|recreation
    private ?int $orderNumber;         // Ordre d'affichage
    private ?string $color;            // Couleur
    private bool $isActive;
    
    // Méthodes utiles
    public function getTimeRange(): string     // "08:00 - 09:00"
    public function getDuration(): ?int        // 60 minutes
    public function getTypeLabel(): string     // "Cours", "Pause", etc.
}
```

**Types disponibles** :
- `cours` : Plage de cours
- `pause` : Pause courte
- `dejeuner` : Pause déjeuner
- `recreation` : Récréation

---

### 2. Entité Course (Modifiée)

**Avant** ❌ :
```php
private ?\DateTimeInterface $startTime;
private ?\DateTimeInterface $endTime;
```

**Après** ✅ :
```php
private ?TimeSlot $timeSlot;  // Lien vers la plage horaire

// Méthodes de compatibilité
public function getStartTime() { return $this->timeSlot?->getStartTime(); }
public function getEndTime() { return $this->timeSlot?->getEndTime(); }
public function getDuration() { return $this->timeSlot?->getDuration(); }
```

**Migration SQL** :
```sql
ALTER TABLE course 
ADD time_slot_id INT NOT NULL,
DROP start_time,
DROP end_time;

ALTER TABLE course 
ADD CONSTRAINT FK_169E6FB9D62B0FA 
FOREIGN KEY (time_slot_id) REFERENCES time_slot (id);
```

---

### 3. Formulaire CourseType (Modifié)

**Avant** ❌ :
```php
->add('startTime', TimeType::class, [...])
->add('endTime', TimeType::class, [...])
```

**Après** ✅ :
```php
->add('timeSlot', EntityType::class, [
    'label' => 'Plage horaire',
    'class' => TimeSlot::class,
    'choice_label' => function(TimeSlot $timeSlot) {
        return sprintf('%s (%s)', $timeSlot->getName(), $timeSlot->getTimeRange());
    },
    'query_builder' => function ($repository) use ($schoolId) {
        $qb = $repository->createQueryBuilder('t')
            ->where('t.isActive = :active')
            ->andWhere('t.type = :type')      // Seulement type "cours"
            ->setParameter('active', true)
            ->setParameter('type', 'cours')
            ->orderBy('t.orderNumber', 'ASC');
        
        // Filtrer par l'établissement
        if ($schoolId) {
            $qb->andWhere('t.school = :school')
               ->setParameter('school', $schoolId);
        }
        
        return $qb;
    },
])
```

**Filtrage complet ajouté** :
```php
// Classroom - Filtré par établissement
->add('classroom', [...
    'query_builder' => function ($repository) use ($schoolId) {
        // WHERE c.school = :school
    }
])

// Subject - Filtré par établissement
->add('subject', [...
    'query_builder' => function ($repository) use ($schoolId) {
        // WHERE s.school = :school
    }
])

// Teacher - Filtré par établissement
->add('teacher', [...
    'query_builder' => function ($repository) use ($schoolId) {
        // INNER JOIN user_school WHERE sc.id = :school
    }
])

// TimeSlot - Filtré par établissement
->add('timeSlot', [...
    'query_builder' => function ($repository) use ($schoolId) {
        // WHERE t.school = :school AND t.type = 'cours'
    }
])
```

---

### 4. Template schedule.html.twig (Modifié)

**Avant** ❌ :
```twig
{% set time_slots = [
    {'start': '08:00', 'end': '09:00'},
    {'start': '09:00', 'end': '10:00'},
    ...
] %}
```

**Après** ✅ :
```twig
{% for timeSlot in time_slots %}
    <tr>
        <td>
            {{ timeSlot.name }}<br>
            {{ timeSlot.startTime|date('H:i') }} - {{ timeSlot.endTime|date('H:i') }}
        </td>
        
        {% if timeSlot.type == 'cours' %}
            {# Afficher les cours pour cette plage #}
            {% for day in days %}
                {% for course in schedule[day] %}
                    {% if course.timeSlot.id == timeSlot.id %}
                        {# Afficher le cours #}
                    {% endif %}
                {% endfor %}
            {% endfor %}
        {% else %}
            {# Pause, Déjeuner, Récréation #}
            <td colspan="6">{{ timeSlot.typeLabel }}</td>
        {% endif %}
    </tr>
{% endfor %}
```

**Avantages** :
- ✅ Utilise les **vraies plages horaires** de la base de données
- ✅ Affiche les **pauses** automatiquement
- ✅ Adapté à **chaque établissement**

---

### 5. Contrôleur CourseController (Modifié)

**Ajout** dans la méthode `schedule()` :
```php
public function schedule(
    Classroom $classroom, 
    CourseRepository $courseRepository, 
    TimeSlotRepository $timeSlotRepository  // Ajouté
): Response {
    $schedule = $courseRepository->findScheduleByClassroom($classroom->getId());
    
    // Récupérer les plages horaires de l'établissement
    $timeSlots = $timeSlotRepository->findBySchool($classroom->getSchool()->getId());

    return $this->render('course/schedule.html.twig', [
        'classroom' => $classroom,
        'schedule' => $schedule,
        'time_slots' => $timeSlots,  // Ajouté
    ]);
}
```

---

### 6. Repository CourseRepository (Modifié)

**Tri mis à jour** :
```php
// Avant
->orderBy('c.startTime', 'ASC')

// Après
->leftJoin('c.timeSlot', 't')
->orderBy('t.orderNumber', 'ASC')
```

Toutes les méthodes (`findActive`, `findByClassroom`, `findByTeacher`, `findByDayOfWeek`) utilisent maintenant `timeSlot.orderNumber` pour le tri.

---

### 7. Navigation (Mise à jour)

**Menu Sidebar** :
```
Académique
  ├── Classes
  ├── Matières
  ├── Plages Horaires      ← NOUVEAU
  └── Emplois du Temps
```

**Route** : `/admin/time-slots`

---

## 📊 Données de Test

### École Maternelle (5 plages)

| Ordre | Nom | Type | Horaire | Durée |
|-------|-----|------|---------|-------|
| 1 | 1ère heure | Cours | 08:00-09:00 | 60 min |
| 2 | 2ème heure | Cours | 09:00-10:00 | 60 min |
| 3 | Récréation | Récréation | 10:00-10:15 | 15 min |
| 4 | 3ème heure | Cours | 10:15-11:15 | 60 min |
| 5 | 4ème heure | Cours | 11:15-12:15 | 60 min |

### École Primaire (10 plages)

| Ordre | Nom | Type | Horaire | Durée |
|-------|-----|------|---------|-------|
| 1 | 1ère heure | Cours | 08:00-09:00 | 60 min |
| 2 | 2ème heure | Cours | 09:00-10:00 | 60 min |
| 3 | Récréation | Récréation | 10:00-10:15 | 15 min |
| 4 | 3ème heure | Cours | 10:15-11:15 | 60 min |
| 5 | 4ème heure | Cours | 11:15-12:15 | 60 min |
| 6 | Déjeuner | Déjeuner | 12:15-13:45 | 90 min |
| 7 | 5ème heure | Cours | 13:45-14:45 | 60 min |
| 8 | 6ème heure | Cours | 14:45-15:45 | 60 min |
| 9 | Pause | Pause | 15:45-16:00 | 15 min |
| 10 | 7ème heure | Cours | 16:00-17:00 | 60 min |

---

## 🎨 Interface

### Page Plages Horaires

```
┌──────────────────────────────────────────────────────────────────┐
│ ⏰ Gestion des Plages Horaires    [+ Nouvelle Plage Horaire]    │
│ École Maternelle Les Petits Bambins                              │
├──────────────────────────────────────────────────────────────────┤
│ Ordre | Nom        | Type       | Horaire   | Durée  | Actions  │
├──────────────────────────────────────────────────────────────────┤
│  1    | 1ère heure | [Cours]    | 08:00-09:00| 60 min | 👁️ ✏️ 🗑️ │
│  2    | 2ème heure | [Cours]    | 09:00-10:00| 60 min | 👁️ ✏️ 🗑️ │
│  3    | Récréation | [Récréa.]  | 10:00-10:15| 15 min | 👁️ ✏️ 🗑️ │
│  4    | 3ème heure | [Cours]    | 10:15-11:15| 60 min | 👁️ ✏️ 🗑️ │
│  5    | 4ème heure | [Cours]    | 11:15-12:15| 60 min | 👁️ ✏️ 🗑️ │
└──────────────────────────────────────────────────────────────────┘
```

### Formulaire Nouveau Cours

```
┌────────────────────────────────────────────┐
│ 📅 Nouveau Cours                           │
├────────────────────────────────────────────┤
│                                            │
│ Classe: [Petite Section A ▼]              │  ← Filtré par école
│                                            │
│ Matière: [Langage oral ▼]                 │  ← Filtré par école
│         Options: Langage, Art, Sport, ...  │
│                                            │
│ Enseignant: [Jean MARTIN ▼]               │  ← Filtré par école
│                                            │
│ Jour: [Lundi ▼]                            │
│                                            │
│ Plage horaire: [1ère heure (08:00-09:00)▼]│  ← Horaires de l'école
│               Options:                      │
│               - 1ère heure (08:00-09:00)   │
│               - 2ème heure (09:00-10:00)   │
│               - 3ème heure (10:15-11:15)   │
│               - 4ème heure (11:15-12:15)   │
│                                            │
│ Salle: [Salle 1]                           │
│                                            │
│ [Retour]                  [Enregistrer]    │
└────────────────────────────────────────────┘
```

### Emploi du Temps avec TimeSlots

```
┌───────────────────────────────────────────────────────────────────┐
│ 📅 Emploi du Temps - Petite Section A                            │
├───────────────────────────────────────────────────────────────────┤
│ Horaire           │ Lundi          │ Mardi          │ Mercredi... │
├───────────────────┼────────────────┼────────────────┼─────────────┤
│ [1ère heure]      │ 🔴 Langage oral│ 🟡 Act. phys.  │             │
│ 08:00             │ Jean MARTIN    │ Jean MARTIN    │             │
│ 09:00             │ Salle 1        │ Salle 1        │             │
├───────────────────┼────────────────┼────────────────┼─────────────┤
│ [2ème heure]      │ 🔴 Langage oral│ 🟢 Découverte  │             │
│ 09:00             │ Jean MARTIN    │ Jean MARTIN    │             │
│ 10:00             │ Salle 1        │ Salle 1        │             │
├───────────────────┴────────────────┴────────────────┴─────────────┤
│ [Récréation] 10:00-10:15                                          │
├───────────────────┬────────────────┬────────────────┬─────────────┤
│ [3ème heure]      │ 🔵 Act. artist.│                │             │
│ 10:15             │ Jean MARTIN    │                │             │
│ 11:15             │ Salle 1        │                │             │
└───────────────────┴────────────────┴────────────────┴─────────────┘

Pause et Récréation s'affichent automatiquement
```

---

## 🔄 Flux Utilisateur

### Scénario : Création de Cours

```
1. User connecté
   └─> Sélectionne "École Maternelle"
        └─> Session stocke: school_id = 1

2. Aller sur /admin/courses/new
   └─> Formulaire s'affiche

3. Champ "Classe" :
   └─> Query filtrée: WHERE classroom.school_id = 1
        └─> Affiche: Petite Section A, Moyenne Section A, Grande Section A
        └─> Masque: Classes du Primaire

4. Champ "Matière" :
   └─> Query filtrée: WHERE subject.school_id = 1
        └─> Affiche: Langage oral, Activités artistiques, ...
        └─> Masque: Français, Mathématiques (primaire)

5. Champ "Enseignant" :
   └─> Query filtrée: INNER JOIN user_school WHERE school_id = 1
        └─> Affiche: Jean MARTIN (enseignant maternelle)
        └─> Masque: Sophie DUPRÉ (enseignante primaire)

6. Champ "Plage horaire" :
   └─> Query filtrée: WHERE timeslot.school_id = 1 AND type = 'cours'
        └─> Affiche: 1ère heure (08:00-09:00), 2ème heure (09:00-10:00), ...
        └─> Masque: Plages du Primaire (avec déjeuner)

7. Remplir et enregistrer
   └─> Cours créé avec les données de l'établissement
```

---

## 📊 Comparaison Avant/Après

### Avant (Horaires Fixes) ❌

```
Emploi du temps identique pour tous:
├── 08:00-09:00
├── 09:00-10:00
├── 10:00-11:00
├── 11:00-12:00
└── ...

Problèmes:
- Pas de pauses affichées
- Horaires non adaptés
- Déjeuner au même moment partout
```

### Après (Horaires Personnalisés) ✅

```
École Maternelle:
├── 08:00-09:00  (1ère heure)
├── 09:00-10:00  (2ème heure)
├── 10:00-10:15  (Récréation)    ← Pause affichée
├── 10:15-11:15  (3ème heure)
└── 11:15-12:15  (4ème heure)

École Primaire:
├── 08:00-09:00  (1ère heure)
├── ...
├── 12:15-13:45  (Déjeuner)      ← Pause déjeuner
├── 13:45-14:45  (5ème heure)
└── ...

Avantages:
✓ Horaires personnalisés
✓ Pauses visibles
✓ Chaque école sa configuration
```

---

## 🧪 Tests de Validation

### Test 1 : Créer des Plages Horaires

```bash
# 1. Sélectionner "École Maternelle"
# 2. Aller sur /admin/time-slots
# ✅ Voir les 5 plages horaires
# 3. Cliquer "Nouvelle Plage Horaire"
# 4. Créer "Pause goûter" 16:00-16:15
# ✅ Plage créée et visible
```

### Test 2 : Formulaire Cours Filtré

```bash
# 1. Sélectionner "École Maternelle"
# 2. Aller sur /admin/courses/new
# ✅ Classes: Uniquement maternelle
# ✅ Matières: Uniquement maternelle
# ✅ Enseignants: Uniquement Jean MARTIN
# ✅ Plages horaires: 1ère, 2ème, 3ème, 4ème heure (pas pause)
```

### Test 3 : Emploi du Temps

```bash
# 1. Créer des cours avec TimeSlot
# 2. Aller sur /admin/courses/schedule/1
# ✅ Grille affiche les TimeSlots de l'école
# ✅ Cours affichés aux bonnes plages
# ✅ Récréation visible en grisé
```

### Test 4 : Changement d'Établissement

```bash
# 1. École Maternelle: 5 plages horaires
# 2. Changer pour "École Primaire"
# 3. Aller sur /admin/time-slots
# ✅ 10 plages horaires (différentes)
# 4. Créer un cours
# ✅ Plages du Primaire uniquement
```

---

## 📈 Statistiques

```
Fichiers créés:          8
- TimeSlot.php (222 lignes)
- TimeSlotRepository.php (74 lignes)
- TimeSlotType.php (90 lignes)
- TimeSlotController.php (132 lignes)
- time_slot/index.html.twig (115 lignes)
- time_slot/new.html.twig (68 lignes)
- time_slot/edit.html.twig (3 lignes)
- time_slot/show.html.twig (72 lignes)

Fichiers modifiés:       5
- Course.php
- CourseType.php
- CourseController.php
- CourseRepository.php
- course/schedule.html.twig
- base.html.twig

Routes créées:           6
Tables créées:           1 (time_slot)
Enregistrements test:    15 (5 maternelle + 10 primaire)
```

---

## 🎯 Avantages

### ✅ Flexibilité

```
Chaque école définit:
- Horaires de début/fin
- Durée des cours
- Pauses personnalisées
- Nombre de créneaux
```

### ✅ Clarté

```
Emploi du temps montre:
- Les cours
- Les pauses (récréation, déjeuner)
- Tout visuellement organisé
```

### ✅ Maintenance

```
Modifier les horaires:
- Aller sur /admin/time-slots
- Modifier une plage
- Tous les cours se mettent à jour automatiquement
```

### ✅ Performance

```
Avant: Boucle sur 10 créneaux fixes
Après: Boucle sur les créneaux réels (5-10 selon école)
```

---

## 💡 Cas d'Usage

### École Maternelle

```
Horaires adaptés aux petits:
- 4 heures de cours (08:00-12:15)
- Récréation de 15 min
- Pas de cours l'après-midi
- Sieste possible après 12:15
```

### École Primaire

```
Journée complète:
- 7 heures de cours
- Récréation matin
- Déjeuner 1h30
- Pause après-midi
- Cours jusqu'à 17:00
```

### Université

```
Créneaux flexibles:
- Cours de 2h
- Pauses entre chaque
- TD/TP avec horaires spéciaux
```

---

## 🚀 Utilisation

### Créer des Plages Horaires

```
1. Sélectionner l'établissement
2. /admin/time-slots
3. Cliquer "Nouvelle Plage Horaire"
4. Remplir:
   - Nom: "1ère heure"
   - Type: Cours
   - Début: 08:00
   - Fin: 09:00
   - Ordre: 1
   - Couleur: #4e73df
5. Enregistrer
6. ✅ Disponible pour les cours
```

### Créer un Cours avec TimeSlot

```
1. /admin/courses/new
2. Sélectionner:
   - Classe: Petite Section A
   - Matière: Langage oral
   - Enseignant: Jean MARTIN
   - Jour: Lundi
   - Plage horaire: [1ère heure (08:00-09:00)]  ← TimeSlot
   - Salle: Salle 1
3. Enregistrer
4. ✅ Cours créé avec horaires de l'école
```

---

## 🎉 Résultat Final

```
╔════════════════════════════════════════════════════════════╗
║                                                            ║
║   ✅  SYSTÈME DE PLAGES HORAIRES COMPLET                   ║
║                                                            ║
║   • TimeSlot entité créée                                  ║
║   • Course lié à TimeSlot                                  ║
║   • CRUD TimeSlot complet                                  ║
║   • Filtrage par établissement                             ║
║   • Emploi du temps dynamique                              ║
║   • Pauses automatiques                                    ║
║   • Interface professionnelle                              ║
║                                                            ║
║   École Maternelle: 5 plages                               ║
║   École Primaire: 10 plages                                ║
║                                                            ║
║        HORAIRES PERSONNALISÉS ! 🚀                         ║
║                                                            ║
╚════════════════════════════════════════════════════════════╝
```

---

**Version** : 2.1.0  
**Date** : 10 Octobre 2025  
**Status** : ✅ Terminé et Testé  
**Impact** : Emplois du temps adaptés à chaque établissement

