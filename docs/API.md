# 🌐 Documentation API REST - EDU-SCHOOL

## 📋 Vue d'ensemble

L'API REST d'EDU-SCHOOL permet l'accès programmatique aux données et fonctionnalités du système. Elle suit les principes RESTful et utilise JSON pour l'échange de données.

### URL de base
```
Production: https://api.edu-school.com/api
Développement: http://localhost:8000/api
```

### Format des données
- **Request**: JSON
- **Response**: JSON
- **Encodage**: UTF-8

### Versioning
```
Version actuelle: v1
Pattern: /api/v1/{resource}
```

## 🔐 Authentification

### Méthodes supportées

1. **Token JWT** (Recommandé)
```http
Authorization: Bearer <token>
```

2. **Session Cookie** (Pour web app)
```http
Cookie: PHPSESSID=<session_id>
```

### Obtenir un token

**Endpoint**: `POST /api/auth/login`

**Request**:
```json
{
    "username": "john.doe",
    "password": "password123"
}
```

**Response**:
```json
{
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expires_in": 3600,
    "user": {
        "id": 1,
        "username": "john.doe",
        "email": "john@example.com",
        "roles": ["ROLE_TEACHER"]
    }
}
```

### Rafraîchir un token

**Endpoint**: `POST /api/auth/refresh`

