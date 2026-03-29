# 🎓 EDU-SCHOOL - Système de Gestion Scolaire Intégré

## 📋 Vue d'ensemble

EDU-SCHOOL est un système de gestion scolaire complet conçu pour gérer tous les niveaux d'enseignement :
- 🧸 **Maternelle**
- 📚 **Primaire** 
- 📖 **Collège**
- 🎯 **Lycée**
- 🎓 **Grande École / Université**

## 🚀 Technologies utilisées

### Backend
- **Framework** : Symfony 6.4 (PHP 8.1+)
- **Base de données** : MySQL/MariaDB avec Doctrine ORM
- **Architecture** : MVC (Model-View-Controller)

### Frontend
- **Template Engine** : Twig
- **Asset Management** : Symfony Asset Mapper
- **UI Framework** : Stimulus & Turbo (Hotwire)
- **Éditeur WYSIWYG** : CKEditor
- **Gestionnaire de fichiers** : elFinder

### Fonctionnalités techniques
- 📧 **Mailing** : Symfony Mailer + PHPMailer
- 📄 **Génération PDF** : DomPDF
- 📊 **Export Excel** : PhpSpreadsheet
- 🔐 **Sécurité** : Symfony Security avec authentification personnalisée
- 📱 **QR Code** : Endroid QR Code
- 🔄 **Messaging** : Symfony Messenger
- 📝 **Pagination** : KnpPaginatorBundle

## 📁 Structure du projet

```
edu-school/
├── assets/              # Fichiers frontend (JS, CSS, images)
├── bin/                 # Exécutables (console Symfony)
├── config/              # Configuration de l'application
│   ├── packages/        # Configuration des bundles
│   └── routes/          # Configuration des routes
├── migrations/          # Migrations de base de données
├── public/              # Point d'entrée web
├── src/
│   ├── Command/         # Commandes console
│   ├── Controller/      # Contrôleurs
│   │   └── Api/         # API REST
│   ├── Entity/          # Entités Doctrine
│   ├── Form/            # Formulaires Symfony
│   ├── Repository/      # Repositories Doctrine
│   ├── Security/        # Authentification et sécurité
│   ├── Service/         # Services métier
│   └── EventSubscriber/ # Écouteurs d'événements
├── templates/           # Vues Twig
├── tests/               # Tests unitaires et fonctionnels
├── translations/        # Fichiers de traduction
└── var/                 # Cache, logs, sessions
```

## 🔧 Installation

### Prérequis
- PHP 8.1 ou supérieur
- Composer
- MySQL/MariaDB
- Extensions PHP : `gd`, `zip`, `intl`, `pdo_mysql`

### Étapes d'installation

1. **Cloner le projet**
```bash
git clone [url-du-repo]
cd edu-school
```

2. **Installer les dépendances**
```bash
composer install
```

3. **Configurer l'environnement**
```bash
cp .env .env.local
# Éditer .env.local avec vos paramètres
```

4. **Configurer la base de données**
```env
DATABASE_URL="mysql://user:password@127.0.0.1:3306/edu_school?serverVersion=8.0"
```

