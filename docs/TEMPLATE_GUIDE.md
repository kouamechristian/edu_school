# 🎨 Guide du Template EDU-SCHOOL

## 📋 Vue d'ensemble

Le template `base.html.twig` est le template principal de l'application EDU-SCHOOL. Il fournit une interface moderne, responsive et professionnelle avec :

- ✅ Sidebar (menu latéral) responsive
- ✅ Topbar (barre supérieure) avec notifications
- ✅ Navigation dynamique selon les rôles
- ✅ Design moderne avec Bootstrap 5
- ✅ Font Awesome Icons
- ✅ Animations et transitions
- ✅ Support mobile

## 🎯 Utilisation

### Créer une nouvelle page

```twig
{% extends 'base.html.twig' %}

{% block title %}Titre de votre page{% endblock %}

{% block body %}
<div class="container-fluid">
    <h1>Contenu de votre page</h1>
</div>
{% endblock %}
```

### Ajouter du CSS personnalisé

```twig
{% extends 'base.html.twig' %}

{% block stylesheets %}
    <style>
        .custom-class {
            color: red;
        }
    </style>
{% endblock %}

{% block body %}
    <!-- Votre contenu -->
{% endblock %}
```

### Ajouter du JavaScript

```twig
{% extends 'base.html.twig' %}

{% block body %}
    <!-- Votre contenu -->
{% endblock %}

{% block javascripts %}
    <script>
        console.log('Mon script personnalisé');
    </script>
{% endblock %}
```

## 🎨 Composants disponibles

### 1. Cards Statistiques

```twig
<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card primary h-100">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-uppercase text-primary fw-bold text-xs mb-1">
                            Titre
                        </div>
                        <div class="h5 mb-0 fw-bold text-gray-800">850</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
```

**Variantes disponibles** : `primary`, `success`, `info`, `warning`, `danger`

### 2. Alertes

```twig
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i>
    Message de succès
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
```

**Types** : `success`, `info`, `warning`, `danger`, `primary`

### 3. Tables

```twig
<div class="table-responsive">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Colonne 1</th>
                <th>Colonne 2</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Donnée 1</td>
                <td>Donnée 2</td>
            </tr>
        </tbody>
    </table>
</div>
```

### 4. Boutons

```twig
<button class="btn btn-primary">
    <i class="fas fa-plus me-2"></i> Ajouter
</button>

<button class="btn btn-success btn-sm">
    <i class="fas fa-check me-2"></i> Valider
</button>

<div class="btn-group" role="group">
    <button class="btn btn-info">
        <i class="fas fa-eye"></i>
    </button>
    <button class="btn btn-warning">
        <i class="fas fa-edit"></i>
    </button>
    <button class="btn btn-danger">
        <i class="fas fa-trash"></i>
    </button>
</div>
```

## 🎨 Variables CSS personnalisées

Le template utilise des variables CSS pour faciliter la personnalisation :

```css
:root {
    --primary-color: #4e73df;
    --secondary-color: #858796;
    --success-color: #1cc88a;
    --info-color: #36b9cc;
    --warning-color: #f6c23e;
    --danger-color: #e74a3b;
    --sidebar-width: 260px;
    --topbar-height: 60px;
}
```

Pour modifier ces couleurs, surchargez-les dans votre bloc `{% block stylesheets %}`.

## 📱 Responsive Design

### Breakpoints Bootstrap 5

- **xs** : < 576px (mobile)
- **sm** : ≥ 576px (mobile large)
- **md** : ≥ 768px (tablette)
- **lg** : ≥ 992px (desktop)
- **xl** : ≥ 1200px (large desktop)
- **xxl** : ≥ 1400px (très large desktop)

### Classes utiles

```html
<!-- Visible uniquement sur mobile -->
<div class="d-block d-md-none">Mobile uniquement</div>

<!-- Masqué sur mobile -->
<div class="d-none d-md-block">Desktop uniquement</div>

<!-- Colonnes responsive -->
<div class="col-12 col-md-6 col-lg-4">
    Contenu adaptatif
</div>
```

## 🎯 Navigation (Sidebar)

### Ajouter un élément de menu

Dans `templates/base.html.twig`, ajoutez dans la section `<ul class="sidebar-nav">` :

```twig
<li class="nav-item">
    <a href="{{ path('votre_route') }}" class="nav-link {{ app.request.get('_route') starts with 'votre_route' ? 'active' : '' }}">
        <i class="fas fa-icone"></i>
        <span>Nom du menu</span>
    </a>
</li>
```

### Ajouter une section

```twig
<div class="nav-section">Nom de la section</div>
```

### Menu conditionnel par rôle

```twig
{% if is_granted('ROLE_ADMIN') %}
    <li class="nav-item">
        <a href="#" class="nav-link">
            <i class="fas fa-cog"></i>
            <span>Administration</span>
        </a>
    </li>
{% endif %}
```

## 🔔 Notifications (Topbar)

### Modifier le nombre de notifications

Dans `templates/base.html.twig`, trouvez :

```twig
<span class="notification-badge">3</span>
```