**Request**:
```json
{
    "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

## 📚 Endpoints

### 🎓 Élèves/Étudiants

#### Lister les élèves
```http
GET /api/students
```

**Query Parameters**:
- `page` (int): Numéro de page (défaut: 1)
- `limit` (int): Nombre par page (défaut: 20, max: 100)
- `classroom` (int): Filtrer par classe
- `status` (string): active, graduated, dropped, transferred
- `search` (string): Recherche par nom
- `sort` (string): Champ de tri (défaut: last_name)
- `order` (string): asc, desc (défaut: asc)

**Response**:
```json
{
    "data": [
        {
            "id": 1,
            "registration_number": "STU2024001",
            "first_name": "Marie",
            "last_name": "DUPONT",
            "date_of_birth": "2010-05-15",
            "gender": "F",
            "classroom": {
                "id": 5,
                "name": "6ème A",
                "level": "Collège"
            },
            "status": "active",
            "photo_url": "/uploads/students/photo_1.jpg"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 20,
        "total": 150,
        "last_page": 8
    }
}
```

#### Obtenir un élève
```http
GET /api/students/{id}
```

**Response**:
```json
{
    "id": 1,
    "registration_number": "STU2024001",
    "first_name": "Marie",
    "last_name": "DUPONT",
    "date_of_birth": "2010-05-15",
    "place_of_birth": "Paris",
    "gender": "F",
    "blood_group": "A+",
    "nationality": "Française",
    "address": "15 Rue de la Paix, 75001 Paris",
    "phone": "0612345678",
    "email": "marie.dupont@email.com",
    "emergency_contact": "Mme DUPONT",
    "emergency_phone": "0698765432",
    "classroom": {
        "id": 5,
        "name": "6ème A",
        "level": "Collège",
        "main_teacher": "M. MARTIN"
    },
    "parents": [
        {
            "id": 10,
            "name": "Jean DUPONT",
            "relation": "father",
            "phone": "0612345678",
            "is_primary": true
        }
    ],
    "status": "active",
    "enrollment_date": "2024-09-01",
    "photo_url": "/uploads/students/photo_1.jpg",
    "qr_code_url": "/uploads/qrcodes/student_1.png"
}
```

#### Créer un élève
```http
POST /api/students
```

**Request**:
```json
{
    "registration_number": "STU2024150",
    "first_name": "Pierre",
    "last_name": "MARTIN",
    "date_of_birth": "2011-03-20",
    "place_of_birth": "Lyon",
    "gender": "M",
    "nationality": "Française",
    "address": "25 Avenue Victor Hugo, 69002 Lyon",
    "phone": "0623456789",
    "emergency_contact": "M. MARTIN",
    "emergency_phone": "0634567890",
    "classroom_id": 5,
    "parent_ids": [10, 11]
}
```

**Response**: `201 Created`
```json
{
    "id": 151,
    "registration_number": "STU2024150",
    "message": "Student created successfully"
}
```

#### Mettre à jour un élève
```http
PUT /api/students/{id}
PATCH /api/students/{id}
```

**Request** (PATCH - partiel):
```json
{
    "phone": "0612345679",
    "classroom_id": 6
}
```

**Response**: `200 OK`
```json
{
    "id": 1,
    "message": "Student updated successfully"
}
```

#### Supprimer un élève
```http
DELETE /api/students/{id}
```

**Response**: `204 No Content`

### 👨‍🏫 Enseignants

#### Lister les enseignants
```http
GET /api/teachers
```

**Response**:
```json
{
    "data": [
        {
            "id": 1,
            "employee_number": "TEACH001",
            "first_name": "Jean",
            "last_name": "MARTIN",
            "specialization": "Mathématiques",
            "subjects": ["Mathématiques", "Physique"],
            "status": "active"
        }
    ]
}
```

#### Obtenir un enseignant
```http
GET /api/teachers/{id}
```

#### Emploi du temps d'un enseignant
```http
GET /api/teachers/{id}/schedule
```

**Query Parameters**:
- `week` (string): Semaine (format: YYYY-Wxx)

**Response**:
```json
{
    "teacher": {
        "id": 1,
        "name": "Jean MARTIN"
    },
    "week": "2024-W42",
    "schedule": [
        {
            "day": "Monday",
            "courses": [
                {
                    "id": 10,
                    "subject": "Mathématiques",
                    "classroom": "6ème A",
                    "start_time": "08:00",
                    "end_time": "09:00",
                    "room": "Salle 101"
                }
            ]
        }
    ]
}
```

### 🏫 Classes

#### Lister les classes
```http
GET /api/classrooms
```

**Query Parameters**:
- `school_year` (int): Année scolaire
- `level` (int): Niveau

**Response**:
```json
{
    "data": [
        {
            "id": 5,
            "name": "6ème A",
            "code": "6A",
            "level": "Collège",
            "capacity": 30,
            "current_students": 28,
            "main_teacher": "M. MARTIN",
            "room_number": "101"
        }
    ]
}
```

#### Élèves d'une classe
```http
GET /api/classrooms/{id}/students
```

#### Emploi du temps d'une classe
```http
GET /api/classrooms/{id}/schedule
```

### 📊 Notes

#### Lister les notes d'un élève
```http
GET /api/students/{id}/grades
```

**Query Parameters**:
- `period` (int): Période d'évaluation
- `subject` (int): Matière

**Response**:
```json
{
    "student": {
        "id": 1,
        "name": "Marie DUPONT"
    },
    "period": {
        "id": 1,
        "name": "1er Trimestre"
    },
    "grades": [
        {
            "id": 100,
            "subject": "Mathématiques",
            "grade_value": 15.5,
            "max_grade": 20,
            "coefficient": 3,
            "type": "exam",
            "date": "2024-10-05",
            "teacher": "M. MARTIN"
        }
    ],
    "average": 14.8
}
```

#### Ajouter une note
```http
POST /api/grades
```

**Request**:
```json
{
    "student_id": 1,
    "subject_id": 5,
    "period_id": 1,
    "grade_value": 16.5,
    "max_grade": 20,
    "coefficient": 2,
    "type": "test",
    "description": "Devoir de mathématiques",
    "date": "2024-10-15"
}
```

#### Bulletin de notes
```http
GET /api/students/{id}/report-card
```

**Query Parameters**:
- `period` (int): Période (required)
- `format` (string): json, pdf (défaut: json)

**Response JSON**:
```json
{
    "student": {
        "id": 1,
        "name": "Marie DUPONT",
        "registration_number": "STU2024001",
        "classroom": "6ème A"
    },
    "period": {
        "id": 1,
        "name": "1er Trimestre"
    },
    "subjects": [
        {
            "name": "Mathématiques",
            "average": 15.5,
            "coefficient": 3,
            "teacher": "M. MARTIN",
            "appreciation": "Très bon travail"
        }
    ],
    "general_average": 14.8,
    "rank": 3,
    "total_students": 28,
    "appreciation": "Bon trimestre, continue ainsi."
}
```

**Response PDF**:
- Content-Type: `application/pdf`
- Fichier PDF du bulletin

### 📅 Absences

#### Lister les absences d'un élève
```http
GET /api/students/{id}/attendance
```

**Query Parameters**:
- `start_date` (date): Date de début
- `end_date` (date): Date de fin
- `status` (string): present, absent, late, excused

**Response**:
```json
{
    "student": {
        "id": 1,
        "name": "Marie DUPONT"
    },
    "period": {
        "start_date": "2024-09-01",
        "end_date": "2024-10-15"
    },
    "attendance": [
        {
            "id": 50,
            "date": "2024-10-10",
            "course": "Mathématiques",
            "status": "absent",
            "reason": "Maladie",
            "is_justified": true
        }
    ],
    "statistics": {
        "total_days": 30,
        "present": 27,
        "absent": 2,
        "late": 1,
        "attendance_rate": 90.0
    }
}
```

#### Enregistrer une absence
```http
POST /api/attendance
```

**Request**:
```json
{
    "student_id": 1,
    "course_id": 10,
    "date": "2024-10-15",
    "status": "absent",
    "reason": "Rendez-vous médical"
}
```

### 💰 Paiements

#### Frais d'un élève
```http
GET /api/students/{id}/fees
```

**Response**:
```json
{
    "student": {
        "id": 1,
        "name": "Marie DUPONT"
    },
    "fees": [
        {
            "id": 20,
            "type": "Scolarité",
            "amount": 500.00,
            "paid_amount": 200.00,
            "balance": 300.00,
            "due_date": "2024-11-30",
            "status": "partial"
        }
    ],
    "total_fees": 1000.00,
    "total_paid": 600.00,
    "total_balance": 400.00
}
```

#### Enregistrer un paiement
```http
POST /api/payments
```

**Request**:
```json
{
    "fee_id": 20,
    "student_id": 1,
    "amount": 100.00,
    "payment_date": "2024-10-15",
    "payment_method": "cash",
    "reference": "REF20241015001"
}
```

**Response**:
```json
{
    "id": 150,
    "receipt_number": "REC20241015150",
    "message": "Payment recorded successfully",
    "receipt_url": "/api/payments/150/receipt.pdf"
}
```

### 📊 Statistiques

#### Statistiques générales
```http
GET /api/statistics
```

**Access**: PUBLIC (comme configuré dans security.yaml)

**Response**:
```json
{
    "students": {
        "total": 850,
        "active": 820,
        "by_level": {
            "Maternelle": 150,
            "Primaire": 300,
            "Collège": 250,
            "Lycée": 150
        }
    },
    "teachers": {
        "total": 65,
        "active": 62
    },
    "classrooms": {
        "total": 35,
        "average_capacity": 25
    },
    "financial": {
        "total_fees": 425000.00,
        "total_collected": 380000.00,
        "collection_rate": 89.4
    }
}
```

#### Statistiques d'une classe
```http
GET /api/classrooms/{id}/statistics
```

**Response**:
```json
{
    "classroom": {
        "id": 5,
        "name": "6ème A"
    },
    "students_count": 28,
    "average_grades": {
        "Mathématiques": 14.5,
        "Français": 13.8,
        "Histoire": 15.2
    },
    "attendance_rate": 92.5,
    "gender_distribution": {
        "M": 15,
        "F": 13
    }
}
```

## 🔄 Codes de statut HTTP

### Succès
- `200 OK` - Requête réussie
- `201 Created` - Ressource créée
- `204 No Content` - Succès sans contenu (DELETE)

### Erreurs Client
- `400 Bad Request` - Requête invalide
- `401 Unauthorized` - Non authentifié
- `403 Forbidden` - Accès refusé
- `404 Not Found` - Ressource non trouvée
- `422 Unprocessable Entity` - Validation échouée
- `429 Too Many Requests` - Trop de requêtes

### Erreurs Serveur
- `500 Internal Server Error` - Erreur serveur
- `503 Service Unavailable` - Service indisponible

## ❌ Format des erreurs

```json
{
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "Les données fournies sont invalides",
        "details": {
            "first_name": ["Ce champ est requis"],
            "email": ["Format d'email invalide"]
        },
        "timestamp": "2024-10-15T10:30:00Z"
    }
}
```

### Codes d'erreur

- `AUTHENTICATION_FAILED` - Échec d'authentification
- `AUTHORIZATION_FAILED` - Permissions insuffisantes
- `VALIDATION_ERROR` - Erreur de validation
- `NOT_FOUND` - Ressource non trouvée
- `DUPLICATE_ENTRY` - Entrée dupliquée
- `BUSINESS_LOGIC_ERROR` - Erreur de logique métier
- `RATE_LIMIT_EXCEEDED` - Limite de requêtes dépassée

## 🚀 Rate Limiting

### Limites par endpoint

| Endpoint | Limite | Période |
|----------|--------|---------|
| `/api/auth/*` | 5 | 1 minute |
| `/api/**` (authenticated) | 100 | 1 minute |
| `/api/statistics` (public) | 20 | 1 minute |

### Headers de rate limiting

```http
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1697365200
```

## 📝 Pagination

### Request
```http
GET /api/students?page=2&limit=50
```

### Response Headers
```http
X-Total-Count: 150
X-Page-Count: 3
Link: <https://api.edu-school.com/api/students?page=1&limit=50>; rel="first",
      <https://api.edu-school.com/api/students?page=3&limit=50>; rel="last",
      <https://api.edu-school.com/api/students?page=3&limit=50>; rel="next"
```

## 🔍 Filtrage et Tri

### Opérateurs de filtre
```http
GET /api/students?filter[status]=active&filter[classroom_id]=5
```

### Tri multiple
```http
GET /api/students?sort=last_name,first_name&order=asc,asc
```

### Recherche
```http
GET /api/students?search=Dupont
```

## 📦 Exemples d'utilisation

### JavaScript (Fetch)
```javascript
const response = await fetch('https://api.edu-school.com/api/students', {
    headers: {
        'Authorization': 'Bearer YOUR_TOKEN',
        'Content-Type': 'application/json'
    }
});
const data = await response.json();
```

### PHP (Guzzle)
```php
$client = new \GuzzleHttp\Client();
$response = $client->request('GET', 'https://api.edu-school.com/api/students', [
    'headers' => [
        'Authorization' => 'Bearer YOUR_TOKEN',
        'Accept' => 'application/json'
    ]
]);
$data = json_decode($response->getBody(), true);
```

### Python (Requests)
```python
import requests

headers = {
    'Authorization': 'Bearer YOUR_TOKEN',
    'Content-Type': 'application/json'
}
response = requests.get('https://api.edu-school.com/api/students', headers=headers)
data = response.json()
```

### cURL
```bash
curl -X GET "https://api.edu-school.com/api/students" \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Accept: application/json"
```

## 🧪 Environnement de test

**URL**: `https://api-sandbox.edu-school.com/api`

**Credentials de test**:
```
Username: test@edu-school.com
Password: TestPassword123!
```

## 📚 Ressources

- **Postman Collection**: [Download](https://api.edu-school.com/docs/postman)
- **OpenAPI Spec**: [Download](https://api.edu-school.com/docs/openapi.yaml)
- **SDK**: Disponible pour PHP, JavaScript, Python

---

**Version API** : 1.0  
**Dernière mise à jour** : Octobre 2025  
**Support** : api-support@edu-school.com

