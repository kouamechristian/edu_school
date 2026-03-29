# 🗄️ Documentation de la Base de Données - EDU-SCHOOL

## 📊 Schéma de Base de Données Complet

### Vue d'ensemble

La base de données EDU-SCHOOL est conçue pour gérer tous les aspects d'un établissement scolaire multi-niveaux. Elle utilise MySQL/MariaDB avec Doctrine ORM.

## 🏢 Module Établissement

### Table: `school`
Gestion des établissements scolaires

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT(PK) | Identifiant unique |
| name | VARCHAR(255) | Nom de l'établissement |
| code | VARCHAR(50) | Code établissement (unique) |
| type | ENUM | Type (maternelle, primaire, collège, lycée, université) |
| address | TEXT | Adresse complète |
| phone | VARCHAR(20) | Téléphone |
| email | VARCHAR(100) | Email |
| director | VARCHAR(100) | Nom du directeur |
| logo | VARCHAR(255) | Chemin du logo |
| created_at | DATETIME | Date de création |
| updated_at | DATETIME | Date de modification |

**Relations:**
- 1:N avec `school_year`
- 1:N avec `classroom`
- 1:N avec `teacher`

### Table: `school_year`
Années scolaires

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT(PK) | Identifiant unique |
| school_id | INT(FK) | Référence établissement |
| name | VARCHAR(50) | Ex: "2024-2025" |
| start_date | DATE | Date de début |
| end_date | DATE | Date de fin |
| is_current | BOOLEAN | Année en cours |
| created_at | DATETIME | Date de création |

**Relations:**
- N:1 avec `school`
- 1:N avec `period`

### Table: `period`
Périodes d'évaluation (trimestre, semestre)

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT(PK) | Identifiant unique |
| school_year_id | INT(FK) | Référence année scolaire |
| name | VARCHAR(50) | Ex: "1er Trimestre" |
| type | ENUM | trimestre, semestre, annuel |
| start_date | DATE | Date de début |
| end_date | DATE | Date de fin |
| weight | DECIMAL(3,2) | Coefficient (ex: 0.33) |

**Relations:**
- N:1 avec `school_year`
- 1:N avec `grade`

## 👤 Module Utilisateurs

### Table: `user`
Utilisateurs du système (table principale)

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT(PK) | Identifiant unique |
| username | VARCHAR(180) | Nom d'utilisateur (unique) |
| email | VARCHAR(180) | Email (unique) |
| roles | JSON | Rôles utilisateur |
| password | VARCHAR(255) | Mot de passe hashé |
| is_active | BOOLEAN | Compte actif |
| last_login | DATETIME | Dernière connexion |
| avatar | VARCHAR(255) | Photo de profil |
| created_at | DATETIME | Date de création |
| updated_at | DATETIME | Date de modification |

**Index:**
- UNIQUE sur `username`
- UNIQUE sur `email`
- INDEX sur `is_active`

### Table: `student`
Profil élève/étudiant

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT(PK) | Identifiant unique |
| user_id | INT(FK) | Référence utilisateur |
| registration_number | VARCHAR(50) | Matricule (unique) |
| first_name | VARCHAR(100) | Prénom |
| last_name | VARCHAR(100) | Nom |
| date_of_birth | DATE | Date de naissance |
| place_of_birth | VARCHAR(100) | Lieu de naissance |
| gender | ENUM | M, F |
| blood_group | VARCHAR(5) | Groupe sanguin |
| nationality | VARCHAR(50) | Nationalité |
| address | TEXT | Adresse |
| phone | VARCHAR(20) | Téléphone |
| emergency_contact | VARCHAR(100) | Contact d'urgence |
| emergency_phone | VARCHAR(20) | Téléphone urgence |
| photo | VARCHAR(255) | Photo |
| qr_code | VARCHAR(255) | QR Code pour carte |
| classroom_id | INT(FK) | Classe actuelle |
| status | ENUM | active, graduated, dropped, transferred |
| enrollment_date | DATE | Date d'inscription |
| created_at | DATETIME | Date de création |

**Relations:**
- 1:1 avec `user`
- N:1 avec `classroom`
- N:M avec `parent` (table pivot `student_parent`)
- 1:N avec `grade`
- 1:N avec `attendance`
- 1:N avec `payment`

**Index:**
- UNIQUE sur `registration_number`
- INDEX sur `classroom_id`
- INDEX sur `status`

