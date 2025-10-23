# 📄 Système de Génération de Bulletins de Notes

## Vue d'ensemble

Le système de génération de bulletins permet de créer automatiquement des bulletins de notes professionnels au format PDF pour les élèves, avec calcul automatique des moyennes, classements et appréciations.

## Composants Créés

### 1. Service de Calcul - `GradeCalculationService`
**Fichier** : `src/Service/GradeCalculationService.php`

#### Méthodes Principales

##### `calculateStudentAveragesForPeriod(User $student, Period $period): array`
Calcule toutes les moyennes d'un élève pour une période donnée.

**Retourne** :
```php
[
    'subjects' => [
        [
            'subject' => Subject,
            'average' => 14.75,
            'coefficient' => 4,
            'weighted_average' => 59
        ],
        // ...
    ],
    'general_average' => 14.39,
    'total_coefficient' => 26,
    'class_rank' => null,
    'class_total' => null,
]
```

**Formule** :
```
Moyenne Matière = Σ(Note × Coef_évaluation) / Σ(Coef_évaluation)
```

##### `calculateClassRanking(User $student, Period $period, int $classroomId): array`
Calcule le classement d'un élève dans sa classe.

**Retourne** :
```php
[
    'rank' => 5,              // Position de l'élève
    'total' => 28,            // Nombre total d'élèves
    'class_average' => 12.45, // Moyenne de la classe
    'best_average' => 17.80,  // Meilleure moyenne
    'worst_average' => 8.20   // Plus faible moyenne
]
```

**Algorithme** :
1. Récupère tous les élèves de la classe
2. Calcule la moyenne générale de chaque élève
3. Trie par ordre décroissant
4. Trouve la position de l'élève

##### `getAppreciation(float $average): string`
Retourne l'appréciation basée sur la moyenne.

**Barème** :
- **18-20** : Excellent
- **16-18** : Très bien
- **14-16** : Bien
- **12-14** : Assez bien
- **10-12** : Passable
- **8-10** : Insuffisant
- **0-8** : Très insuffisant

##### `getMention(float $average): ?string`
Retourne la mention pour le bulletin.

**Mentions** :
- **≥ 18** : Félicitations du conseil de classe
- **≥ 16** : Compliments du conseil de classe
- **≥ 14** : Encouragements
- **≥ 12** : Tableau d'honneur

### 2. Contrôleur - `BulletinController`
**Fichier** : `src/Controller/BulletinController.php`

#### Routes Disponibles

##### `GET /admin/bulletins` → `admin_bulletin_index`
Interface de sélection et génération des bulletins.

**Fonctionnalités** :
- Sélection de la classe
- Sélection de la période
- Liste des élèves avec leurs moyennes
- Génération individuelle ou en masse

##### `GET /admin/bulletins/generate/{studentId}/{periodId}` → `admin_bulletin_generate`
Aperçu HTML du bulletin (pour visualisation dans le navigateur).

##### `GET /admin/bulletins/pdf/{studentId}/{periodId}` → `admin_bulletin_pdf`
Génération du bulletin au format PDF.

**Utilise** : Dompdf pour la conversion HTML → PDF

##### `POST /admin/bulletins/batch-generate` → `admin_bulletin_batch_generate`
Génération en masse de tous les bulletins d'une classe.

### 3. Templates

#### `templates/bulletin/index.html.twig`
Interface principale de génération.

**Fonctionnalités** :
- Filtres par classe et période
- Liste des élèves avec :
  - Rang
  - Nom complet
  - Moyenne générale
  - Appréciation
  - Actions (Voir / PDF)
- Génération en masse

#### `templates/bulletin/view.html.twig`
Aperçu HTML du bulletin.

**Sections** :
- En-tête (période, établissement)
- Informations élève
- Tableau des moyennes par matière
- Résumé (moyenne générale, classement)
- Mention et appréciation

#### `templates/bulletin/pdf.html.twig`
Template optimisé pour la génération PDF.

**Caractéristiques** :
- Design professionnel
- Mise en page A4 portrait
- Styles CSS intégrés
- Compatible Dompdf
- Zones de signatures

