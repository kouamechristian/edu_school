# 🗺️ Routes et URLs - EDU-SCHOOL

## 🌐 URLs Disponibles

### 🏠 Pages Publiques

```
GET  /                         → Dashboard (redirige vers /login si non connecté)
GET  /login                    → Page de connexion
POST /login                    → Traitement de l'authentification
GET  /logout                   → Déconnexion
```

---

## 👥 Module 2 - Utilisateurs

### Gestion des utilisateurs (ROLE_ADMIN requis)

```
GET  /admin/users                        → Liste des utilisateurs
     ?search=terme                       → Rechercher
     ?type=enseignant                    → Filtrer par type

GET  /admin/users/new                    → Formulaire création utilisateur
POST /admin/users/new                    → Enregistrer nouvel utilisateur

GET  /admin/users/{id}                   → Profil utilisateur
GET  /admin/users/{id}/edit              → Formulaire modification
POST /admin/users/{id}/edit              → Enregistrer modifications

POST /admin/users/{id}                   → Supprimer utilisateur
     _token=csrf_token                   → Protection CSRF requise

POST /admin/users/{id}/toggle            → Activer/Désactiver
     _token=csrf_token                   

POST /admin/users/{id}/reset-password    → Réinitialiser mot de passe
     _token=csrf_token                   → (ROLE_SUPER_ADMIN requis)
```

**Exemples d'URLs** :
```
http://localhost:8000/admin/users
http://localhost:8000/admin/users/new
http://localhost:8000/admin/users/1
http://localhost:8000/admin/users/1/edit
http://localhost:8000/admin/users?type=enseignant
http://localhost:8000/admin/users?search=martin
```

---

## 🏫 Module 1 - Établissements

### Gestion des établissements (ROLE_ADMIN requis)

```
GET  /admin/schools                      → Liste des établissements
GET  /admin/schools/new                  → Formulaire création établissement
POST /admin/schools/new                  → Enregistrer nouvel établissement

GET  /admin/schools/{id}                 → Détails établissement
GET  /admin/schools/{id}/edit            → Formulaire modification
POST /admin/schools/{id}/edit            → Enregistrer modifications

POST /admin/schools/{id}                 → Supprimer établissement
     _token=csrf_token

POST /admin/schools/{id}/toggle          → Activer/Désactiver
     _token=csrf_token
```

**Exemples d'URLs** :
```
http://localhost:8000/admin/schools
http://localhost:8000/admin/schools/new
http://localhost:8000/admin/schools/1
http://localhost:8000/admin/schools/1/edit
```

### Gestion des années scolaires (ROLE_ADMIN requis)

```
GET  /admin/school-years                 → Liste des années scolaires
GET  /admin/school-years/new             → Formulaire création année
POST /admin/school-years/new             → Enregistrer nouvelle année

GET  /admin/school-years/{id}            → Détails année
GET  /admin/school-years/{id}/edit       → Formulaire modification
POST /admin/school-years/{id}/edit       → Enregistrer modifications

POST /admin/school-years/{id}            → Supprimer année
     _token=csrf_token

POST /admin/school-years/{id}/set-current → Définir comme année courante
     _token=csrf_token
```

**Exemples d'URLs** :
```
http://localhost:8000/admin/school-years
http://localhost:8000/admin/school-years/new
http://localhost:8000/admin/school-years/1
```

### Gestion des niveaux (ROLE_ADMIN requis)

```
GET  /admin/levels                       → Liste des niveaux
GET  /admin/levels/new                   → Formulaire création niveau
POST /admin/levels/new                   → Enregistrer nouveau niveau

GET  /admin/levels/{id}                  → Détails niveau
GET  /admin/levels/{id}/edit             → Formulaire modification
POST /admin/levels/{id}/edit             → Enregistrer modifications

POST /admin/levels/{id}                  → Supprimer niveau
     _token=csrf_token

POST /admin/levels/{id}/toggle           → Activer/Désactiver
     _token=csrf_token
```

**Exemples d'URLs** :
```
http://localhost:8000/admin/levels
http://localhost:8000/admin/levels/new
http://localhost:8000/admin/levels/1
```

---

## 🔒 Contrôle d'Accès par URL

### URLs Publiques (PUBLIC_ACCESS)

```
✅ /login                     → Accessible à tous
❌ Toutes les autres          → Authentification requise
```

### URLs Utilisateur (ROLE_USER)

```
✅ /                          → Dashboard
```

### URLs Administration (ROLE_ADMIN)

```
✅ /admin/schools             → Gestion établissements
✅ /admin/school-years        → Gestion années
✅ /admin/levels              → Gestion niveaux
✅ /admin/users               → Gestion utilisateurs
```

### URLs Super Admin (ROLE_SUPER_ADMIN)

```
✅ /admin/users/{id}/reset-password  → Réinitialisation MDP
✅ Toutes les autres routes ADMIN
```

