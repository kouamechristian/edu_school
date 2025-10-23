# 👥 Module 2 - Gestion des Utilisateurs

## 📋 Vue d'ensemble

Ce module gère tous les utilisateurs du système EDU-SCHOOL avec un système complet d'authentification, de rôles et de permissions.

## ✨ Fonctionnalités

### 1. Gestion des Utilisateurs

- ✅ Création, modification, suppression d'utilisateurs
- ✅ 6 types d'utilisateurs (Admin, Directeur, Enseignant, Personnel, Élève, Parent)
- ✅ Gestion des profils complets
- ✅ Upload d'avatar
- ✅ Activation/Désactivation des comptes

### 2. Authentification Sécurisée

- ✅ Login avec username ou email
- ✅ Hachage sécurisé des mots de passe (bcrypt/argon2)
- ✅ Protection CSRF
- ✅ Système "Remember Me" (1 an)
- ✅ Limitation des tentatives de connexion (3 tentatives / 2 minutes)
- ✅ Suivi des connexions

### 3. Gestion des Rôles et Permissions

- ✅ Hiérarchie de rôles
- ✅ Attribution multiple de rôles
- ✅ Contrôle d'accès par route
- ✅ 7 niveaux de rôles

### 4. Gestion des Profils

- ✅ Informations personnelles complètes
- ✅ Coordonnées
- ✅ Historique d'activité
- ✅ Dernière connexion

## 👤 Types d'Utilisateurs

### 1. 👨‍💼 Administrateur (`admin`)
**Rôles** : `ROLE_SUPER_ADMIN` ou `ROLE_ADMIN`

**Pouvoirs** :
- Gestion complète du système
- Création/modification de tous les utilisateurs
- Configuration globale
- Accès à toutes les statistiques

### 2. 🏫 Directeur (`directeur`)
**Rôle** : `ROLE_ADMIN`

**Pouvoirs** :
- Gestion d'un établissement
- Création des classes et années
- Validation des inscriptions
- Rapports et statistiques

### 3. 👨‍🏫 Enseignant (`enseignant`)
**Rôles** : `ROLE_MODIFICATION` ou `ROLE_VALIDATION`

**Pouvoirs** :
- Saisie des notes
- Gestion des absences
- Consultation de l'emploi du temps
- Communication avec élèves/parents

### 4. 💼 Personnel (`personnel`)
**Rôles** : `ROLE_SAISIE` ou `ROLE_IMPRESSION`

**Pouvoirs** :
- Saisie de données
- Impression de documents
- Gestion administrative
- Consultation limitée

### 5. 🎓 Élève (`eleve`)
**Rôle** : `ROLE_USER`

**Pouvoirs** :
- Consultation des notes
- Visualisation de l'emploi du temps
- Téléchargement de documents
- Messagerie

### 6. 👪 Parent (`parent`)
**Rôle** : `ROLE_USER`

**Pouvoirs** :
- Suivi de la scolarité de l'enfant
- Consultation des notes
- Paiements en ligne
- Communication avec enseignants

## 🔐 Hiérarchie des Rôles

```
ROLE_USER                    → Utilisateur de base
├── ROLE_SAISIE              → Saisie de données
├── ROLE_IMPRESSION          → Impression de documents
│   └── ROLE_MODIFICATION    → Modification des données
│       └── ROLE_VALIDATION  → Validation des données
│           └── ROLE_ADMIN   → Administration
│               └── ROLE_SUPER_ADMIN → Super administrateur
```

### Permissions par rôle

| Rôle | Permissions |
|------|-------------|
| `ROLE_USER` | Consultation uniquement |
| `ROLE_SAISIE` | Saisie de données |
| `ROLE_IMPRESSION` | + Impression de documents |
| `ROLE_MODIFICATION` | + Modification de données |
| `ROLE_VALIDATION` | + Validation de données |
| `ROLE_ADMIN` | + Administration complète |
| `ROLE_SUPER_ADMIN` | + Tous les pouvoirs |

## 🗄️ Structure de la Base de Données

### Table: `user`

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT(PK) | Identifiant unique |
| username | VARCHAR(180) | Nom d'utilisateur (unique) |
| email | VARCHAR(180) | Email (unique) |
| roles | JSON | Tableau des rôles |
| password | VARCHAR(255) | Mot de passe hashé |
| is_active | BOOLEAN | Compte actif |
| last_login | DATETIME | Dernière connexion |
| avatar | VARCHAR(255) | Photo de profil |
| first_name | VARCHAR(100) | Prénom |
| last_name | VARCHAR(100) | Nom |
| phone | VARCHAR(20) | Téléphone |
| address | TEXT | Adresse |
| date_of_birth | DATE | Date de naissance |
| gender | VARCHAR(1) | M ou F |
| created_at | DATETIME | Date de création |
| updated_at | DATETIME | Date de modification |
| user_type | VARCHAR(50) | Type d'utilisateur |

