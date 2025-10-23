# 🔗 Liaison User ↔ SchoolGroup - EDU-SCHOOL

## ✅ Fonctionnalité Implémentée !

```
╔═══════════════════════════════════════════════════════╗
║                                                       ║
║    ✅  FILTRAGE DYNAMIQUE DES ÉTABLISSEMENTS          ║
║                                                       ║
║    • Liaison User → SchoolGroup                       ║
║    • Sélection de groupe dans le formulaire           ║
║    • Filtrage automatique des établissements          ║
║    • JavaScript pour filtrage en temps réel           ║
║    • Affichage du groupe dans les vues                ║
║                                                       ║
╚═══════════════════════════════════════════════════════╝
```

---

## 🎯 Objectif

Lors de la création ou modification d'un utilisateur :
1. Sélectionner un **groupe d'établissements**
2. La liste des **établissements disponibles** se filtre automatiquement
3. Seuls les établissements du groupe sélectionné sont affichés

---

## 📦 Modifications Réalisées

### 1. Base de Données

#### Nouvelle Colonne
```sql
ALTER TABLE user 
ADD school_group_id INT NULL,
ADD FOREIGN KEY (school_group_id) REFERENCES school_group(id);
```

**Relation** : `User` ManyToOne `SchoolGroup`
- Un utilisateur appartient à un groupe (optionnel)
- Un groupe peut avoir plusieurs utilisateurs

---

### 2. Backend (3 fichiers modifiés)

#### A. Entité User (`src/Entity/User.php`)

**Ajout de la propriété** :
```php
#[ORM\ManyToOne]
#[ORM\JoinColumn(nullable: true)]
private ?SchoolGroup $schoolGroup = null;
```

**Getters/Setters** :
```php
public function getSchoolGroup(): ?SchoolGroup
{
    return $this->schoolGroup;
}

public function setSchoolGroup(?SchoolGroup $schoolGroup): static
{
    $this->schoolGroup = $schoolGroup;
    return $this;
}
```

#### B. Formulaire UserType (`src/Form/UserType.php`)

**Import** :
```php
use App\Entity\SchoolGroup;
```

**Champ Groupe** (ajouté avant `schools`) :
```php
->add('schoolGroup', EntityType::class, [
    'label' => 'Groupe d\'établissements',
    'class' => SchoolGroup::class,
    'choice_label' => 'name',
    'attr' => ['class' => 'form-select', 'id' => 'user_schoolGroup'],
    'placeholder' => 'Sélectionnez un groupe',
    'required' => false,
    'query_builder' => function ($repository) {
        return $repository->createQueryBuilder('sg')
            ->where('sg.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('sg.name', 'ASC');
    },
    'help' => 'Sélectionnez d\'abord un groupe pour filtrer les établissements',
])
```

**Champ Schools modifié** :
```php
->add('schools', EntityType::class, [
    // ... autres options
    'choice_attr' => function(School $school) {
        return [
            'data-group-id' => $school->getSchoolGroup() ? 
                               $school->getSchoolGroup()->getId() : '',
        ];
    },
    'attr' => ['class' => 'form-select', 'size' => 5, 'id' => 'user_schools'],
    'query_builder' => function ($repository) {
        return $repository->createQueryBuilder('s')
            ->leftJoin('s.schoolGroup', 'sg')
            ->where('s.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('sg.name', 'ASC')
            ->addOrderBy('s.name', 'ASC');
    },
])
```

**Points clés** :
- `choice_attr` : Ajoute l'attribut `data-group-id` à chaque option
- `id` : Identifiants pour le JavaScript
- Les écoles sont triées par groupe puis par nom

#### C. Fixtures (`src/DataFixtures/Module2Fixtures.php`)

**Import** :
```php
use App\Entity\SchoolGroup;
```

**Récupération des groupes** :
```php
$groups = $manager->getRepository(SchoolGroup::class)->findAll();
```

**Liaison dans createDirecteurs** :
```php
if ($schools[$index]->getSchoolGroup()) {
    $user->setSchoolGroup($schools[$index]->getSchoolGroup());
}
```

---

### 3. Frontend (3 fichiers modifiés)

#### A. Template new.html.twig (`templates/user/new.html.twig`)

**Ajout du champ** :
```twig
<div class="mb-3">
    {{ form_row(form.schoolGroup) }}
</div>

<div class="mb-3">
    {{ form_row(form.schools) }}
</div>
```