Pour rendre dynamique :

```twig
<span class="notification-badge">{{ notification_count }}</span>
```

Et passez la variable depuis le contrôleur :

```php
return $this->render('votre_template.html.twig', [
    'notification_count' => 5,
]);
```

## 👤 Menu Utilisateur

Le menu utilisateur affiche :
- Avatar avec initiales
- Nom d'utilisateur
- Email
- Liens : Profil, Paramètres, Déconnexion

Pour personnaliser l'avatar :

```twig
<div class="user-avatar">
    {{ app.user ? app.user.username|slice(0,2)|upper : 'AD' }}
</div>
```

## 📊 Graphiques (Charts.js)

Le template inclut Chart.js pour les graphiques. Exemple d'utilisation :

```twig
{% block body %}
<canvas id="myChart"></canvas>
{% endblock %}

{% block javascripts %}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('myChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Fév', 'Mar'],
            datasets: [{
                label: 'Données',
                data: [12, 19, 3],
                backgroundColor: '#4e73df'
            }]
        }
    });
</script>
{% endblock %}
```

## 🎨 Icônes Font Awesome

### Icônes fréquemment utilisées

```html
<!-- Général -->
<i class="fas fa-home"></i>           <!-- Accueil -->
<i class="fas fa-search"></i>         <!-- Recherche -->
<i class="fas fa-plus"></i>           <!-- Ajouter -->
<i class="fas fa-edit"></i>           <!-- Modifier -->
<i class="fas fa-trash"></i>          <!-- Supprimer -->
<i class="fas fa-eye"></i>            <!-- Voir -->

<!-- Scolaire -->
<i class="fas fa-school"></i>         <!-- École -->
<i class="fas fa-graduation-cap"></i> <!-- Diplôme -->
<i class="fas fa-user-graduate"></i>  <!-- Élève -->
<i class="fas fa-chalkboard-teacher"></i> <!-- Enseignant -->
<i class="fas fa-book"></i>           <!-- Livre -->
<i class="fas fa-clipboard-list"></i> <!-- Notes -->

<!-- Gestion -->
<i class="fas fa-calendar"></i>       <!-- Calendrier -->
<i class="fas fa-clock"></i>          <!-- Horaire -->
<i class="fas fa-envelope"></i>       <!-- Message -->
<i class="fas fa-bell"></i>           <!-- Notification -->
<i class="fas fa-wallet"></i>         <!-- Finance -->
```

[Voir toutes les icônes](https://fontawesome.com/icons)

## ⚡ JavaScript inclus

### Toggle Sidebar

```javascript
document.getElementById('sidebarToggle').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('collapsed');
});
```

### Auto-dismiss Alerts

Les alertes se ferment automatiquement après 5 secondes.

### Animations

Les cards reçoivent une animation d'entrée automatique grâce à la classe `.animate-in`.

## 🎨 Classes utilitaires personnalisées

```css
.stat-card        /* Card avec barre latérale colorée */
.nav-section      /* Section dans le menu */
.topbar-link      /* Lien dans la topbar */
.user-avatar      /* Avatar utilisateur */
.notification-badge /* Badge de notification */
```

## 📝 Exemple complet

```twig
{% extends 'base.html.twig' %}

{% block title %}Ma Page Personnalisée{% endblock %}

{% block stylesheets %}
    <style>
        .custom-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
    </style>
{% endblock %}

{% block body %}
<div class="container-fluid">
    {# En-tête #}
    <div class="custom-header">
        <h1><i class="fas fa-star me-2"></i> Ma Page</h1>
        <p>Description de ma page</p>
    </div>

    {# Statistiques #}
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card stat-card primary">
                <div class="card-body">
                    <div class="text-uppercase text-primary fw-bold mb-1">
                        Total
                    </div>
                    <div class="h5 mb-0">850</div>
                </div>
            </div>
        </div>
    </div>

    {# Contenu principal #}
    <div class="card shadow">
        <div class="card-header">
            <h5 class="mb-0">Titre de la section</h5>
        </div>
        <div class="card-body">
            <p>Votre contenu ici</p>
        </div>
    </div>
</div>
{% endblock %}

{% block javascripts %}
    <script>
        console.log('Page chargée !');
    </script>
{% endblock %}
```

## 🔧 Dépannage

### Le sidebar ne s'affiche pas

Vérifiez que la route `app_home` existe dans votre routeur.

### Les icônes ne s'affichent pas

Vérifiez que Font Awesome CDN est bien chargé :
```html
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
```

### Le dropdown ne fonctionne pas

Vérifiez que Bootstrap JS est bien chargé :
```html
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
```

## 📚 Ressources

- [Bootstrap 5 Documentation](https://getbootstrap.com/docs/5.3/)
- [Font Awesome Icons](https://fontawesome.com/icons)
- [Chart.js Documentation](https://www.chartjs.org/docs/latest/)
- [Twig Documentation](https://twig.symfony.com/)

---

**Version** : 1.0  
**Dernière mise à jour** : Octobre 2025  
**Auteur** : Équipe EDU-SCHOOL