**Index** :
- UNIQUE sur `username`
- UNIQUE sur `email`
- INDEX sur `is_active`
- INDEX sur `user_type`

## 📁 Fichiers du Module

### Entités
- `src/Entity/User.php` - Entité utilisateur complète

### Repositories
- `src/Repository/UserRepository.php` - Requêtes personnalisées

### Contrôleurs
- `src/Controller/UserController.php` - CRUD utilisateurs
- `src/Controller/SecurityController.php` - Authentification

### Formulaires
- `src/Form/UserType.php` - Formulaire utilisateur

### Templates
- `templates/user/index.html.twig` - Liste avec filtres
- `templates/user/new.html.twig` - Création
- `templates/user/edit.html.twig` - Modification
- `templates/user/show.html.twig` - Détails et actions
- `templates/security/login.html.twig` - Page de connexion

### Security
- `src/Security/MainAuthenticator.php` - Authentificateur personnalisé
- `config/packages/security.yaml` - Configuration sécurité

### Migration
- `migrations/Version20251009201500.php` - Table user

### Fixtures
- `src/DataFixtures/Module2Fixtures.php` - Utilisateurs de test

## 🚀 Installation

### 1. Exécuter la migration

```bash
php bin/console doctrine:migrations:migrate
```

### 2. Charger les données de test

```bash
php bin/console doctrine:fixtures:load --append
```

**Utilisateurs créés** :
- 2 Administrateurs
- 2 Directeurs
- 5 Enseignants
- 2 Personnel
- 10 Élèves
- 3 Parents

**Total : 24 utilisateurs**

### 3. Identifiants de test

| Utilisateur | Login | Mot de passe | Rôle |
|-------------|-------|--------------|------|
| Super Admin | `superadmin` | `Admin@123` | ROLE_SUPER_ADMIN |
| Admin | `admin` | `Admin@123` | ROLE_ADMIN |
| Directeur | `directeur1` | `Password@123` | ROLE_ADMIN |
| Enseignant | `jmartin` | `Teacher@123` | ROLE_MODIFICATION |
| Personnel | `secretaire1` | `Staff@123` | ROLE_SAISIE |
| Élève | `lucas.dubois` | `Student@123` | ROLE_USER |
| Parent | `parent1` | `Parent@123` | ROLE_USER |

## 📖 Guide d'utilisation

### Connexion

1. **Accéder à** : `/login`
2. **Saisir** : Username ou Email + Mot de passe
3. **Options** :
   - Cocher "Se souvenir de moi" (session 1 an)
   - Lien "Mot de passe oublié"

### Créer un utilisateur

1. **Accéder à** : `/admin/users`
2. **Cliquer** : "Nouvel Utilisateur"
3. **Remplir le formulaire** :
   
   **Informations de connexion** :
   - Nom d'utilisateur (min 3 caractères, unique)
   - Email (unique)
   - Mot de passe (min 6 caractères)
   
   **Informations personnelles** :
   - Prénom, Nom
   - Genre, Date de naissance
   
   **Type et Permissions** :
   - Type d'utilisateur
   - Rôles (sélection multiple)
   - Statut (Actif/Inactif)
   
   **Coordonnées** :
   - Téléphone
   - Adresse
   
4. **Enregistrer**

### Rechercher des utilisateurs

**Méthodes de recherche** :
- Par nom d'utilisateur
- Par email
- Par prénom/nom
- Par type d'utilisateur

**Filtres disponibles** :
```
Recherche: [________]
Type: [Tous les types ▼]

[🔍 Filtrer] [🔄 Réinitialiser]
```

### Modifier un utilisateur

1. Rechercher l'utilisateur
2. Cliquer sur l'icône "Modifier" (crayon)
3. Modifier les informations
4. **Mot de passe** : Laisser vide pour conserver l'actuel
5. Enregistrer

### Actions sur un utilisateur

**Depuis la page de détails** :

```
┌─────────────────────────────┐
│ Actions                     │
├─────────────────────────────┤
│ [🔑 Réinitialiser MDP]     │  (SUPER_ADMIN uniquement)
│ [🚫 Désactiver]            │
│ [🗑️ Supprimer]              │
└─────────────────────────────┘
```

#### Réinitialiser le mot de passe
- Génère un mot de passe temporaire aléatoire
- Affiche le mot de passe à communiquer à l'utilisateur
- Réservé aux SUPER_ADMIN

#### Activer/Désactiver
- Empêche la connexion sans supprimer le compte
- Impossible de désactiver son propre compte

#### Supprimer
- Supprime définitivement l'utilisateur
- Impossible de supprimer son propre compte

## 🔗 Routes disponibles