**JavaScript de filtrage** :
```javascript
document.addEventListener('DOMContentLoaded', function() {
    const groupSelect = document.getElementById('user_schoolGroup');
    const schoolsSelect = document.getElementById('user_schools');
    
    if (groupSelect && schoolsSelect) {
        // Stocker toutes les options
        const allOptions = Array.from(schoolsSelect.options);
        
        // Fonction pour filtrer les établissements
        function filterSchools() {
            const selectedGroupId = groupSelect.value;
            
            // Réinitialiser les options
            schoolsSelect.innerHTML = '';
            
            // Filtrer et ajouter les options
            allOptions.forEach(option => {
                const groupId = option.getAttribute('data-group-id');
                
                // Si aucun groupe sélectionné, afficher tous
                // Si groupe sélectionné, afficher seulement ceux du groupe
                if (!selectedGroupId || groupId === selectedGroupId) {
                    schoolsSelect.appendChild(option.cloneNode(true));
                }
            });
            
            // Afficher un message si aucune école
            if (schoolsSelect.options.length === 0) {
                const emptyOption = document.createElement('option');
                emptyOption.text = selectedGroupId ? 
                    'Aucun établissement dans ce groupe' : 
                    'Aucun établissement disponible';
                emptyOption.disabled = true;
                schoolsSelect.appendChild(emptyOption);
            }
        }
        
        // Écouter les changements de groupe
        groupSelect.addEventListener('change', filterSchools);
        
        // Filtrer au chargement si un groupe est déjà sélectionné
        if (groupSelect.value) {
            filterSchools();
        }
    }
});
```

**Fonctionnement** :
1. Au chargement de la page, stocke toutes les options d'établissements
2. Quand l'utilisateur sélectionne un groupe :
   - Vide la liste des établissements
   - Parcourt toutes les options stockées
   - N'affiche que celles qui ont le `data-group-id` correspondant
3. Si aucun groupe sélectionné, affiche tous les établissements

#### B. Template edit.html.twig (`templates/user/edit.html.twig`)

**Héritage** :
```twig
{% extends 'user/new.html.twig' %}
```

Le JavaScript est automatiquement disponible car edit étend new.

#### C. Template show.html.twig (`templates/user/show.html.twig`)

**Affichage du groupe** :
```twig
{# Groupe d'établissements #}
{% if user.schoolGroup %}
<h5 class="mb-3"><i class="fas fa-sitemap me-2"></i> Groupe d'Établissements</h5>
<div class="mb-3">
    <a href="{{ path('admin_school_group_show', {'id': user.schoolGroup.id}) }}" 
       class="badge bg-info">
        {{ user.schoolGroup.name }}
    </a>
</div>
<hr>
{% endif %}
```

#### D. Template index.html.twig (`templates/user/index.html.twig`)

**Colonne Groupe ajoutée** :
```twig
<thead>
    <tr>
        <th>Utilisateur</th>
        <th>Email</th>
        <th>Type</th>
        <th>Groupe</th>          ← NOUVEAU
        <th>Établissement(s)</th>
        ...
    </tr>
</thead>
```

**Affichage dans tbody** :
```twig
<td>
    {% if user.schoolGroup %}
        <span class="badge bg-info" title="{{ user.schoolGroup.name }}">
            {{ user.schoolGroup.code }}
        </span>
    {% else %}
        <span class="text-muted">-</span>
    {% endif %}
</td>
```

---

## 🔄 Flux Utilisateur

### Création d'un Utilisateur

```
1. Accès : /admin/users/new

2. Formulaire :
   ┌──────────────────────────────────────┐
   │ Nom d'utilisateur: [________]        │
   │ Email: [________________________]    │
   │                                      │
   │ Type: [Enseignant ▼]                 │
   │                                      │
   │ Groupe: [Ens. Fondamental ▼]  ← SÉLECTION
   │         ↓                            │
   │ Établissements:                      │
   │ ┌────────────────────────┐           │
   │ │ École Maternelle       │  ← FILTRÉ │
   │ │ École Primaire         │  ← FILTRÉ │
   │ └────────────────────────┘           │
   │                                      │
   │ [Enregistrer]                        │
   └──────────────────────────────────────┘

3. Comportement JavaScript :
   - onChange du groupe → filterSchools()
   - Cache les écoles des autres groupes
   - Affiche seulement les écoles du groupe sélectionné
```

### Exemple Concret

**Données** :
```
Groupe 1 (Fondamental) :
  ├── École Maternelle
  └── École Primaire

Groupe 2 (Secondaire) :
  ├── Collège
  └── Lycée

Groupe 3 (Supérieur) :
  └── Université
```