**Sections** :
```
┌────────────────────────────────────┐
│     BULLETIN DE NOTES              │
│     Période - Établissement        │
├────────────────────────────────────┤
│  Informations Élève                │
│  - Nom, Prénom                     │
│  - Date de naissance               │
│  - Classe, Numéro                  │
├────────────────────────────────────┤
│  Tableau des Moyennes              │
│  Matière | Moy | Coef | Appréciation│
├────────────────────────────────────┤
│  Moyenne Générale : XX.XX / 20     │
│  Classement : X / XX               │
├────────────────────────────────────┤
│  Appréciation Générale             │
│  Mention (si applicable)           │
├────────────────────────────────────┤
│  Signatures                        │
│  Prof | Directeur | Parents        │
└────────────────────────────────────┘
```

## Utilisation

### 1. Générer un Bulletin Individuel

```
1. Admin → Bulletins
2. Sélectionner :
   - Classe : 6A
   - Période : 1er Trimestre
3. Dans la liste des élèves :
   - Cliquer sur l'icône 👁️ pour voir l'aperçu
   - Cliquer sur l'icône 📄 pour télécharger le PDF
```

### 2. Génération en Masse

```
1. Admin → Bulletins
2. Sélectionner classe et période
3. Cliquer sur "Générer tous les bulletins"
4. Confirmation
5. Les bulletins sont générés pour tous les élèves
```

### 3. Personnalisation

#### Modifier l'Appréciation

**Fichier** : `src/Service/GradeCalculationService.php`

```php
public function getAppreciation(float $average): string
{
    if ($average >= 18) {
        return 'Excellent - Votre texte';
    }
    // ...
}
```

#### Modifier le Barème des Mentions

```php
public function getMention(float $average): ?string
{
    if ($average >= 18) {
        return 'Votre mention personnalisée';
    }
    // ...
}
```

#### Personnaliser le Design PDF

**Fichier** : `templates/bulletin/pdf.html.twig`

Modifier les styles CSS dans la balise `<style>` :
```css
.header {
    background-color: #votre-couleur;
}
```

## Formules de Calcul

### Moyenne par Matière
```
Exemple :
Contrôle 1 : 15/20 (coef 1)
Devoir     : 12/20 (coef 1)
Examen     : 16/20 (coef 2)

Moyenne = (15×1 + 12×1 + 16×2) / (1+1+2)
        = 59 / 4
        = 14.75
```

### Moyenne Générale
```
Exemple :
Maths   : 14.75/20 (coef 4) → 59 points
Français : 13.50/20 (coef 3) → 40.5 points
Histoire : 15.00/20 (coef 2) → 30 points

Moyenne Générale = (59 + 40.5 + 30) / (4+3+2)
                 = 129.5 / 9
                 = 14.39
```

### Classement
```
1. Calculer la moyenne générale de tous les élèves
2. Trier par ordre décroissant
3. Position de l'élève = Rang

Exemple :
1. Jean : 17.50
2. Marie : 16.80
3. Paul : 15.90
4. Sophie : 14.75  ← Rang 4/28
```

## Dépendances

### Dompdf
Le système utilise Dompdf pour générer les PDF.

**Installation** (si nécessaire) :
```bash
composer require dompdf/dompdf
```

**Configuration** dans `BulletinController` :
```php
$options = new Options();
$options->set('defaultFont', 'Arial');
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true); // Pour les images externes

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
```

## Améliorations Possibles

### 1. Commentaires Personnalisés
Ajouter un champ pour les commentaires du professeur :
```php
// Dans Evaluation
private ?string $professorComment = null;
```

### 2. Graphiques de Progression
Intégrer Chart.js pour afficher l'évolution :
```html
<canvas id="progressChart"></canvas>
<script>
    // Données de progression
    var ctx = document.getElementById('progressChart');
    var chart = new Chart(ctx, {
        type: 'line',
        data: { /* moyennes par période */ }
    });
</script>
```

### 3. Envoi par Email
Automatiser l'envoi des bulletins :
```php
use Symfony\Component\Mailer\MailerInterface;

public function sendBulletin(User $student, string $pdfPath)
{
    $email = (new Email())
        ->from('school@example.com')
        ->to($student->getEmail())
        ->subject('Votre bulletin de notes')
        ->attach($pdfPath);
        
    $this->mailer->send($email);
}
```