```
GET  /login                      → Page de connexion
POST /login                      → Authentification
GET  /logout                     → Déconnexion

GET  /admin/users                → Liste des utilisateurs
GET  /admin/users/new            → Créer un utilisateur
POST /admin/users/new            → Enregistrer un utilisateur
GET  /admin/users/{id}           → Détails d'un utilisateur
GET  /admin/users/{id}/edit      → Modifier un utilisateur
POST /admin/users/{id}/edit      → Enregistrer les modifications
POST /admin/users/{id}           → Supprimer un utilisateur
POST /admin/users/{id}/toggle    → Activer/Désactiver
POST /admin/users/{id}/reset-password → Réinitialiser MDP
```

## 🔒 Sécurité

### Mots de passe

**Politique de mot de passe** :
- Minimum 6 caractères
- Hachage automatique avec algorithme sécurisé (bcrypt/argon2)
- Jamais stocké en clair
- Mise à jour automatique de l'algorithme si nécessaire

**Générer un hash manuellement** :
```bash
php bin/console security:hash-password
```

### Protection CSRF

Tous les formulaires sont protégés contre les attaques CSRF :
```php
$this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))
```

### Limitation des tentatives

Configuration dans `security.yaml` :
```yaml
login_throttling:
    max_attempts: 3
    interval: '2 minutes'
```

## 📊 Statistiques

Le module fournit :
- Nombre total d'utilisateurs
- Répartition par type
- Nombre d'utilisateurs actifs/inactifs
- Dernières inscriptions
- Taux d'activité

## 🔧 Configuration

### security.yaml

```yaml
security:
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'
        App\Entity\User:
            algorithm: auto

    providers:
        app_user_provider:
            entity:
                class: App\Entity\User
                property: username

    firewalls:
        main:
            lazy: true
            provider: app_user_provider
            custom_authenticator: App\Security\MainAuthenticator
            logout:
                path: app_logout
                target: app_login
            remember_me:
                secret: '%env(APP_SECRET)%'
                lifetime: 31536000
            login_throttling:
                max_attempts: 3
                interval: '2 minutes'

    role_hierarchy:
        ROLE_SAISIE: ROLE_USER
        ROLE_IMPRESSION: ROLE_USER
        ROLE_MODIFICATION: ROLE_IMPRESSION
        ROLE_VALIDATION: ROLE_MODIFICATION
        ROLE_ADMIN: [ROLE_VALIDATION]
        ROLE_SUPER_ADMIN: [ROLE_ADMIN, ROLE_ALLOWED_TO_SWITCH]

    access_control:
        - { path: ^/login$, roles: PUBLIC_ACCESS }
        - { path: ^/admin, roles: ROLE_ADMIN }
        - { path: ^/, roles: ROLE_USER }
```

## 🧪 Tests

### Vérifier la création d'utilisateurs

```bash
# Compter les utilisateurs
php bin/console doctrine:query:sql "SELECT COUNT(*) FROM user"

# Voir tous les utilisateurs
php bin/console doctrine:query:sql "SELECT username, email, user_type FROM user"

# Voir les rôles
php bin/console doctrine:query:sql "SELECT username, roles FROM user"
```

### Tester l'authentification

1. Accéder à `/login`
2. Utiliser un des comptes de test
3. Vérifier la redirection
4. Vérifier les permissions selon le rôle

### Tester "Remember Me"

1. Se connecter avec "Se souvenir de moi"
2. Fermer le navigateur
3. Rouvrir → Devrait rester connecté

### Tester la limitation des tentatives

1. Essayer de se connecter 3 fois avec un mauvais mot de passe
2. Au 4ème essai → Blocage pendant 2 minutes

## 💡 Fonctionnalités avancées

### Recherche multi-critères

Le repository fournit une méthode de recherche avancée :

```php
$users = $userRepository->searchByNameOrEmail('martin');
// Recherche dans: username, email, firstName, lastName
```

### Filtrage par rôle

```php
$admins = $userRepository->findByRole('ROLE_ADMIN');
```

### Utilisateurs actifs

```php
$activeUsers = $userRepository->findActive();
$count = $userRepository->countActive();
```

### Derniers inscrits

```php
$latest = $userRepository->findLatest(10); // 10 derniers
```

## 🎨 Interface utilisateur

### Page de liste

**Affichage** :
```
┌────────────────────────────────────────────────────┐
│ Avatar | Nom complet    | Email     | Type  | ... │
├────────────────────────────────────────────────────┤
│ [JM]   | Jean MARTIN    | jean@...  | Prof  | ... │
│        | @jmartin       |           |       |     │
├────────────────────────────────────────────────────┤
│ [SD]   | Sophie DUPRÉ   | sophie@.. | Prof  | ... │
│        | @sdupre        |           |       |     │
└────────────────────────────────────────────────────┘
```

