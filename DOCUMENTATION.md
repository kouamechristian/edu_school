# EDU-SCHOOL — Documentation fonctionnelle et technique

> Plateforme de gestion d'établissements scolaires (multi-établissements, multi-années)
> développée avec **Symfony 6.4 / PHP 8.1+ / Doctrine ORM 3 / MySQL / Twig / Bootstrap**.

Cette documentation présente le projet **module par module** : rôle fonctionnel, entités
concernées, contrôleurs, services métier et règles de gestion.

---

## 1. Vue d'ensemble

EDU-SCHOOL est une application web de gestion scolaire couvrant l'ensemble du cycle de vie
d'un élève (préinscription → inscription → scolarité → notes/bulletins → finances) ainsi que
l'administration de l'établissement (RH, caisse, recouvrement, communication) et des espaces
dédiés (parent, enseignant, fondateur).

### Chiffres clés du code

| Élément        | Nombre |
|----------------|--------|
| Contrôleurs    | 44     |
| Entités        | 41     |
| Services       | 29     |
| Formulaires    | 34     |
| Templates Twig | 209    |
| Migrations     | 18     |

### Stack technique

- **Framework** : Symfony 6.4 (LTS)
- **ORM** : Doctrine ORM 3 + migrations
- **Base de données** : MySQL (via `DATABASE_URL`)
- **Templating** : Twig + Bootstrap 5 + FontAwesome
- **PDF** : Dompdf (reçus, bulletins, rapports)
- **Tableurs** : PhpSpreadsheet (imports/exports Excel)
- **QR Code** : endroid/qr-code (reçus, badges)
- **Upload de fichiers** : VichUploaderBundle
- **Pagination** : KnpPaginatorBundle (50/page sur toutes les listes)
- **Éditeur riche / gestionnaire de fichiers** : CKEditor + elFinder
- **E-mail** : Symfony Mailer + PHPMailer
- **IA** : API Anthropic Claude (`ANTHROPIC_API_KEY`, `AI_MODEL`)
- **Paiement en ligne** : passerelle GeniusPay (Mobile Money)

### Concepts transverses

