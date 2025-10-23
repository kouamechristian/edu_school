# 👨‍🎓 Espace Élève - Interface de Consultation

## Vue d'ensemble

L'Espace Élève est une interface dédiée permettant aux élèves de consulter en autonomie :
- 📊 Leurs notes et moyennes
- 📄 Leurs bulletins par période
- 📅 Leur emploi du temps
- 📈 Leur progression académique
- 🏆 Leur classement

## Composants Créés

### 1. Contrôleur - `StudentSpaceController`
**Fichier** : `src/Controller/StudentSpaceController.php`

#### Routes Disponibles

##### `GET /student/dashboard` → `student_dashboard`
Tableau de bord principal de l'élève.

**Affiche** :
- Statistiques personnelles (moyenne, nombre d'évaluations, classement)
- Accès rapides (Notes, Bulletin, Emploi du temps)
- Dernières notes publiées
- Informations personnelles

##### `GET /student/grades` → `student_grades`
Consultation détaillée des notes.

**Fonctionnalités** :
- Sélection de la période
- Notes organisées par matière
- Moyennes par matière
- Moyenne générale
- Détails de chaque évaluation

##### `GET /student/bulletin/{periodId}` → `student_bulletin`
Affichage du bulletin pour une période.

**Contenu** :
- Toutes les moyennes par matière
- Moyenne générale
- Classement
- Appréciations
- Mentions éventuelles

##### `GET /student/schedule` → `student_schedule`
Emploi du temps de l'élève.

**Affichage** :
- Grille horaire complète
- Matières avec leurs couleurs
- Professeurs
- Salles
- Légende des matières

### 2. Service Utilisé - `GradeCalculationService`
Réutilise le service créé pour les bulletins admin.

**Méthodes utilisées** :
- `calculateStudentAveragesForPeriod()` - Calcul des moyennes
- `calculateClassRanking()` - Classement
- `getAppreciation()` - Appréciations
- `getMention()` - Mentions

### 3. Templates Créés

#### `templates/student_space/dashboard.html.twig`
Tableau de bord personnalisé de l'élève.

**Sections** :
```
┌─────────────────────────────────────┐
│ Bienvenue [Prénom] !                │
├─────────────────────────────────────┤
│ ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐    │
│ │14.50│ │ 12  │ │ #5  │ │ T1  │    │
│ │Moy. │ │Eval.│ │Rang │ │Pér. │    │
│ └─────┘ └─────┘ └─────┘ └─────┘    │
├─────────────────────────────────────┤
│ Accès Rapide :                      │
│ [Mes Notes] [Mon EDT] [Mon Bulletin]│
├─────────────────────────────────────┤
│ Dernières Notes :                   │
│ • Maths - Contrôle 1 : 15/20        │
│ • Français - Devoir : 13/20         │
│ • ...                               │
├─────────────────────────────────────┤
│ Mes Informations                    │
│ Mon Niveau : Bien                   │
└─────────────────────────────────────┘
```

**Éléments** :
- 4 cartes de statistiques
- Boutons d'accès rapide (grandes icônes)
- Tableau des 5 dernières notes
- Carte d'informations personnelles
- Badge de niveau (Très bien, Bien, etc.)

#### `templates/student_space/grades.html.twig`
Consultation détaillée des notes.

**Fonctionnalités** :
- Sélecteur de période
- 3 cartes de résumé (moyenne, nombre de matières, appréciation)
- Notes groupées par matière (accordéon)
- Moyenne affichée pour chaque matière
- Détails de chaque évaluation
- Code couleur (vert ≥ 10, rouge < 10)

**Organisation** :
```
┌─────────────────────────────────────┐
│ Sélectionner une Période : [T1 ▼]  │
├─────────────────────────────────────┤
│ Moyenne: 14.50 | Matières: 8 | Bien│
├─────────────────────────────────────┤
│ 📘 Mathématiques [Moyenne: 14.75]   │
│   ▼ Détails (cliquer pour voir)    │
│   • 15/10 - Contrôle 1 (coef 1)     │
│   • 14.50/10 - Examen (coef 2)      │
├─────────────────────────────────────┤
│ 📗 Français [Moyenne: 13.50]        │
│   ▼ Détails                         │
│   • ...                             │
└─────────────────────────────────────┘
```

#### `templates/student_space/bulletin.html.twig`
Affichage du bulletin scolaire.

**Design** :
- En-tête avec dégradé coloré
- Informations élève
- Tableau des moyennes par matière
- Moyenne générale en grand
- Badge d'appréciation
- Mentions éventuelles

**Calculs affichés** :
```
Matière    | Moyenne | Coef | Appréciation | Points
─────────────────────────────────────────────────
Maths      | 14.75   |  4   | Bien         | 59.00
Français   | 13.50   |  3   | Assez bien   | 40.50
...
─────────────────────────────────────────────────
MOYENNE GÉNÉRALE : 14.39 / 20
```

#### `templates/student_space/schedule.html.twig`
Emploi du temps personnel.

**Affichage** :
```
Horaires    | Lundi  | Mardi  | Mercredi | Jeudi | Vendredi
────────────────────────────────────────────────────────────
08:00-09:00 | Maths  | Français| -        | Maths | Histoire
            | Salle A| Salle B |          | Salle A| Salle C
────────────────────────────────────────────────────────────
09:00-10:00 | ...
```

**Caractéristiques** :
- Grille complète de la semaine
- Couleurs par matière
- Noms des professeurs
- Codes des salles
- Légende des couleurs en bas

### 4. Navigation Intégrée

**Ajout dans `templates/base.html.twig`** :

Menu conditionnel qui s'affiche **uniquement pour les élèves** :
```twig
{% if app.user and app.user.userType == 'eleve' %}
    Mon Espace Élève
    • Mon Tableau de Bord
    • Mes Notes
    • Mon Emploi du Temps
    • Ma Progression
{% endif %}
```

## Sécurité

### Contrôle d'Accès
Chaque méthode du contrôleur vérifie :
```php
if (!$user || $user->getUserType() !== 'eleve') {
    $this->addFlash('error', 'Accès réservé aux élèves.');
    return $this->redirectToRoute('app_login');
}
```

### Protection des Données
- ✅ Un élève ne peut voir QUE ses propres notes
- ✅ Seules les notes **publiées** sont visibles
- ✅ Pas d'accès aux notes des autres élèves
- ✅ Pas de modification possible (lecture seule)

## Expérience Utilisateur

### Design Moderne
- Interface colorée et attrayante
- Grandes icônes FontAwesome
- Badges pour les notes (vert/rouge)
- Animations Bootstrap
- Accordéons pour organisation
- Cartes avec ombres et bordures

### Navigation Intuitive
```
Tableau de Bord Élève
    ├── [Mes Notes] → Vue détaillée par matière
    ├── [Mon Bulletin] → Bulletin complet
    ├── [Mon Emploi du Temps] → Grille horaire
    └── [Ma Progression] → Graphiques (à venir)
```

### Informations en Temps Réel
- Moyennes mises à jour automatiquement
- Classement recalculé à chaque visite
- Nouvelles notes visibles dès publication
- Statistiques dynamiques

## Cas d'Usage

### Scénario 1 : Consulter ses Notes
```
1. Élève se connecte
2. Clique sur "Mes Notes" dans le menu
3. Sélectionne la période (ex: 1er Trimestre)
4. Voit toutes ses notes organisées par matière
5. Peut cliquer sur une matière pour voir le détail
6. Voit sa moyenne générale en haut
```

### Scénario 2 : Consulter son Bulletin
```
1. Depuis le tableau de bord
2. Clique sur "Mon Bulletin"
3. Sélectionne la période
4. Voit :
   - Toutes les moyennes par matière
   - Moyenne générale
   - Classement dans la classe
   - Appréciation générale
   - Mention éventuelle
```

### Scénario 3 : Voir son Emploi du Temps
```
1. Clique sur "Mon Emploi du Temps"
2. Voit la grille complète de la semaine
3. Pour chaque cours :
   - Matière (avec couleur)
   - Professeur
   - Salle
4. Peut consulter la légende des couleurs
```

## Fonctionnalités Avancées

### 1. Appréciations Automatiques
Basées sur la moyenne :
- **18-20** : ⭐⭐⭐ Excellent
- **16-18** : ⭐⭐ Très bien
- **14-16** : ⭐ Bien
- **12-14** : 👍 Assez bien
- **10-12** : ✋ Passable
- **< 10** : ⚠️ Insuffisant

### 2. Mentions du Conseil de Classe
- **≥ 18** : 🏆 Félicitations du conseil de classe
- **≥ 16** : ⭐ Compliments du conseil de classe
- **≥ 14** : 👍 Encouragements
- **≥ 12** : 📜 Tableau d'honneur

### 3. Statistiques Personnelles
```php
[
    'total_evaluations' => 12,
    'average' => 14.50,
    'rank' => 5,
    'class_total' => 28,
]
```

### 4. Code Couleur des Notes
- 🟢 **Vert** : Note ≥ 10 (réussite)
- 🔴 **Rouge** : Note < 10 (échec)
- 🟡 **Jaune** : Statut spécial (absent, dispensé)

## Améliorations Futures

### 1. Graphiques de Progression
```javascript
// Chart.js
var ctx = document.getElementById('progressChart');
var chart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['T1', 'T2', 'T3'],
        datasets: [{
            label: 'Ma Moyenne',
            data: [12.5, 13.8, 14.5],
            borderColor: '#0066cc',
            fill: false
        }]
    }
});
```

### 2. Comparaison avec la Classe
```
Ma Moyenne    : 14.50
Moy. Classe   : 12.45
Écart         : +2.05 (↗ Au-dessus)
```

### 3. Objectifs Personnels
```
Objectif T2 : 15/20
Actuel      : 14.50
Écart       : -0.50
Progrès     : 96.7% [████████▒▒] 
```

### 4. Notifications Push
```php
// Nouvelle note publiée
"📊 Nouvelle note en Mathématiques : 15/20"

// Bulletin disponible
"📄 Votre bulletin du 1er Trimestre est disponible"
```

### 5. Comparaison Inter-Périodes
```
Évolution de ma Moyenne Générale :
T1 : 12.50
T2 : 13.75 (↗ +1.25)
T3 : 14.20 (↗ +0.45)
```

### 6. Conseils Personnalisés
```
Matières à Renforcer :
• Mathématiques (10.50) → "Revoir le chapitre sur les fractions"
• Histoire (9.80) → "Approfondir les dates importantes"

Points Forts :
• Français (16.50) → "Excellent niveau !"
• SVT (15.20) → "Continue comme ça !"
```

### 7. Export Personnel
```php
// PDF de toutes les notes
$pdf = $this->generateMyGradesPdf($student, $period);

// Excel de la progression
$excel = $this->exportMyProgress($student);
```

## Accessibilité

### Niveaux de Lecture
- Interface adaptée à tous les âges
- Texte clair et lisible
- Icônes explicites
- Badges colorés

### Responsive Design
- ✅ Desktop (> 1200px)
- ✅ Tablette (768-1200px)
- ✅ Mobile (< 768px)

### Adaptation par Niveau
```twig
{% if student.level.code == 'maternelle' %}
    {# Interface simplifiée avec smileys #}
    😊 Très bien | 😐 Assez bien | 😢 Peut mieux faire
{% else %}
    {# Interface standard avec notes #}
    14.50 / 20 - Bien
{% endif %}
```

## Performance

### Optimisations
1. **Mise en cache des moyennes** :
```php
// Cache Redis - 1 heure
$cacheKey = sprintf('student_avg_%d_%d', $studentId, $periodId);
$average = $cache->get($cacheKey, function() {
    return $this->calculateAverage();
});
```

2. **Lazy Loading** :
```javascript
// Charger les détails au clic
$('.subject-detail').on('click', function() {
    // Charger en AJAX
});
```

3. **Pagination** :
Pour les élèves avec beaucoup d'évaluations.

## Ergonomie

### Éléments Visuels

#### Cartes de Statistiques
```
┌──────────────┐
│  ⭐          │
│              │
│   14.50      │ ← Grande valeur
│   ─────      │
│ Moyenne      │ ← Label
│ Générale     │
└──────────────┘
```

#### Badges de Notes
- 🟢 **≥ 10** : Badge vert "Réussite"
- 🔴 **< 10** : Badge rouge "Échec"

#### Accordéons par Matière
```
📘 Mathématiques [Moyenne: 14.75]  [▼]
    ├─ Contrôle 1 : 15/20 (coef 1)
    ├─ Devoir 2 : 14.50/20 (coef 1)
    └─ Examen : 14.50/20 (coef 2)

📗 Français [Moyenne: 13.50]  [▶]
    (Fermé - Cliquer pour voir)
```

## Messages Encourageants

### Selon la Moyenne
- **≥ 16** : "Excellent travail ! Continue comme ça !" 🎉
- **≥ 14** : "Très bon niveau ! Continue tes efforts !" 👏
- **≥ 12** : "Bon travail ! Tu peux encore progresser !" 👍
- **≥ 10** : "C'est bien, mais il faut redoubler d'efforts !" 💪
- **< 10** : "Il faut travailler davantage. N'hésite pas à demander de l'aide !" 📚

### Conseils Ciblés
```twig
{% if average < 10 %}
    <div class="alert alert-warning">
        <i class="fas fa-lightbulb"></i>
        <strong>Conseil :</strong> N'hésite pas à demander de l'aide 
        à tes professeurs ou à suivre du soutien scolaire.
    </div>
{% endif %}
```

## Intégration avec les Modules

### Module 1 - Établissements
- ✅ Utilise `School` pour l'établissement de l'élève
- ✅ Utilise `SchoolYear` pour l'année en cours

### Module 2 - Utilisateurs
- ✅ Authentification élève
- ✅ Profil de l'élève
- ✅ Type d'utilisateur = 'eleve'

### Module 3 - Académique
- ✅ Affichage de l'emploi du temps (Courses)
- ✅ Matières avec couleurs (Subjects)
- ✅ Classe de l'élève (Classroom)

### Module 4 - Notes
- ✅ Consultation des évaluations
- ✅ Affichage des notes (Grades)
- ✅ Calcul des moyennes
- ✅ Bulletins

## Configuration

### Activer l'Espace Élève
L'espace est automatiquement actif pour tout utilisateur de type `eleve`.

**Vérification dans le contrôleur** :
```php
if ($user->getUserType() !== 'eleve') {
    // Accès refusé
}
```

**Vérification dans le menu** :
```twig
{% if app.user and app.user.userType == 'eleve' %}
    {# Menu élève #}
{% endif %}
```

### Personnalisation
Pour masquer certaines fonctionnalités :
```twig
{# Dans dashboard.html.twig #}
{% if show_ranking %}
    <div class="col-md-3">
        <!-- Carte de classement -->
    </div>
{% endif %}
```

## Tests Utilisateur

### Test 1 : Connexion Élève
1. Se connecter avec un compte élève
2. Vérifier que le menu "Mon Espace Élève" s'affiche
3. Vérifier que les menus admin sont masqués

### Test 2 : Consultation des Notes
1. Aller dans "Mes Notes"
2. Sélectionner une période
3. Vérifier que seules les notes publiées sont visibles
4. Vérifier le calcul des moyennes

### Test 3 : Affichage du Bulletin
1. Cliquer sur "Mon Bulletin"
2. Vérifier les moyennes par matière
3. Vérifier la moyenne générale
4. Vérifier l'appréciation et la mention

### Test 4 : Emploi du Temps
1. Aller dans "Mon Emploi du Temps"
2. Vérifier que tous les cours sont affichés
3. Vérifier les couleurs des matières
4. Vérifier les informations (prof, salle)

## État Actuel

**Statut** : ✅ Opérationnel  
**Version** : 1.0  
**Date** : 12 Janvier 2025

## Fonctionnalités Implémentées

✅ Tableau de bord élève avec statistiques  
✅ Consultation détaillée des notes  
✅ Affichage du bulletin par période  
✅ Emploi du temps personnel  
✅ Calcul automatique des moyennes  
✅ Classement dans la classe  
✅ Appréciations automatiques  
✅ Mentions selon la moyenne  
✅ Design moderne et responsive  
✅ Navigation conditionnelle  
✅ Sécurité et contrôle d'accès  

## Prochaines Améliorations

⏭️ Graphiques de progression (Chart.js)  
⏭️ Comparaison avec la moyenne de classe  
⏭️ Objectifs personnels  
⏭️ Notifications push  
⏭️ Export PDF des notes  
⏭️ Conseils personnalisés  
⏭️ Interface pour tablette/mobile  
⏭️ Mode sombre  

---

**Documentation complète de l'Espace Élève EDU-SCHOOL**

