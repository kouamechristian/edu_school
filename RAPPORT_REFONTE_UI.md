# Rapport de refonte de l'interface — EDU-SCHOOL

**Charte retenue :** « Navy + Émeraude » (identité académique premium)
**Étendue de cette passe :** Fondation (charte + composants) + pages clés + guide de déploiement
**Stack :** Symfony 6.4 · Twig · Bootstrap 5.3 (CDN) · Font Awesome 6 · Chart.js

---

## 1. Synthèse des améliorations

### Identité visuelle
- **Nouvelle charte de couleurs institutionnelles** : navy `#1e3a5f` (primaire), émeraude `#10b981` (accent), or `#f59e0b`, avec des sémantiques cohérentes (info `#0ea5e9`, warning `#f59e0b`, danger `#ef4444`, purple `#7c3aed`).
- **Typographie professionnelle bi-police** : `Poppins` pour les titres (autorité), `Inter` pour le corps (lisibilité écran). Chargées en une seule requête Google Fonts avec `preconnect`.
- **Système de tokens CSS** (`--es-*`) : couleurs, rayons (`--es-radius*`), ombres (`--es-shadow*`), transitions — une échelle unique réutilisée partout, fini les valeurs magiques éparpillées.
- **Mapping des variables Bootstrap** (`--bs-primary`, `--bs-primary-rgb`, …) : tous les utilitaires `.text-*`, `.bg-*`, `.border-*`, `.text-bg-*` sont recolorés automatiquement sans toucher au CDN.

### Composants réutilisables (Twig)
- En‑tête de page **`page_header`** (embeddable) avec icône, sous‑titre, fil d'Ariane et bloc `actions`.
- **Fil d'Ariane** (`breadcrumb`), **cartes statistiques** (`card_stat` + variante manuelle `.stat-card`), **raccourcis** (`quick_action`), **lignes d'information** (`info_row` / `.info-list`) pour les fiches, **badges de statut doux** (`status_badge`), **badge de rôle** (`badge_role`), **avatar** (`avatar`), **actions de tableau** (`table_actions`), **état vide** (`empty_state`), **toasts** (`alert_flash`), **modale de suppression** (`delete_modal`).

### Expérience & responsive
- Cartes, tableaux, formulaires, boutons et badges harmonisés (rayons, ombres, états de survol/focus homogènes).
- **Animations légères** : apparition des cartes au scroll, compteurs animés, transitions de survol — **désactivées automatiquement** via `prefers-reduced-motion` (accessibilité).
- **Responsive ordinateur / tablette / mobile** : grilles fluides, en‑têtes qui se réorganisent, `info-list` qui passe en colonne, sidebar en overlay sur mobile, masquage intelligent des sélecteurs de contexte.
- **Thème sombre** entièrement repensé sur la nouvelle palette (navy nuit `#0f1729`).

### Performance & nettoyage
- Suppression de la règle morte `body { background: skyblue }` (`assets/styles/app.css`).
- Suppression de **CSS inline dupliqué** dans les pages (ex. `student/index` : bloc `<style>` `border-left-*` / `text-xs` retiré au profit des classes de la charte).
- Scrollbars, ombres et rendus consolidés dans **un seul fichier** `public/css/styles.css` (chargé pour un rendu critique immédiat).
- `preconnect` sur les domaines de polices pour réduire la latence de premier rendu.

---

## 2. Arborescence des fichiers (modifiés / créés)

```
edu_school/
├── public/
│   └── css/
│       └── styles.css                      [RÉÉCRIT]  Charte complète + tokens + composants + dark + responsive
├── assets/
│   └── styles/
│       └── app.css                         [NETTOYÉ]  Règle morte « skyblue » supprimée
├── templates/
│   ├── base.html.twig                      [MODIFIÉ]  Sidebar/topbar navy, police Inter, palette avatars, accent émeraude
│   ├── components/
│   │   ├── macros.html.twig                [RÉÉCRIT]  Bibliothèque de macros (rétro‑compatible)
│   │   └── page_header.html.twig           [CRÉÉ]     En‑tête de page embeddable (titre + breadcrumb + actions)
│   ├── security/
│   │   └── login.html.twig                 [RÉÉCRIT]  Page de connexion premium (split navy + halos)
│   ├── home/
│   │   └── index.html.twig                 [MODIFIÉ]  En‑tête composant + couleurs Chart.js charte
│   ├── student_space/
│   │   └── dashboard.html.twig             [RÉÉCRIT]  Dashboard élève (stat‑cards, accès rapide, info‑list)
│   └── student/
│       ├── index.html.twig                 [RÉÉCRIT]  Liste (stat‑cards, filtres, tableau, empty‑state)
│       └── show.html.twig                  [RÉÉCRIT]  Fiche détaillée (profile‑hero + info‑list)
└── RAPPORT_REFONTE_UI.md                   [CRÉÉ]     Ce document
```

Validation : `php bin/console lint:twig` → **8/8 fichiers OK**.

---

## 3. Bibliothèque de composants — mode d'emploi