- **Multi-établissement** : la plupart des entités sont rattachées à une `School`. Un
  `SchoolContextSubscriber` + un filtre Doctrine (`SchoolFilter`) cloisonnent les données par
  établissement courant. Voir [Contexte établissement / année](#3-contexte-établissement--année-scolaire).
- **Multi-année** : la scolarité d'un élève est portée par une `Registration` annuelle (et non
  par l'élève lui-même). L'élève (`Student`) est un **pur référentiel** d'identité.
- **Sécurité par rôles hiérarchiques** : voir [Sécurité & rôles](#2-sécurité--rôles).

---

## 2. Sécurité & rôles

**Fichiers clés** : `config/packages/security.yaml`, `src/Security/MainAuthenticator.php`,
`src/Entity/User.php`, `src/Controller/SecurityController.php`.

### Authentification

- Connexion par **nom d'utilisateur OU e-mail** (`UserRepository::loadUserByIdentifier`).
- Authentificateur personnalisé `MainAuthenticator`.
- Protection anti-bruteforce : `login_throttling` (3 tentatives / 2 min).
- « Se souvenir de moi » (cookie 1 an).

### Hiérarchie des rôles (`role_hierarchy`)

```
ROLE_SUPER_ADMIN
 └─ ROLE_FONDATEUR              (supervision, limité à ses établissements)
     └─ ROLE_ADMIN             (accès complet à un établissement)
         ├─ ROLE_DIRECTEUR     (Académique, Notes & Évaluations, Absences)
         │   ├─ ROLE_ENSEIGNANT
         │   └─ ROLE_EDUCATEUR
         ├─ ROLE_CAISSE        (Gestion financière) → hérite ROLE_INSCRIPTION
         │   └─ ROLE_RECOUVREMENT
         ├─ ROLE_INSCRIPTION   (Gestion des élèves)
         ├─ ROLE_CORRESPONDANT_FICHIER → hérite ROLE_INSCRIPTION
         └─ ROLE_RH            (Ressources Humaines)

ROLE_PARENT  → espace parent uniquement
```

| Rôle                        | Périmètre fonctionnel |
|-----------------------------|-----------------------|
| `ROLE_SUPER_ADMIN`          | Sommet de la hiérarchie, tout établissement |
| `ROLE_FONDATEUR`            | Supervision (validation caisses, autorisations, versements) sur ses établissements |
| `ROLE_ADMIN`                | Administration complète d'un établissement |
| `ROLE_DIRECTEUR`            | Académique + Notes/Évaluations + Absences |
| `ROLE_ENSEIGNANT`           | Ses cours, ses élèves, ses évaluations |
| `ROLE_EDUCATEUR`            | Saisie/suivi des absences |
| `ROLE_INSCRIPTION`          | Gestion des élèves (préinscriptions, inscriptions) |
| `ROLE_CORRESPONDANT_FICHIER`| Ses documents + gestion des élèves |
| `ROLE_CAISSE`               | Caisse, paiements, frais, bourses, plans (+ élèves + recouvrement) |
| `ROLE_RECOUVREMENT`         | Suivi des soldes et relances |
| `ROLE_RH`                   | Employés et contrats |
| `ROLE_PARENT`               | Espace parent (ses enfants) |

> ⚠️ Après modification de `role_hierarchy`, exécuter `php bin/console cache:clear`.

### Contrôle d'accès (URL)

- `/login`, `/webhook` → public.
- `/parent/*` → `ROLE_PARENT`.
- tout le reste `/` → `ROLE_USER` (utilisateur connecté).

---

## 3. Contexte établissement & année scolaire

**Fichiers** : `src/Service/SchoolContextService.php`, `src/EventSubscriber/SchoolContextSubscriber.php`,
`src/Doctrine/Filter/SchoolFilter.php`, `src/Controller/ContextController.php`.

- L'**établissement courant** et l'**année scolaire courante** sont mémorisés en session.
- `SchoolContextService` fournit `getCurrentSchool()` / `setCurrentSchool()` et
  `getCurrentSchoolYear()` ; le dernier établissement utilisé est persisté sur le `User`
  (`lastSchool`).
- Un utilisateur peut être rattaché à **plusieurs établissements** (`User.schools` ManyToMany) et
  basculer de l'un à l'autre (`context_switch`).
- Le `SchoolFilter` (filtre Doctrine) applique automatiquement la restriction par établissement
  sur les requêtes, garantissant le cloisonnement des données.

---

## 4. Modules fonctionnels

Les modules ci-dessous correspondent aux sections de la barre latérale
(`templates/base.html.twig`), affichées dynamiquement selon les rôles.

---

### Module — Administration (structure)

> **Rôle requis** : `ROLE_ADMIN`

Gère le socle organisationnel de la plateforme.

| Sous-module | Entité | Contrôleur | Description |
|-------------|--------|------------|-------------|
| Groupes d'établissements | `SchoolGroup` | `SchoolGroupController` | Regroupe plusieurs établissements (réseau scolaire) |
| Établissements | `School` | `SchoolController` | Fiche établissement : nom, code, **type** (`PRESCOLAIRE-PRIMAIRE`, `SECONDAIRE GENERAL`, `TECHNIQUE ET PROFESSIONNEL`, `UNIVERSITE`), logo, cachet, directeur, tutelle |
| Années scolaires | `SchoolYear` | `SchoolYearController` | Année + ses `Period` (trimestres/semestres) |
| Utilisateurs | `User` | `UserController` | Comptes, rôles, rattachement établissements |

- **School** : `logo` et `cachetDirection` gérés en upload ; `badgeBackgroundColor` pour la charte
  visuelle ; rattachement optionnel à un `SchoolGroup`.
- **SchoolYear** : contient les **périodes** (`Period`) servant aux évaluations, bulletins et
  finances.

---

### Module — Configuration (référentiel pédagogique)

> **Rôle requis** : `ROLE_ADMIN`

Paramétrage de la structure pédagogique de l'établissement.

| Sous-module | Entité | Contrôleur | Description |
|-------------|--------|------------|-------------|
| Cycles | `Cycle` | `CycleController` | Cycle d'enseignement (ex. maternelle, primaire…) regroupant niveaux/filières/séries |
| Niveaux | `Level` | `LevelController` | Niveaux/classes types (ordonnés via `orderNumber`), rattachés à un cycle |
| Filières | `Faculty` | `FacultyController` | Filières (secondaire technique, université) |
| Séries / Tours | `Round` | `RoundController` | Séries ou tours rattachés à un cycle |
| Salles | `Room` | `RoomController` | Salles physiques |
| Créneaux horaires | `TimeSlot` | `TimeSlotController` | Plages horaires pour l'emploi du temps |
| Matières | `Subject` | `SubjectController` | Matière (coefficient, type, heures/sem, ordre bulletin, couleur) |
| Types de matière | `SubjectType` | `SubjectTypeController` | Catégorisation des matières |
| Matières équivalentes | `SubjectEquivalent` | `SubjectEquivalentController` | Équivalences entre matières |
| Types de document | `DocumentType` | `DocumentTypeController` | Pièces exigées à la préinscription |
| Types d'absence | `AbsenceType` | (via Gestion) | Catégories d'absences |

**Hiérarchie pédagogique** : `Cycle → Level → Classroom`, avec `Faculty` et `Round` pour les
établissements à filières/séries. `Subject` est rattaché à un `Level`.

---

### Module — Gestion des Élèves

> **Rôle requis** : `ROLE_INSCRIPTION` (et au-dessus)

Cœur du parcours élève : de la demande d'inscription à la scolarité effective.

#### Entités

| Entité | Rôle |
|--------|------|
| `Student` | **Référentiel d'identité** de l'élève (état civil, contacts parent, infos médicales, matricules). Ne porte **aucune** information de scolarité. |
| `PreRegistration` | Demande de (pré)inscription pour une année donnée (`status`: `pending`/validé/rejeté/inscrit) |
| `PreRegistrationDocument` | Pièces jointes d'une préinscription |
| `Registration` | **Inscription annuelle** : rattache un élève à une `School` + `SchoolYear` + `Classroom`. Source de vérité de la scolarité (année/classe/niveau/statut) |
| `StudentDropout` | Abandon / déperdition d'un élève (motif, validation) |
| `StudentTransfer` | Transfert d'élève |
| `StudentFee` | Frais affectés à l'élève pour une inscription |

#### Contrôleurs

- `PreRegistrationController` / `PreRegistrationDocumentController` — gestion des demandes et pièces.
- `RegistrationController` — inscriptions (validation → inscription).
- `StudentController` — référentiel élèves (liste **toutes années confondues**).
- `StudentDropoutController` — abandons.

#### Workflow d'inscription (règle métier centrale)

```
1. PRÉINSCRIPTION (PreRegistration, status = pending)
   ├─ Nouvel élève  → saisie complète de l'identité
   └─ Ancien élève  → réutilise un Student existant (existingStudent)
2. VALIDATION  (validatedBy / validatedAt) ou REJET (rejectionReason)
3. INSCRIPTION → EnrollmentService.enrollFromPreRegistration(...)
   ├─ Nouvel élève → crée le Student (référentiel) puis sa Registration
   └─ Ancien élève → réutilise le Student, met à jour les champs modifiables,
                     crée une NOUVELLE Registration (sans duplication)
```

Services impliqués :
- **`EnrollmentService`** — orchestre l'inscription depuis une préinscription validée ; garantit
  **une seule inscription par élève et par année** et conserve l'historique.
- **`RegistrationManager`** — crée/met à jour l'inscription (année ↔ classe ↔ statut) ; unique
  source de vérité du rattachement scolaire.
- **`PreRegistrationFactory`** — construction des préinscriptions.
- **`MatriculeGenerator`** / **`CodeGenerator`** — génération des matricules et codes.

> 📌 **Règle de portée année** : (pré)inscription = par année scolaire ; la **liste des élèves**
> agrège toutes les années.

---

### Module — Académique

> **Rôle requis** : `ROLE_DIRECTEUR` (et `ROLE_ENSEIGNANT`/`ROLE_EDUCATEUR` pour leurs vues)

Organisation des classes et de l'emploi du temps.

| Sous-module | Entité | Contrôleur | Description |
|-------------|--------|------------|-------------|
| Classes | `Classroom` | `ClassroomController` | Classe d'une année (niveau, filière, série, capacité, prof principal, salle) |
| Cours / emploi du temps | `Course` | `CourseController` | Affecte une matière + enseignant + créneau + salle à une classe |
| Périodes | `Period` | `PeriodController` | Trimestres/semestres d'une année scolaire |

**Espace Enseignant** (`ROLE_ENSEIGNANT`) :
- *Mes Cours* (`admin_course_my_schedule`) — emploi du temps personnel.
- *Mes élèves* (`admin_course_my_students`) — élèves des classes enseignées.
- *Évaluations* — saisie des notes.

---

### Module — Notes & Évaluations

> **Rôle requis** : `ROLE_DIRECTEUR` / `ROLE_ENSEIGNANT`

Évaluation des élèves et production des bulletins.

#### Entités

| Entité | Rôle |
|--------|------|
| `Evaluation` | Devoir/composition (classe, matière, période, enseignant, barème `maxGrade`, coefficient) |
| `Grade` | Note d'un élève à une évaluation (valeur, statut, appréciation, saisie par) |
| `GeneratedBulletin` | Trace d'un bulletin généré (classe, période, année, par qui) |

#### Contrôleurs

- `EvaluationController` — création d'évaluations et saisie des notes.
- `BulletinController` — génération et impression des bulletins.
- `AcademicReportController` — rapports académiques (statistiques, répartitions, majors, affectés/non affectés).

#### Service métier — `GradeCalculationService`

- `calculateStudentAveragesForPeriod()` — moyennes par matière pondérées par coefficient.
- `calculateClassRanking()` — rang de l'élève dans sa classe.
- `generateBulletinData()` / `generateBulletinSheet()` — données complètes du bulletin officiel
  (ligne par matière : moyenne, coef, moy×coef, rang, professeur, appréciation, totaux,
  statistiques de classe, **mention**).

Les bulletins sont rendus en **PDF (Dompdf)**. L'appréciation peut être générée par IA
(voir [Module IA](#module--intelligence-artificielle)).

---

### Module — Gestion (Absences / Assiduité)

> **Rôle requis** : `ROLE_EDUCATEUR` (et au-dessus)

| Entité | Rôle |
|--------|------|
| `Absence` | Absence d'un élève (date, plage horaire, motif, justification + statut/justificatif) |
| `AbsenceType` | Type d'absence |

- `AbsenceController` — saisie, justification (workflow `justificationStatus`: pending → validé), pièces jointes.
- **`AttendanceService`** :
  - statistiques d'assiduité par élève / classe / établissement,
  - taux de présence,
  - rapport d'assiduité détaillé,
  - détection des élèves à **assiduité critique** (seuil paramétrable, défaut 75 %).

---

### Module — Ressources Humaines

> **Rôle requis** : `ROLE_RH` (et au-dessus)

| Entité | Contrôleur | Description |
|--------|------------|-------------|
| `Employee` | `EmployeeController` | Dossier employé (type, poste, département, salaire, dates d'embauche/fin) |
| `Contract` | `ContractController` | Contrat (type, intitulé, période d'essai, salaire de base, heures/sem, statut) |
| `Teacher` | `TeacherController` | Profil enseignant lié à un employé |

- Un `Employee` peut être lié à un compte `User` et à un `Teacher`.
- **`UserEmployeeService`** — synchronise le compte utilisateur et la fiche employé.
- Un `Employee` possède 0..n `Contract` (cascade persist/remove).

---

### Module — Finances (Caisse & Frais)

> **Rôle requis** : `ROLE_CAISSE` (hérité par `ROLE_ADMIN`)

Gestion complète de la facturation, des encaissements et des dépenses.

#### Entités

| Entité | Rôle |
|--------|------|
| `Fee` | Frais/rubrique (montant, catégorie : `scolarite`/`article`/`autre_frais`, type : `pour_tous`/affecté/non affecté, fréquence) rattaché à une `School` et éventuellement un `Level` |
| `FeeSchedule` | Échéancier d'un frais (échéances ordonnées, montant, date d'échéance) |
| `StudentFee` | Frais affecté à un élève pour une inscription (montant dû, payé, statut) |
| `Payment` | Paiement (numéro, montant, méthode, statut, reçu PDF, infos passerelle en ligne, caisse, enregistré par) |
| `CashRegister` | Caisse (caissier, ouverture/fermeture, solde, validation fondateur, autorisation) |
| `CashDeposit` | Versement de caisse (réf, montant, validé par le fondateur) |
| `Depense` | Dépense (numéro, libellé, catégorie, bénéficiaire, méthode) imputée à une caisse |

#### Contrôleurs

- `FeeController` — rubriques de frais + échéanciers (`admin_fee_schedule`).
- `PaymentController` — enregistrement et suivi des paiements ; reçus PDF par élève.
- `CashRegisterController` — ouverture/fermeture/validation des caisses.
- `DepenseController` — saisie des dépenses.

#### Services

- **`FeeAssignmentService`** :
  - `assignScolariteFeesForRegistration()` — affecte les frais de scolarité applicables au statut
    de l'inscription,
  - `syncScolariteFeesForRegistration()` — réadapte les frais selon le statut affecté/non affecté
    (sans retirer les frais déjà payés),
  - `assignScolariteFeeToAllStudents()` — à la création d'un frais « scolarité », l'affecte à tous
    les élèves du niveau.
- **`PaymentReceiptService`** — génère le **reçu PDF** (relevé détaillé par frais et par échéance,
  imputation en cascade des plus anciennes échéances, montant **en toutes lettres** + « francs CFA »).

#### Règle de solde (caisse)

```
Solde caisse = Paiements encaissés − Versements approuvés (CashDeposit) − Dépenses (Depense)
```

> 📌 L'ancienne entité `FinancialTransaction` a été supprimée : les flux passent désormais par
> `Payment`, `CashDeposit` (versements validés par le fondateur) et `Depense`.

---

### Module — Recouvrement

> **Rôle requis** : `ROLE_RECOUVREMENT` (hérité par `ROLE_CAISSE` → `ROLE_ADMIN`)

Suivi des impayés et relances.

- `RecouvrementController` — tableau de bord des soldes (à jour / en retard), relances.
- **`RecouvrementService`** :
  - construit les lignes de recouvrement par élève (dû, payé, solde, statut, montant échu,
    nombre d'échéances échues, date d'échéance la plus ancienne, jours de retard, prochaine échéance),
  - filtrage optionnel par **catégorie** de frais et par **année scolaire**,
  - le statut de relance s'appuie sur les **échéanciers** (`FeeSchedule`) : seules les échéances
    dépassées et non couvertes constituent un « montant échu impayé ».

---

### Module — Paiement en ligne (GeniusPay / Mobile Money)

> **Espace parent** + **webhook public**

Permet aux parents de régler les frais en ligne via la passerelle **GeniusPay** (Mobile Money).

**Configuration** : `MobileMoneyConfig` (par établissement) + variables d'environnement
`GENIUSPAY_BASE_URL`, `GENIUSPAY_API_KEY`, `GENIUSPAY_API_SECRET`, `GENIUSPAY_WEBHOOK_SECRET`,
`GENIUSPAY_WEBHOOK_VERIFY`. Contrôleurs : `MobileMoneyConfigController`, `MobileMoneyLogController`.

**Services (`src/Service/Payment/`)** :

| Service | Rôle |
|---------|------|
| `GeniusPayClient` | Appels API passerelle : `createPayment()`, `getPaymentStatus()` |
| `GeniusPayCredentialsProvider` | Fournit les identifiants (par établissement) |
| `GeniusPaySignatureVerifier` | Vérifie la signature des webhooks |
| `PaymentInitiator` | `initiate(StudentFee, montant, parent)` — démarre un paiement et renvoie l'URL de checkout |
| `PaymentStatusSynchronizer` | Synchronise le statut d'un paiement avec la passerelle |
| `WebhookProcessor` | Traite le callback (`handle(rawBody, signature, timestamp)`) |
| `OnlineCashRegisterProvider` | Caisse virtuelle « en ligne » par établissement |

**Flux** :

```
Parent (espace parent) ──initiate()──► GeniusPay ──► checkoutUrl (redirection)
                                          │
        Webhook public /webhook ◄─────────┘ (callback signé)
                                          │
        WebhookProcessor.handle() ──► met à jour Payment + StudentFee
```

Entités liées : `Payment` (champs `provider`, `providerTransactionId`, `providerStatus`,
`payerPhone`, `checkoutUrl`, `idempotencyKey`), `PaymentWebhookEvent` (journal des callbacks).
Contrôleurs : `Portal/ParentPaymentApiController`, `Webhook/GeniusPayWebhookController`.

---

### Module — Espace Parent

> **Rôle requis** : `ROLE_PARENT`

Portail dédié aux parents pour le suivi de leurs enfants.

- `Portal/ParentPortalController` — tableau de bord, sélection d'enfant et d'année.
- **`ParentPortalService`** :
  - `getChildren()` — enfants rattachés au parent (`Student.parentUser`),
  - `getAcademicReport()` — notes et moyennes,
  - `getAttendanceReport()` — assiduité,
  - `getFinancialReport()` — situation financière,
  - `getDashboard()` — synthèse.
- **`ParentContextService`** — contexte (enfant/année) côté parent.
- Réinscription en ligne d'un ancien élève par son parent → crée une `PreRegistration` en `pending`.
- Paiement en ligne des frais (voir module GeniusPay).

---

### Module — Espace Fondateur

> **Rôle requis** : `ROLE_FONDATEUR`

Supervision multi-établissements.

- `FondateurController` :
  - `fondateur_index` — tableau de bord,
  - `fondateur_validations` — **validation des caisses** (clôtures),
  - `fondateur_autorisations` — autorisations,
  - `fondateur_versements` — validation des **versements** (`CashDeposit`).
- Le fondateur valide/autorise les opérations de caisse (`CashRegister.validatedBy`,
  `authorizedBy`) et approuve les versements (`CashDeposit.approvedBy`).

---

### Module — Communication & Notifications

| Entité | Contrôleur / Service |
|--------|----------------------|
| `Notification` | `NotificationController`, `NotificationService` |

- **`NotificationService`** :
  - `notify(user, titre, message, lien, icône)` — notification individuelle,
  - `notifyRole(role, …)` — notifie tous les utilisateurs actifs d'un rôle.
- Notifications internes affichées dans la barre supérieure (badge non lus, marquage lu).
- E-mails via Symfony Mailer (`MAILER_DSN`).

---

### Module — Rapports

> **Rôle requis** : selon le rapport (admin / direction)

- `ReportController` — rapports de synthèse établissement.
- `AcademicReportController` — statistiques académiques (répartition, majors, affectés/non affectés).
- Exports **PDF** (Dompdf) et **Excel** (PhpSpreadsheet).
- Synthèses pouvant être rédigées par IA (`ReportAIService`).

---

### Module — Ressources / Documents

- Gestionnaire de fichiers **elFinder** + éditeur **CKEditor** intégrés.
- `DocumentType` définit les pièces exigées (préinscription, dossiers).
- Upload géré par **VichUploaderBundle** (logos, cachets, photos, justificatifs, reçus).

---

### Module — Intelligence Artificielle

> Activable via `AI_ENABLED` ; nécessite `ANTHROPIC_API_KEY`. Modèle configuré par `AI_MODEL`.

Intégration de l'**API Anthropic Claude** pour assister plusieurs modules.

**Service de base — `AIService`** (`src/Service/AI/`)
- Appelle l'API Claude (`https://api.anthropic.com/v1/messages`).
- `ask()` (avec cache, TTL `AI_CACHE_TTL`) / `askWithoutCache()`.
- `isEnabled()`, plafond `AI_MAX_TOKENS`.

**Services spécialisés** :

| Service | Usage | Module |
|---------|-------|--------|
| `BulletinAIService` | Génère l'appréciation d'un élève à partir de ses notes (ton adapté au niveau) | Notes & Évaluations |
| `AttendanceAIService` | Analyse les absences d'un élève (stats + recommandations) | Absences |
| `ReportAIService` | Rédige une synthèse de rapport d'établissement | Rapports |
| `ChatbotAIService` | Assistant conversationnel contextualisé selon le type d'utilisateur | Assistant (`AIController`) |

Contrôleur : `AIController` (routes `ai_*`).

---

## 5. Modèle de données (relations principales)

```
SchoolGroup 1───n School 1───n SchoolYear 1───n Period
                    │
                    ├──n Cycle 1──n Level 1──n Subject
                    │              │
                    │              └──n Classroom ──n Course (Subject+Teacher+TimeSlot+Room)
                    │
                    ├──n Fee 1──n FeeSchedule
                    ├──n CashRegister 1──n CashDeposit / Depense / Payment
                    └──n User (ManyToMany)

Student (référentiel)
   ├──n PreRegistration ──1 Registration ──1 Classroom/SchoolYear
   ├──n StudentFee ──n Payment
   ├──n Grade ──1 Evaluation ──1 Period
   ├──n Absence ──1 AbsenceType
   └──1 parentUser (User ROLE_PARENT)

Employee 1──n Contract     Employee 1──1 Teacher / User
```

**Principe architectural clé** : `Student` = identité pure. Toute la **scolarité**
(année, classe, niveau, statut, boursier, frais) est portée par **`Registration`**, accessible
via la préinscription d'origine (`Registration → PreRegistration → Student` ou `existingStudent`).

---

## 6. Configuration & environnement

Variables d'environnement (`.env`) :

| Variable | Rôle |
|----------|------|
| `APP_ENV`, `APP_DEBUG`, `APP_SECRET` | Environnement Symfony |
| `DATABASE_URL` | Connexion MySQL |
| `MESSENGER_TRANSPORT_DSN` | File de messages (async) |
| `MAILER_DSN` | Envoi d'e-mails |
| `ANTHROPIC_API_KEY`, `AI_MODEL`, `AI_MAX_TOKENS`, `AI_CACHE_TTL`, `AI_ENABLED` | Module IA |
| `GENIUSPAY_BASE_URL`, `GENIUSPAY_API_KEY`, `GENIUSPAY_API_SECRET`, `GENIUSPAY_WEBHOOK_SECRET`, `GENIUSPAY_WEBHOOK_URL`, `GENIUSPAY_WEBHOOK_VERIFY` | Paiement en ligne |

**Initialisation** : `DefaultDataInitializer` crée les données par défaut au démarrage.

**Commandes utiles** :
```bash
php bin/console doctrine:migrations:migrate   # appliquer les migrations
php bin/console cache:clear                   # vider le cache (obligatoire après changement de rôles)
php bin/console debug:router                  # lister les routes
```

---

## 7. Organisation du code

```
src/
├── Controller/        44 contrôleurs (admin_*, parent_*, fondateur_*, ai_*, webhook)
│   ├── Concern/       Traits réutilisables (upload, suppression d'entité)
│   ├── Portal/        Espace parent (portail + API paiement)
│   └── Webhook/       Callback GeniusPay (public)
├── Entity/            41 entités Doctrine
├── Service/           29 services métier
│   ├── AI/            Intégration Claude (bulletins, absences, rapports, chatbot)
│   └── Payment/       Passerelle GeniusPay (initiation, webhook, sync)
├── Form/              34 formulaires Symfony
├── Repository/        Requêtes Doctrine
├── Security/          Authentificateur
├── EventSubscriber/   Contexte établissement
└── Doctrine/Filter/   Cloisonnement multi-établissement

templates/             209 templates Twig (1 dossier par module)
migrations/            18 migrations
```

---

## 8. Documents annexes

Le dépôt contient d'autres notes : `CHANGELOG.md`, `INSTALL.md`, `DEPLOIEMENT.md`,
`COMMANDS.md`, `MODULES_SUMMARY.md`, `NOUVELLE_ARCHITECTURE.md`, `PROJECT_STATUS.md`.

---

*Documentation générée par analyse du code source (Symfony 6.4). Pour toute évolution du modèle
de rôles, du workflow d'inscription ou du modèle financier, mettre à jour les sections
correspondantes.*