**Sans groupe sélectionné** :
```
Établissements:
┌────────────────────────┐
│ École Maternelle       │
│ École Primaire         │
│ Collège                │
│ Lycée                  │
│ Université             │
└────────────────────────┘
```

**Avec "Groupe Fondamental" sélectionné** :
```
Établissements:
┌────────────────────────┐
│ École Maternelle       │
│ École Primaire         │
└────────────────────────┘
```

---

## 🎨 Interface

### Formulaire

```
┌──────────────────────────────────────────────────────┐
│ Type et Permissions                                  │
├──────────────────────────────────────────────────────┤
│                                                      │
│ Type d'utilisateur:                                  │
│ [Enseignant                              ▼]         │
│                                                      │
│ Groupe d'établissements:                             │
│ [Groupe Enseignement Fondamental         ▼]         │
│ ℹ️ Sélectionnez d'abord un groupe pour filtrer      │
│                                                      │
│ Établissement(s):                                    │
│ ┌────────────────────────────────────────┐           │
│ │ École Maternelle Les Petits Bambins    │  Ctrl+   │
│ │ École Primaire Jean Moulin             │  Clic    │
│ │                                        │  pour    │
│ │                                        │  multi-  │
│ │                                        │  sélec.  │
│ └────────────────────────────────────────┘           │
│ ℹ️ Sélectionnez un ou plusieurs établissements      │
│                                                      │
└──────────────────────────────────────────────────────┘
```

### Liste des Utilisateurs

```
┌──────────────────────────────────────────────────────────────────────┐
│ Utilisateur    | Email         | Type    | Groupe  | Établ. |  ...  │
├──────────────────────────────────────────────────────────────────────┤
│ Marie DUPONT   | marie@...     | Direct. | [GRP001]| [MAT001]       │
│ Jean MARTIN    | jean@...      | Enseig. | [GRP001]| [PRI001]       │
│ Sophie DUPRÉ   | sophie@...    | Enseig. | -       | [COL001]       │
└──────────────────────────────────────────────────────────────────────┘
```

### Page de Détails

```
┌────────────────────────────────────────┐
│ 👤 Marie DUPONT                        │
│    @directeur1                         │
│    [Directeur]                         │
├────────────────────────────────────────┤
│                                        │
│ 🏢 Groupe d'Établissements             │
│ [Groupe Enseignement Fondamental]      │
│                                        │
│ 🏫 Établissement(s)                    │
│ [École Maternelle Les Petits Bambins] │
│                                        │
│ 🛡️ Rôles et Permissions                │
│ [ROLE_ADMIN]                           │
│                                        │
└────────────────────────────────────────┘
```

---

## 🔧 Code JavaScript Expliqué

### Structure

```javascript
// 1. Attendre le chargement du DOM
document.addEventListener('DOMContentLoaded', function() {

    // 2. Récupérer les éléments
    const groupSelect = document.getElementById('user_schoolGroup');
    const schoolsSelect = document.getElementById('user_schools');
    
    // 3. Vérifier l'existence
    if (groupSelect && schoolsSelect) {
    
        // 4. Stocker toutes les options initiales
        const allOptions = Array.from(schoolsSelect.options);
        
        // 5. Définir la fonction de filtrage
        function filterSchools() {
            // Récupérer l'ID du groupe sélectionné
            const selectedGroupId = groupSelect.value;
            
            // Vider la liste actuelle
            schoolsSelect.innerHTML = '';
            
            // Filtrer et réafficher
            allOptions.forEach(option => {
                const groupId = option.getAttribute('data-group-id');
                
                if (!selectedGroupId || groupId === selectedGroupId) {
                    schoolsSelect.appendChild(option.cloneNode(true));
                }
            });
            
            // Message si vide
            if (schoolsSelect.options.length === 0) {
                // Créer option désactivée avec message
                const emptyOption = document.createElement('option');
                emptyOption.text = 'Aucun établissement dans ce groupe';
                emptyOption.disabled = true;
                schoolsSelect.appendChild(emptyOption);
            }
        }
        
        // 6. Écouter les changements
        groupSelect.addEventListener('change', filterSchools);
        
        // 7. Filtrer au chargement si déjà sélectionné
        if (groupSelect.value) {
            filterSchools();
        }
    }
});
```

### Pourquoi `cloneNode(true)` ?

```javascript
// ❌ MAUVAIS : Déplace l'élément
schoolsSelect.appendChild(option);

// ✅ BON : Clone l'élément
schoolsSelect.appendChild(option.cloneNode(true));
```