### Table: `teacher`
Profil enseignant

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT(PK) | Identifiant unique |
| user_id | INT(FK) | Référence utilisateur |
| employee_number | VARCHAR(50) | Matricule (unique) |
| first_name | VARCHAR(100) | Prénom |
| last_name | VARCHAR(100) | Nom |
| date_of_birth | DATE | Date de naissance |
| gender | ENUM | M, F |
| phone | VARCHAR(20) | Téléphone |
| address | TEXT | Adresse |
| specialization | VARCHAR(100) | Spécialisation |
| qualification | VARCHAR(100) | Diplôme |
| hire_date | DATE | Date d'embauche |
| status | ENUM | active, on_leave, retired |
| created_at | DATETIME | Date de création |

**Relations:**
- 1:1 avec `user`
- N:M avec `subject` (table pivot `teacher_subject`)
- N:M avec `classroom` (table pivot `teacher_classroom`)
- 1:N avec `course`

### Table: `parent`
Profil parent/tuteur

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT(PK) | Identifiant unique |
| user_id | INT(FK) | Référence utilisateur |
| first_name | VARCHAR(100) | Prénom |
| last_name | VARCHAR(100) | Nom |
| relation | ENUM | father, mother, guardian, other |
| phone | VARCHAR(20) | Téléphone |
| email | VARCHAR(100) | Email |
| profession | VARCHAR(100) | Profession |
| address | TEXT | Adresse |
| created_at | DATETIME | Date de création |

**Relations:**
- 1:1 avec `user`
- N:M avec `student` (table pivot `student_parent`)

### Table: `student_parent`
Relation élève-parent

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT(PK) | Identifiant unique |
| student_id | INT(FK) | Référence élève |
| parent_id | INT(FK) | Référence parent |
| is_primary | BOOLEAN | Contact principal |
| can_fetch | BOOLEAN | Autorisé à récupérer l'enfant |

## 📚 Module Académique

### Table: `level`
Niveaux scolaires

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT(PK) | Identifiant unique |
| name | VARCHAR(100) | Ex: "Maternelle", "CP", "6ème" |
| category | ENUM | maternelle, primaire, college, lycee, universite |
| order | INT | Ordre d'affichage |
| description | TEXT | Description |

**Relations:**
- 1:N avec `classroom`

### Table: `classroom`
Classes

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT(PK) | Identifiant unique |
| school_id | INT(FK) | Référence établissement |
| school_year_id | INT(FK) | Référence année scolaire |
| level_id | INT(FK) | Référence niveau |
| name | VARCHAR(100) | Ex: "6ème A" |
| code | VARCHAR(50) | Code classe |
| capacity | INT | Capacité maximale |
| room_number | VARCHAR(20) | Numéro de salle |
| main_teacher_id | INT(FK) | Professeur principal |
| created_at | DATETIME | Date de création |

**Relations:**
- N:1 avec `school`
- N:1 avec `school_year`
- N:1 avec `level`
- N:1 avec `teacher` (main_teacher)
- 1:N avec `student`
- N:M avec `subject`

### Table: `subject`
Matières

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT(PK) | Identifiant unique |
| name | VARCHAR(100) | Nom de la matière |
| code | VARCHAR(20) | Code matière |
| coefficient | DECIMAL(3,2) | Coefficient |
| color | VARCHAR(7) | Couleur (hex) |
| description | TEXT | Description |
| level_id | INT(FK) | Niveau concerné |

**Relations:**
- N:1 avec `level`
- N:M avec `teacher`
- N:M avec `classroom`
- 1:N avec `grade`

### Table: `course`
Cours / Séances

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT(PK) | Identifiant unique |
| subject_id | INT(FK) | Référence matière |
| teacher_id | INT(FK) | Référence enseignant |
| classroom_id | INT(FK) | Référence classe |
| day_of_week | INT | Jour (1=Lundi, 7=Dimanche) |
| start_time | TIME | Heure de début |
| end_time | TIME | Heure de fin |
| room | VARCHAR(50) | Salle |
| is_active | BOOLEAN | Actif |

**Relations:**
- N:1 avec `subject`
- N:1 avec `teacher`
- N:1 avec `classroom`

## 📊 Module Évaluation