---

## 🎯 Routes Nommées

### Utilisation dans Twig

```twig
{# Lien vers la liste des utilisateurs #}
<a href="{{ path('admin_user_index') }}">Utilisateurs</a>

{# Lien vers un utilisateur spécifique #}
<a href="{{ path('admin_user_show', {'id': user.id}) }}">Voir</a>

{# Lien vers la modification #}
<a href="{{ path('admin_user_edit', {'id': user.id}) }}">Modifier</a>
```

### Utilisation dans PHP

```php
// Redirection vers la liste
return $this->redirectToRoute('admin_user_index');

// Redirection avec paramètres
return $this->redirectToRoute('admin_user_show', [
    'id' => $user->getId()
]);

// Génération d'URL
$url = $this->generateUrl('admin_user_index');
```

---

## 📋 Liste Complète des Routes Nommées

### Authentification
```
app_login                    → /login
app_logout                   → /logout
```

### Dashboard
```
app_home                     → /
```

### Établissements
```
admin_school_index           → /admin/schools
admin_school_new             → /admin/schools/new
admin_school_show            → /admin/schools/{id}
admin_school_edit            → /admin/schools/{id}/edit
admin_school_delete          → /admin/schools/{id} [POST]
admin_school_toggle          → /admin/schools/{id}/toggle [POST]
```

### Années Scolaires
```
admin_school_year_index      → /admin/school-years
admin_school_year_new        → /admin/school-years/new
admin_school_year_show       → /admin/school-years/{id}
admin_school_year_edit       → /admin/school-years/{id}/edit
admin_school_year_delete     → /admin/school-years/{id} [POST]
admin_school_year_set_current → /admin/school-years/{id}/set-current [POST]
```

### Niveaux
```
admin_level_index            → /admin/levels
admin_level_new              → /admin/levels/new
admin_level_show             → /admin/levels/{id}
admin_level_edit             → /admin/levels/{id}/edit
admin_level_delete           → /admin/levels/{id} [POST]
admin_level_toggle           → /admin/levels/{id}/toggle [POST]
```

### Utilisateurs
```
admin_user_index             → /admin/users
admin_user_new               → /admin/users/new
admin_user_show              → /admin/users/{id}
admin_user_edit              → /admin/users/{id}/edit
admin_user_delete            → /admin/users/{id} [POST]
admin_user_toggle            → /admin/users/{id}/toggle [POST]
admin_user_reset_password    → /admin/users/{id}/reset-password [POST]
```

---

## 🔍 Commande pour Voir Toutes les Routes

```bash
# Lister toutes les routes
php bin/console debug:router

# Rechercher une route spécifique
php bin/console debug:router | findstr "user"
php bin/console debug:router | findstr "school"

# Voir les détails d'une route
php bin/console debug:router admin_user_index
```

---

## 🌐 URLs Futures (Modules à venir)

### Module 3 - Académique (À venir)
```
/admin/classrooms            → Gestion des classes
/admin/subjects              → Gestion des matières
/admin/courses               → Gestion des cours
```

### Module 4 - Notes (À venir)
```
/teacher/grades              → Saisie des notes
/student/grades              → Consultation notes
/admin/report-cards          → Bulletins
```

### Module 5 - Absences (À venir)
```
/teacher/attendance          → Pointage absences
/student/attendance          → Consultation absences
/admin/attendance-reports    → Rapports
```

### Module 6 - Finances (À venir)
```
/admin/fees                  → Gestion des frais
/parent/payments             → Paiements
/admin/financial-reports     → Rapports financiers
```

---

## 📱 Navigation Rapide

### Liens directs après connexion

**Administrateur** :
```
Dashboard:          http://localhost:8000/
Établissements:     http://localhost:8000/admin/schools
Années scolaires:   http://localhost:8000/admin/school-years
Niveaux:            http://localhost:8000/admin/levels
Utilisateurs:       http://localhost:8000/admin/users
```

**Enseignant** :
```
Dashboard:          http://localhost:8000/
Mon profil:         http://localhost:8000/profile
```

**Élève/Parent** :
```
Dashboard:          http://localhost:8000/
Mon profil:         http://localhost:8000/profile
```

---

## 🎨 Préfixes de Routes

```
/                           → Public
/login, /logout             → Authentification
/admin/*                    → Administration (ROLE_ADMIN)
/teacher/*                  → Enseignants (à venir)
/student/*                  → Élèves (à venir)
/parent/*                   → Parents (à venir)
/api/*                      → API REST (à venir)
```

---

## 📊 Récapitulatif

```
Routes publiques:      2 routes
Routes admin:         24 routes
Routes user:           1 route
──────────────────────────────
Total actuel:         27 routes
```

**Futur** : ~100+ routes quand tous les modules seront complétés

---

**Dernière mise à jour** : 09 Octobre 2025  
**Version** : 1.0.0