5. **Créer la base de données**
```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

6. **Charger les données de test (optionnel)**
```bash
php bin/console doctrine:fixtures:load
```

7. **Installer les assets**
```bash
php bin/console assets:install
php bin/console importmap:install
php bin/console ckeditor:install
php bin/console elfinder:install
```

8. **Lancer le serveur**
```bash
symfony server:start
# ou
php -S localhost:8000 -t public/
```

## 👤 Système d'utilisateurs

### Hiérarchie des rôles

```
ROLE_USER              → Utilisateur de base
├── ROLE_SAISIE        → Saisie de données
├── ROLE_IMPRESSION    → Impression de documents
│   └── ROLE_MODIFICATION → Modification des données
│       └── ROLE_VALIDATION → Validation des données
│           └── ROLE_ADMIN → Administration
│               └── ROLE_SUPER_ADMIN → Super administrateur
```

### Types d'utilisateurs
- **Administrateur** : Gestion complète du système
- **Directeur d'établissement** : Gestion d'un établissement
- **Enseignant** : Gestion des notes, absences, cours
- **Personnel administratif** : Gestion administrative
- **Élève/Étudiant** : Consultation des notes, emplois du temps
- **Parent** : Suivi de la scolarité de l'enfant

## 📚 Modules fonctionnels

### 1. 🎯 Gestion des établissements
- Création et gestion de multiples établissements
- Configuration par niveau scolaire
- Gestion des années scolaires
- Paramétrage des cycles et filières

### 2. 👥 Gestion des utilisateurs
- Inscription et authentification
- Gestion des profils
- Attribution des rôles et permissions
- Système de "Remember Me"
- Limitation des tentatives de connexion

### 3. 📖 Gestion académique
- **Classes et groupes**
  - Création des classes par niveau
  - Affectation des élèves
  - Gestion des effectifs
  
- **Matières et programmes**
  - Définition des matières par niveau
  - Coefficients et unités d'enseignement
  - Programmes pédagogiques

- **Emploi du temps**
  - Planification des cours
  - Gestion des salles
  - Attribution des professeurs

### 4. 📊 Gestion des notes et évaluations
- Saisie des notes par période
- Calcul automatique des moyennes
- Gestion des coefficients
- Bulletins et relevés de notes
- Statistiques et classements

### 5. 📅 Gestion des absences
- Enregistrement des absences
- Justificatifs
- Retards
- Rapports d'assiduité

### 6. 💰 Gestion financière
- Frais de scolarité
- Paiements et échéanciers
- Bourses et aides
- Facturation
- États financiers

### 7. 📚 Bibliothèque
- Gestion du catalogue
- Prêts et retours
- Réservations
- Inventaire

### 8. 🏥 Infirmerie
- Dossiers médicaux
- Suivi sanitaire
- Vaccinations
- Visites médicales

### 9. 🚌 Transport scolaire
- Circuits et arrêts
- Inscriptions au transport
- Gestion des bus

### 10. 🍽️ Cantine
- Menus
- Inscriptions
- Régimes spéciaux
- Facturation

### 11. 📱 Communication
- Messagerie interne
- Notifications (email, SMS)
- Annonces et actualités
- Calendrier des événements

### 12. 📄 Documents et rapports
- Génération de PDF
- Exports Excel
- Bulletins de notes
- Attestations et certificats
- Cartes d'étudiant avec QR Code
- Statistiques et tableaux de bord

## 🔐 Sécurité

### Authentification
- Système de login sécurisé
- Protection CSRF
- Limitation des tentatives de connexion (3 tentatives / 2 minutes)
- Session "Remember Me" (1 an)

### Contrôle d'accès
- Firewall Symfony
- Rôles hiérarchiques
- ACL sur les routes
- Validation des formulaires

### Protection des données
- Hachage des mots de passe (algorithme auto)
- Validation des entrées utilisateur
- Protection contre les injections SQL (Doctrine ORM)
- Protection XSS (Twig auto-escape)

## 🌐 API REST

L'application expose une API REST pour l'intégration externe :

```
Préfixe : /api
Authentification : Token JWT (à configurer)
Format : JSON
```

### Endpoints disponibles
```
GET    /api/statistics          # Statistiques (PUBLIC)
GET    /api/students            # Liste des élèves
GET    /api/students/{id}       # Détails d'un élève
POST   /api/students            # Créer un élève
PUT    /api/students/{id}       # Modifier un élève
DELETE /api/students/{id}       # Supprimer un élève
```

## 🧪 Tests

```bash
# Tests unitaires
php bin/phpunit

# Tests avec coverage
php bin/phpunit --coverage-html var/coverage
```

## 📝 Commandes utiles

```bash
# Vider le cache
php bin/console cache:clear

# Créer une entité
php bin/console make:entity

# Créer un contrôleur
php bin/console make:controller

# Créer un formulaire
php bin/console make:form

# Créer une migration
php bin/console make:migration

# Lister les routes
php bin/console debug:router

# Vérifier la sécurité
php bin/console security:check
```

## 📈 Évolutions futures

- [ ] Application mobile (React Native / Flutter)
- [ ] Tableau de bord analytics avancé
- [ ] Intégration vidéoconférence
- [ ] E-learning intégré
- [ ] IA pour suggestions pédagogiques
- [ ] Reconnaissance faciale
- [ ] Blockchain pour certificats

## 📞 Support

Pour toute question ou problème :
- 📧 Email : support@edu-school.com
- 📚 Documentation : [docs.edu-school.com]
- 🐛 Issues : [GitHub Issues]

## 📄 Licence

Propriétaire - Tous droits réservés

## 👥 Contributeurs

- Équipe de développement EDU-SCHOOL

---

**Version** : 1.0.0  
**Dernière mise à jour** : Octobre 2025