**Actions** :
- 👁️ Voir
- ✏️ Modifier
- 🚫 Activer/Désactiver
- 🗑️ Supprimer

### Page de détails

**Sections** :
1. Avatar et informations de base
2. Contact (email, téléphone, adresse)
3. Informations personnelles (naissance, genre)
4. Rôles et permissions
5. Activité (date d'inscription, dernière connexion)
6. Actions rapides (sidebar droite)

### Formulaire de création/modification

**Organisation en 4 sections** :
1. **Informations de connexion**
   - Username, Email, Mot de passe
2. **Informations personnelles**
   - Prénom, Nom, Genre, Date de naissance
3. **Type et Permissions**
   - Type d'utilisateur, Rôles, Statut
4. **Coordonnées**
   - Téléphone, Adresse

## 🔧 Personnalisation

### Ajouter un nouveau type d'utilisateur

1. **Modifier User.php** :
```php
#[Assert\Choice(choices: ['admin', 'directeur', 'enseignant', 'personnel', 'eleve', 'parent', 'nouveau_type'])]
private ?string $userType = null;
```

2. **Mettre à jour getUserTypeLabel()** :
```php
public function getUserTypeLabel(): string
{
    return match($this->userType) {
        'nouveau_type' => 'Nouveau Type',
        // ... autres types
    };
}
```

3. **Mettre à jour UserType.php** :
```php
'choices' => [
    'Nouveau Type' => 'nouveau_type',
    // ...
]
```

### Ajouter un nouveau rôle

1. **Définir dans security.yaml** :
```yaml
role_hierarchy:
    ROLE_NOUVEAU: ROLE_USER
```

2. **Ajouter dans UserType.php** :
```php
'choices' => [
    'Nouveau Rôle (ROLE_NOUVEAU)' => 'ROLE_NOUVEAU',
    // ...
]
```

## 🐛 Dépannage

### Erreur "Username already exists"

Le nom d'utilisateur doit être unique. Choisissez un autre nom.

### Erreur "Email already exists"

L'email doit être unique. Utilisez un autre email.

### Ne peut pas se connecter

1. Vérifier que le compte est **actif**
2. Vérifier le mot de passe
3. Vérifier qu'il n'y a pas de blocage (3 tentatives)

### Mot de passe oublié

Un administrateur peut :
- Utiliser "Réinitialiser MDP" (génère un temporaire)
- Modifier le compte et définir un nouveau mot de passe

## 📈 Évolutions futures

- [ ] Inscription en ligne (self-registration)
- [ ] Confirmation par email
- [ ] Mot de passe oublié (réinitialisation par email)
- [ ] Authentification à deux facteurs (2FA)
- [ ] Connexion via OAuth (Google, Facebook)
- [ ] Historique des connexions
- [ ] Journalisation des actions utilisateur
- [ ] Import/Export d'utilisateurs (Excel/CSV)
- [ ] Politique de mot de passe personnalisable

## 💡 Bonnes pratiques

1. **Noms d'utilisateur** :
   - Utiliser un format cohérent (ex: prenom.nom)
   - Minimum 3 caractères
   - Pas d'espaces ni caractères spéciaux

2. **Emails** :
   - Un email par utilisateur
   - Format valide obligatoire
   - Utiliser des domaines différents par type (ex: @student.edu-school.com)

3. **Mots de passe** :
   - Minimum 6 caractères (augmentez à 8+ en production)
   - Forcer le changement à la première connexion
   - Expiration régulière (tous les 90 jours)

4. **Rôles** :
   - Principe du moindre privilège
   - Attribuer uniquement les rôles nécessaires
   - Réviser régulièrement les permissions

5. **Désactivation vs Suppression** :
   - Préférer la désactivation pour conserver l'historique
   - Supprimer uniquement en cas d'erreur de saisie

## 🔗 Intégration avec d'autres modules

Ce module est utilisé par :
- **Module 3** - Gestion académique (enseignants, élèves)
- **Module 4** - Notes (lien enseignant-notes)
- **Module 5** - Absences (pointage par enseignants)
- **Module 6** - Finances (paiements des parents)
- **Module 11** - Communication (messagerie entre utilisateurs)

## 📚 API REST

### Endpoints utilisateurs

```
GET    /api/users              # Liste des utilisateurs
GET    /api/users/{id}         # Détails
POST   /api/users              # Créer
PUT    /api/users/{id}         # Modifier
DELETE /api/users/{id}         # Supprimer
POST   /api/auth/login         # Se connecter
POST   /api/auth/refresh       # Rafraîchir le token
```

Voir [API.md](./API.md) pour la documentation complète.

---

**Version** : 1.0  
**Date de création** : Octobre 2025  
**Auteur** : Équipe EDU-SCHOOL