### Table: `grade`
Notes

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT(PK) | Identifiant unique |
| student_id | INT(FK) | Référence élève |
| subject_id | INT(FK) | Référence matière |
| period_id | INT(FK) | Référence période |
| teacher_id | INT(FK) | Enseignant |
| grade_value | DECIMAL(5,2) | Note obtenue |
| max_grade | DECIMAL(5,2) | Note maximale |
| coefficient | DECIMAL(3,2) | Coefficient |
| type | ENUM | exam, test, homework, oral |
| description | VARCHAR(255) | Description |
| date | DATE | Date de l'évaluation |
| created_at | DATETIME | Date de création |

**Relations:**
- N:1 avec `student`
- N:1 avec `subject`
- N:1 avec `period`
- N:1 avec `teacher`

**Index:**
- INDEX sur `student_id`
- INDEX sur `period_id`
- INDEX sur `date`

### Table: `report_card`
Bulletins de notes

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT(PK) | Identifiant unique |
| student_id | INT(FK) | Référence élève |
| period_id | INT(FK) | Référence période |
| general_average | DECIMAL(5,2) | Moyenne générale |
| rank | INT | Classement |
| total_students | INT | Effectif total |
| appreciation | TEXT | Appréciation |
| generated_at | DATETIME | Date de génération |
| pdf_path | VARCHAR(255) | Chemin du PDF |

**Relations:**
- N:1 avec `student`
- N:1 avec `period`

## 📅 Module Assiduité

### Table: `attendance`
Présences/Absences

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT(PK) | Identifiant unique |
| student_id | INT(FK) | Référence élève |
| course_id | INT(FK) | Référence cours |
| date | DATE | Date |
| status | ENUM | present, absent, late, excused |
| arrival_time | TIME | Heure d'arrivée (si retard) |
| reason | TEXT | Motif |
| justification | VARCHAR(255) | Document justificatif |
| recorded_by | INT(FK) | Enregistré par (user) |
| created_at | DATETIME | Date de création |

**Relations:**
- N:1 avec `student`
- N:1 avec `course`
- N:1 avec `user` (recorded_by)

**Index:**
- INDEX sur `student_id`
- INDEX sur `date`
- INDEX sur `status`

## 💰 Module Financier

### Table: `fee_type`
Types de frais

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT(PK) | Identifiant unique |
| name | VARCHAR(100) | Ex: "Scolarité", "Cantine" |
| code | VARCHAR(20) | Code |
| description | TEXT | Description |
| is_mandatory | BOOLEAN | Obligatoire |

### Table: `fee`
Frais de scolarité

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT(PK) | Identifiant unique |
| fee_type_id | INT(FK) | Type de frais |
| student_id | INT(FK) | Référence élève |
| school_year_id | INT(FK) | Référence année |
| amount | DECIMAL(10,2) | Montant |
| due_date | DATE | Date d'échéance |
| status | ENUM | pending, partial, paid, overdue |
| created_at | DATETIME | Date de création |

**Relations:**
- N:1 avec `fee_type`
- N:1 avec `student`
- N:1 avec `school_year`
- 1:N avec `payment`

### Table: `payment`
Paiements

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT(PK) | Identifiant unique |
| fee_id | INT(FK) | Référence frais |
| student_id | INT(FK) | Référence élève |
| amount | DECIMAL(10,2) | Montant payé |
| payment_date | DATE | Date de paiement |
| payment_method | ENUM | cash, check, card, transfer, mobile_money |
| reference | VARCHAR(100) | Référence |
| receipt_number | VARCHAR(50) | Numéro reçu |
| received_by | INT(FK) | Reçu par (user) |
| notes | TEXT | Notes |
| created_at | DATETIME | Date de création |

**Relations:**
- N:1 avec `fee`
- N:1 avec `student`
- N:1 avec `user` (received_by)

## 📚 Module Bibliothèque

### Table: `book`
Livres

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT(PK) | Identifiant unique |
| isbn | VARCHAR(20) | ISBN |
| title | VARCHAR(255) | Titre |
| author | VARCHAR(100) | Auteur |
| publisher | VARCHAR(100) | Éditeur |
| category | VARCHAR(50) | Catégorie |
| quantity | INT | Quantité totale |
| available | INT | Quantité disponible |
| location | VARCHAR(50) | Emplacement |

**Relations:**
- 1:N avec `book_loan`