### En‑tête de page (avec actions et fil d'Ariane)
```twig
{% embed 'components/page_header.html.twig' with {
    title: 'Gestion des Matières',
    subtitle: 'Programme et coefficients',
    icon: 'fa-book',
    color: 'primary',
    breadcrumb: [
        { label: 'Académique', path: path('admin_subject_index') },
        { label: 'Matières' }
    ]
} %}
    {% block actions %}
        <a href="{{ path('admin_subject_new') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> Nouvelle matière
        </a>
    {% endblock %}
{% endembed %}
```
> Sans actions : `{% embed 'components/page_header.html.twig' with { title: '…' } %}{% endembed %}`

### Macros
```twig
{% from 'components/macros.html.twig' import card_stat, quick_action,
   info_row, status_badge, table_actions, empty_state, breadcrumb %}

{{ card_stat('Élèves', 1280, 'fa-user-graduate', 'success', 12) }}
{{ quick_action(path('admin_payment_index'), 'fa-credit-card', 'Paiements', 'info') }}
{{ status_badge('Payé', 'success', 'fa-check') }}
{{ table_actions('admin_subject_edit', 'admin_subject_delete', subject.id, 'admin_subject_show') }}
{{ empty_state('fa-inbox', 'Aucune donnée', 'Commencez par créer un élément.') }}
```

### Fiche détaillée (lignes d'information)
```twig
<div class="info-list">
    <div class="info-row"><div class="info-label">Nom</div><div class="info-value">{{ entity.name }}</div></div>
    <div class="info-row"><div class="info-label">Statut</div>
        <div class="info-value"><span class="badge bg-{{ entity.statusColor }}">{{ entity.statusLabel }}</span></div></div>
</div>
```
> Pour une valeur purement textuelle : `{{ info_row('Téléphone', entity.phone) }}`.

### Tableau pleine largeur
```twig
<div class="card table-card">
    <div class="card-header"><h6 class="m-0 fw-bold text-primary">Titre</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">…</table>
        </div>
    </div>
</div>
```

### Classes utilitaires ajoutées
`.stat-card[.primary|.success|.info|.warning|.danger]`, `.badge-soft[.success|…]`,
`.bg-soft-primary|success|info|warning|danger`, `.card-accent[.success|…]`,
`.quick-action-card`, `.empty-state`, `.profile-hero` / `.profile-avatar`,
`.breadcrumb-modern`, `.page-header*`, `.text-accent`, `.bg-purple` / `.text-purple`.

---

## 4. Guide de déploiement sur le reste du projet

Le système est en place ; appliquer le reste est mécanique et sans risque fonctionnel. Pour chaque vue d'un module (`new`, `edit`, `index`, `show`) :

**A. Pages `index` (listes)**
1. Remplacer l'en‑tête `<div class="d-sm-flex … mb-4"><h1 …></h1>…</div>` par le composant `page_header` (bloc `actions` pour les boutons).
2. Remplacer les cartes de stats `border-left-*` par `.stat-card`.
3. Envelopper le tableau dans `.card.table-card` + `.table.table-hover.align-middle`.
4. Remplacer la ligne « Aucun … » par `empty_state(...)`.
5. Supprimer tout `<style>` inline `border-left-*` / `text-xs` (désormais dans la charte).

**B. Pages `show` (fiches)**
1. En‑tête → `page_header` (avec `breadcrumb` + actions Modifier/Retour).
2. Bandeau `.profile-hero` si l'entité a un « titre » (nom, libellé).
3. Convertir les blocs `<p><strong>Label :</strong> valeur</p>` en `.info-list` / `.info-row`.

**C. Pages `new` / `edit` (formulaires)**
1. En‑tête → `page_header`.
2. Formulaire dans une `.card` ; libellés en `.form-label`, champs Bootstrap standard (déjà stylés par la charte). Penser à `{{ form_themes }}` si Symfony Forms.

**Ordre de priorité suggéré** (du plus visible au moins visible) :
`pre_registration` → `evaluation` / `bulletin` → `fee` / `payment` / `invoice` → `classroom` / `course` / `subject` → `school` / `school_year` / `level` → `user` → modules restants.

**Recette qualité** après chaque module :
```bash
php bin/console lint:twig templates/<module>
```
Vérifier visuellement : clair + sombre, largeurs ordinateur / tablette (768px) / mobile (375px).

---

## 5. Points d'attention / suite possible

- **Boutons Bootstrap** : recolorés via les variables `--bs-btn-*` dans `styles.css`. Si une version future de Bootstrap est auto‑hébergée, conserver ces overrides.
- **Données factices** : certains dashboards (`home/index`, notifications de la topbar) contiennent encore des valeurs d'exemple (activités, événements, compteurs `3`/`5`). À brancher sur de vraies sources quand disponibles — hors périmètre UI.
- **Liens `href="#"`** (Messages, Annonces, Bibliothèque…) : placeholders existants conservés tels quels.
- **Optimisation avancée (optionnelle)** : héberger Bootstrap/FA/Chart.js via AssetMapper + purge CSS pour supprimer les dépendances CDN et réduire le poids.

---

*Document généré dans le cadre de la refonte de l'interface. La fondation (charte + composants) couvre l'intégralité du projet ; les pages clés servent de modèles de référence pour le déploiement décrit en section 4.*