### 4. Signature Électronique
Intégrer des signatures numériques :
```php
use setasign\Fpdi\Fpdi;

$pdf = new Fpdi();
// Ajouter signature
$pdf->addSignature($certificat);
```

### 5. QR Code de Vérification
Ajouter un QR code pour authentification :
```php
use Endroid\QrCode\QrCode;

$qrCode = new QrCode($verificationUrl);
$qrCodeImage = $qrCode->writeString();
```

### 6. Export Excel
Générer un tableau récapitulatif :
```php
use PhpOffice\PhpSpreadsheet\Spreadsheet;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setCellValue('A1', 'Élève');
$sheet->setCellValue('B1', 'Moyenne');
// ...
```

### 7. Comparaison Inter-Périodes
Afficher l'évolution sur plusieurs trimestres :
```
┌─────────────────────────────────┐
│  Trimestre 1 : 12.50            │
│  Trimestre 2 : 13.75 (↗ +1.25) │
│  Trimestre 3 : 14.20 (↗ +0.45) │
└─────────────────────────────────┘
```

### 8. Bulletin Interactif (Web)
Version HTML interactive avec :
- Graphiques animés
- Tooltips explicatifs
- Navigation fluide
- Export PDF à la demande

## Sécurité

### Contrôles d'Accès
```php
// Dans BulletinController
if (!$this->isGranted('ROLE_ADMIN')) {
    throw $this->createAccessDeniedException();
}

// Vérifier que l'enseignant ne peut voir que ses classes
if ($this->isGranted('ROLE_TEACHER')) {
    // Filtrer par professeur
}
```

### Protection des Données
- Les bulletins ne sont accessibles qu'aux personnes autorisées
- Les PDF sont générés à la demande (pas de stockage permanent)
- Les moyennes sont recalculées en temps réel

## Performance

### Optimisations
1. **Mise en cache des moyennes** :
```php
// Cache Redis pour 1 heure
$average = $cache->get('student_' . $studentId . '_period_' . $periodId, function() {
    return $this->calculateAverage();
});
```

2. **Génération asynchrone en masse** :
```php
// Utiliser Symfony Messenger
$this->bus->dispatch(new GenerateBulletinMessage($studentId, $periodId));
```

3. **Pagination** :
Pour les classes nombreuses (> 50 élèves).

## Tests

### Test Unitaire du Calcul
```php
public function testCalculateAverage()
{
    $average = $this->service->calculateStudentAveragesForPeriod($student, $period);
    
    $this->assertEquals(14.75, $average['general_average']);
    $this->assertArrayHasKey('subjects', $average);
}
```

### Test de Génération PDF
```php
public function testGeneratePdf()
{
    $response = $this->client->request('GET', '/admin/bulletins/pdf/1/1');
    
    $this->assertResponseIsSuccessful();
    $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
}
```

## Dépannage

### Problème : PDF vide
**Solution** : Vérifier que Dompdf peut charger les styles CSS.

### Problème : Calcul incorrect
**Solution** : Vérifier que les coefficients sont bien définis dans les matières et évaluations.

### Problème : Classement incorrect
**Solution** : S'assurer que tous les élèves ont des moyennes publiées.

## État Actuel

**Statut** : ✅ Opérationnel  
**Version** : 1.0  
**Date** : 12 Janvier 2025

## Fonctionnalités Implémentées

✅ Calcul automatique des moyennes par matière  
✅ Calcul de la moyenne générale  
✅ Classement dans la classe  
✅ Appréciations automatiques  
✅ Mentions selon la moyenne  
✅ Génération PDF professionnelle  
✅ Aperçu HTML  
✅ Génération en masse  
✅ Interface utilisateur intuitive  
✅ Navigation intégrée  

## Fonctionnalités à Venir

⏭️ Commentaires personnalisés par matière  
⏭️ Graphiques de progression  
⏭️ Envoi automatique par email  
⏭️ Signature électronique  
⏭️ QR Code de vérification  
⏭️ Export Excel récapitulatif  
⏭️ Comparaison inter-périodes  
⏭️ Bulletin interactif web  

---

**Documentation complète du système de génération de bulletins EDU-SCHOOL**