### Table: `book_loan`
Emprunts de livres

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT(PK) | Identifiant unique |
| book_id | INT(FK) | Référence livre |
| student_id | INT(FK) | Référence élève |
| loan_date | DATE | Date d'emprunt |
| due_date | DATE | Date de retour prévue |
| return_date | DATE | Date de retour réelle |
| status | ENUM | borrowed, returned, overdue, lost |
| fine_amount | DECIMAL(10,2) | Pénalité |

**Relations:**
- N:1 avec `book`
- N:1 avec `student`

## 📱 Module Communication

### Table: `notification`
Notifications

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT(PK) | Identifiant unique |
| user_id | INT(FK) | Destinataire |
| type | ENUM | info, warning, success, error |
| title | VARCHAR(255) | Titre |
| message | TEXT | Message |
| link | VARCHAR(255) | Lien |
| is_read | BOOLEAN | Lu |
| sent_at | DATETIME | Date d'envoi |
| read_at | DATETIME | Date de lecture |

**Relations:**
- N:1 avec `user`

### Table: `announcement`
Annonces

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT(PK) | Identifiant unique |
| author_id | INT(FK) | Auteur |
| title | VARCHAR(255) | Titre |
| content | TEXT | Contenu |
| target_audience | JSON | Public cible |
| priority | ENUM | low, normal, high, urgent |
| start_date | DATE | Date de début |
| end_date | DATE | Date de fin |
| is_published | BOOLEAN | Publié |
| created_at | DATETIME | Date de création |

**Relations:**
- N:1 avec `user` (author)

## 📄 Module Documents

### Table: `document`
Documents

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT(PK) | Identifiant unique |
| type | ENUM | certificate, attestation, transcript, report |
| student_id | INT(FK) | Référence élève |
| title | VARCHAR(255) | Titre |
| file_path | VARCHAR(255) | Chemin du fichier |
| file_size | INT | Taille (octets) |
| mime_type | VARCHAR(50) | Type MIME |
| generated_by | INT(FK) | Généré par |
| generated_at | DATETIME | Date de génération |

**Relations:**
- N:1 avec `student`
- N:1 avec `user` (generated_by)

## 🔍 Index et Optimisations

### Index principaux

```sql
-- Performance queries
CREATE INDEX idx_student_classroom ON student(classroom_id);
CREATE INDEX idx_student_status ON student(status);
CREATE INDEX idx_grade_student_period ON grade(student_id, period_id);
CREATE INDEX idx_attendance_student_date ON attendance(student_id, date);
CREATE INDEX idx_payment_student ON payment(student_id);
CREATE INDEX idx_user_active ON user(is_active);

-- Recherche texte
CREATE FULLTEXT INDEX idx_student_name ON student(first_name, last_name);
CREATE FULLTEXT INDEX idx_teacher_name ON teacher(first_name, last_name);
```

### Vues utiles

```sql
-- Vue: Moyenne par élève et période
CREATE VIEW student_period_average AS
SELECT 
    s.id AS student_id,
    p.id AS period_id,
    AVG((g.grade_value / g.max_grade) * 20 * g.coefficient) AS average
FROM student s
JOIN grade g ON s.id = g.student_id
JOIN period p ON g.period_id = p.id
GROUP BY s.id, p.id;

-- Vue: Statistiques de présence
CREATE VIEW attendance_stats AS
SELECT 
    s.id AS student_id,
    COUNT(*) AS total_days,
    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_days,
    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent_days
FROM student s
LEFT JOIN attendance a ON s.id = a.student_id
GROUP BY s.id;
```

## 🔐 Sécurité Base de Données

### Bonnes pratiques
1. **Mots de passe** : Toujours hashés (bcrypt/argon2)
2. **Données sensibles** : Chiffrement au niveau application
3. **Sauvegardes** : Automatiques quotidiennes
4. **Permissions** : Utilisateurs DB avec privilèges minimums
5. **Audit** : Logs des modifications sensibles

### Contraintes d'intégrité

```sql
-- Contraintes de suppression
ALTER TABLE student 
    ADD CONSTRAINT fk_student_user 
    FOREIGN KEY (user_id) 
    REFERENCES user(id) 
    ON DELETE CASCADE;

-- Contraintes de validation
ALTER TABLE grade 
    ADD CONSTRAINT chk_grade_value 
    CHECK (grade_value >= 0 AND grade_value <= max_grade);
```

---

**Document maintenu par** : Équipe Database EDU-SCHOOL  
**Dernière révision** : Octobre 2025