Sans `cloneNode`, l'élément est **déplacé** de `allOptions` vers `schoolsSelect`, donc perdu du tableau initial. Avec `cloneNode(true)`, on **copie** l'élément, préservant l'original.

---

## 📊 Schéma de Données

### Relations

```
SchoolGroup
    ├── schools (OneToMany)
    │   └── School
    │       └── users (ManyToMany)
    │           └── User
    └── users (OneToMany)
        └── User
            └── schoolGroup (ManyToOne)
```

**Doubles liaisons** :
- `User` → `SchoolGroup` (ManyToOne) : Pour le filtrage
- `User` ↔ `School` (ManyToMany) : Pour l'accès réel aux établissements

### Tables SQL

```sql
-- Nouvelle colonne dans user
user
├── id
├── username
├── email
├── school_group_id  ← NOUVELLE COLONNE
└── ...

-- Table de jonction existante
user_school
├── user_id
└── school_id
```

---

## 🧪 Test Manuel

### Test 1 : Filtrage Dynamique

1. Aller sur `/admin/users/new`
2. Sélectionner "Groupe Enseignement Fondamental"
3. ✅ La liste des établissements montre SEULEMENT :
   - École Maternelle
   - École Primaire
4. Changer pour "Groupe Enseignement Secondaire"
5. ✅ La liste montre SEULEMENT :
   - Collège
   - Lycée

### Test 2 : Sans Groupe

1. Aller sur `/admin/users/new`
2. Ne pas sélectionner de groupe
3. ✅ Tous les établissements sont visibles

### Test 3 : Modification

1. Modifier un utilisateur existant
2. Sélectionner un groupe
3. ✅ Le filtrage fonctionne aussi en mode édition

### Test 4 : Affichage

1. Créer un utilisateur avec groupe et établissements
2. Aller sur `/admin/users`
3. ✅ Le groupe s'affiche dans la colonne "Groupe"
4. Cliquer sur "Voir"
5. ✅ Le groupe s'affiche avec un lien cliquable

---

## 💡 Cas d'Usage

### Scénario 1 : Directeur de Groupe

```
Utilisateur: Marie DUPONT
Type: Directeur
Groupe: Groupe Enseignement Fondamental
Établissements: École Maternelle + École Primaire

→ Marie gère tous les établissements du groupe Fondamental
```

### Scénario 2 : Enseignant Multi-Établissements

```
Utilisateur: Jean MARTIN
Type: Enseignant
Groupe: Groupe Enseignement Secondaire
Établissements: Collège + Lycée

→ Jean enseigne dans 2 établissements du même groupe
```

### Scénario 3 : Admin Sans Groupe

```
Utilisateur: Admin
Type: Administrateur
Groupe: (aucun)
Établissements: TOUS

→ Admin a accès à tous les établissements sans restriction
```

---

## 🔒 Sécurité

### Validation Côté Serveur

Le filtrage JavaScript est **seulement pour l'UX**.  
Le serveur ne valide PAS que les écoles appartiennent au groupe.

**Raison** : Un utilisateur peut avoir accès à des écoles de plusieurs groupes si nécessaire.

Si validation stricte nécessaire, ajouter dans `UserType` :
```php
->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
    $user = $event->getData();
    $group = $user->getSchoolGroup();
    
    if ($group) {
        foreach ($user->getSchools() as $school) {
            if ($school->getSchoolGroup() !== $group) {
                // Erreur : école hors groupe
            }
        }
    }
});
```

---

## 📈 Impact

```
Fichiers modifiés:    7
Lignes ajoutées:      ~200 lignes
SQL:                  1 colonne + 1 FK
JavaScript:           50 lignes
Fonctionnalité:       Filtrage dynamique en temps réel
```

---

## 🎉 Résultat

```
╔═══════════════════════════════════════════════════════╗
║                                                       ║
║   ✅  FILTRAGE DYNAMIQUE 100% FONCTIONNEL             ║
║                                                       ║
║   • Sélection de groupe                               ║
║   • Filtrage automatique des établissements           ║
║   • JavaScript temps réel                             ║
║   • Interface utilisateur fluide                      ║
║   • Affichage du groupe partout                       ║
║                                                       ║
║        EXPÉRIENCE UTILISATEUR OPTIMISÉE ! 🚀          ║
║                                                       ║
╚═══════════════════════════════════════════════════════╝
```

---

**Version** : 1.2.0  
**Date** : 09 Octobre 2025  
**Status** : ✅ Terminé et Testé

