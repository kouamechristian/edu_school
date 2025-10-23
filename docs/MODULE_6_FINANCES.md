# 💰 Module 6 - Gestion Financière

## 📋 Vue d'ensemble

Le Module 6 - Gestion Financière d'EDU-SCHOOL permet de gérer tous les aspects financiers d'un établissement scolaire, incluant les frais de scolarité, les paiements, les factures, les plans de paiement, les bourses et les transactions financières.

## 🏗️ Architecture du Module

### Entités Principales

#### 1. **Fee** - Frais de Scolarité
- **Table** : `fee`
- **Description** : Définit les différents types de frais (scolarité, inscription, transport, etc.)
- **Relations** : ManyToOne vers School, Level ; OneToMany vers Payment, Invoice

#### 2. **Payment** - Paiements
- **Table** : `payment`
- **Description** : Enregistre les paiements effectués par les élèves
- **Relations** : ManyToOne vers Student, Fee ; OneToMany vers FinancialTransaction

#### 3. **Invoice** - Factures
- **Table** : `invoice`
- **Description** : Gère les factures émises aux élèves
- **Relations** : ManyToOne vers Student, Fee ; OneToMany vers Payment

#### 4. **PaymentPlan** - Plans de Paiement
- **Table** : `payment_plan`
- **Description** : Permet d'établir des plans de paiement échelonnés
- **Relations** : ManyToOne vers Student, Fee ; OneToMany vers Payment

#### 5. **Scholarship** - Bourses et Aides
- **Table** : `scholarship`
- **Description** : Gère les bourses et aides financières accordées aux élèves
- **Relations** : ManyToOne vers Student, User

#### 6. **FinancialTransaction** - Transactions Financières
- **Table** : `financial_transaction`
- **Description** : Enregistre toutes les transactions financières du système
- **Relations** : ManyToOne vers Student, Payment, Invoice, Scholarship

## 📊 Fonctionnalités

### 🎯 Gestion des Frais

#### Types de Frais Supportés
- **Obligatoires** : Frais de scolarité, inscription, uniforme, etc.
- **Optionnels** : Transport, cantine, activités sportives
- **Pénalités** : Frais de retard, sanctions financières

#### Fréquences de Paiement
- **Unique** : Paiement en une seule fois
- **Mensuel** : Paiement mensuel
- **Trimestriel** : Paiement par trimestre
- **Annuel** : Paiement annuel

#### Fonctionnalités Avancées
- Système de remises (pourcentage ou montant fixe)
- Calcul automatique du montant final
- Gestion des échéances
- Suivi des frais en retard

### 💳 Gestion des Paiements

#### Méthodes de Paiement
- **Espèces** : Paiement en liquide
- **Chèque** : Paiement par chèque
- **Virement** : Virement bancaire
- **Carte bancaire** : Paiement par carte
- **Mobile Money** : Paiement mobile (Orange Money, MTN, etc.)

#### Statuts de Paiement
- **En attente** : Paiement en cours de traitement
- **Payé** : Paiement confirmé
- **Partiellement payé** : Paiement partiel
- **Annulé** : Paiement annulé
- **Remboursé** : Paiement remboursé

#### Fonctionnalités
- Génération automatique de numéros de paiement
- Suivi des références de transaction
- Gestion des reçus
- Historique complet des paiements

### 📄 Gestion des Factures

#### Types de Factures
- **Brouillon** : Facture en cours de création
- **Envoyée** : Facture envoyée au client
- **Payée** : Facture entièrement payée
- **Partiellement payée** : Facture partiellement réglée
- **En retard** : Facture non payée à l'échéance
- **Annulée** : Facture annulée

#### Fonctionnalités
- Génération automatique de numéros de facture
- Calcul automatique des montants restants
- Système de remises et taxes
- Génération de PDF (en développement)
- Suivi des échéances

### 📅 Plans de Paiement

#### Caractéristiques
- **Échelonnement** : Paiement réparti sur plusieurs échéances
- **Fréquences** : Mensuel, trimestriel, semestriel, annuel
- **Flexibilité** : Nombre d'échéances personnalisable
- **Suivi** : Progression du plan de paiement

#### Statuts
- **Actif** : Plan en cours d'exécution
- **Suspendu** : Plan temporairement arrêté
- **Terminé** : Plan entièrement exécuté
- **Annulé** : Plan annulé

### 🎓 Gestion des Bourses

#### Types de Bourses
- **Montant fixe** : Bourse d'un montant déterminé
- **Pourcentage** : Bourse basée sur un pourcentage des frais
- **Gratuité totale** : Exonération complète des frais

#### Fonctionnalités
- Calcul automatique des remises
- Gestion des sponsors/organismes
- Conditions d'attribution
- Suivi des bourses actives/expirées

### 💼 Transactions Financières

#### Types de Transactions
- **Entrée** : Recettes, paiements reçus
- **Sortie** : Dépenses, remboursements
- **Transfert** : Mouvements internes

#### Catégories
- **Paiement** : Paiements d'élèves
- **Remboursement** : Remboursements effectués
- **Bourse** : Attribution de bourses
- **Frais** : Frais administratifs
- **Autre** : Autres transactions

## 🎨 Interface Utilisateur

### Pages Principales

#### 1. **Gestion des Frais** (`/admin/fees`)
- Liste des frais avec filtres
- Statistiques en temps réel
- Actions : Créer, Modifier, Supprimer, Activer/Désactiver
- Vue des frais en retard et échéances proches

#### 2. **Gestion des Paiements** (`/admin/payments`)
- Liste des paiements avec filtres avancés
- Suivi des paiements en attente
- Confirmation/Annulation des paiements
- Historique des paiements récents

#### 3. **Gestion des Factures** (`/admin/invoices`)
- Liste des factures avec statuts
- Génération de nouvelles factures
- Suivi des factures en retard
- Gestion des échéances

### Fonctionnalités d'Interface

#### Tableaux Interactifs
- **Filtres dynamiques** : Par type, statut, méthode, date
- **Recherche** : Par numéro, nom, référence
- **Tri** : Par date, montant, statut
- **Pagination** : Gestion des grandes listes

#### Statistiques Visuelles
- **Cartes de résumé** : Totaux, compteurs, montants
- **Graphiques** : Évolution des paiements
- **Indicateurs** : Frais en retard, échéances proches
- **Couleurs** : Code couleur pour les statuts

#### Actions Contextuelles
- **Boutons d'action** : Voir, Modifier, Supprimer
- **Actions rapides** : Confirmer, Annuler, Marquer comme payé
- **Formulaires modaux** : Actions sans rechargement de page
- **Confirmations** : Protection contre les suppressions accidentelles

## 🔧 Configuration et Utilisation

### Installation

1. **Exécuter la migration** :
```bash
php bin/console doctrine:migrations:migrate
```

2. **Charger les données de test** :
```bash
php bin/console doctrine:fixtures:load --group=Module6
```

3. **Accéder aux fonctionnalités** :
   - Aller sur `/admin/fees` pour gérer les frais
   - Aller sur `/admin/payments` pour gérer les paiements
   - Aller sur `/admin/invoices` pour gérer les factures

### Configuration des Frais

1. **Créer un nouveau frais** :
   - Nom et code du frais
   - Établissement et niveau concerné
   - Montant et type (obligatoire/optionnel/pénalité)
   - Fréquence de paiement
   - Dates de début, fin et échéance
   - Remises éventuelles

2. **Gérer les remises** :
   - Remise en pourcentage
   - Remise en montant fixe
   - Calcul automatique du montant final

### Gestion des Paiements

1. **Enregistrer un paiement** :
   - Sélectionner l'élève et le frais
   - Saisir le montant et la date
   - Choisir la méthode de paiement
   - Ajouter une référence si nécessaire
   - Définir le statut

2. **Suivre les paiements** :
   - Filtrer par statut, méthode, date
   - Rechercher par numéro ou référence
   - Confirmer les paiements en attente
   - Annuler les paiements si nécessaire

### Gestion des Factures

1. **Créer une facture** :
   - Sélectionner l'élève et le frais
   - Définir le montant total
   - Ajouter des remises ou taxes
   - Définir la date d'émission et d'échéance
   - Définir le statut initial

2. **Suivre les factures** :
   - Marquer comme payée
   - Envoyer les factures
   - Suivre les échéances
   - Gérer les retards

## 📈 Rapports et Statistiques

### Statistiques Disponibles

#### Tableaux de Bord
- **Totaux financiers** : Montants collectés, en attente, en retard
- **Répartition par méthode** : Espèces, chèques, virements, etc.
- **Évolution temporelle** : Paiements par jour/semaine/mois
- **Statuts** : Répartition des paiements par statut

#### Rapports Spécifiques
- **Frais en retard** : Liste des frais non payés à l'échéance
- **Échéances proches** : Frais arrivant à échéance sous 7 jours
- **Paiements récents** : Activité récente des paiements
- **Bourses actives** : Suivi des bourses en cours

### Export de Données

#### Formats Supportés
- **PDF** : Factures, reçus, rapports
- **Excel** : Export des données pour analyse
- **CSV** : Données brutes pour traitement externe

## 🔒 Sécurité et Permissions

### Rôles Requis
- **ROLE_ADMIN** : Accès complet à toutes les fonctionnalités
- **ROLE_FINANCE** : Gestion des paiements et factures
- **ROLE_ACCOUNTING** : Consultation des rapports financiers

### Sécurité des Données
- **Validation** : Tous les montants et données sont validés
- **Audit** : Traçabilité des modifications
- **Sauvegarde** : Historique des transactions
- **Chiffrement** : Protection des données sensibles

## 🚀 Évolutions Futures

### Fonctionnalités Prévues
- **Intégration bancaire** : Connexion directe aux comptes bancaires
- **Paiement en ligne** : Portail de paiement pour les parents
- **Notifications** : Alertes par email/SMS pour les échéances
- **Mobile Money** : Intégration avec les opérateurs mobiles
- **QR Codes** : Codes QR pour paiements rapides
- **Blockchain** : Traçabilité des transactions

### Améliorations Techniques
- **API REST** : Interface pour applications externes
- **Webhooks** : Notifications en temps réel
- **Analytics** : Tableaux de bord avancés
- **IA** : Prédiction des impayés
- **Blockchain** : Certification des diplômes et paiements

## 📞 Support et Maintenance

### Commandes Utiles

```bash
# Vider le cache
php bin/console cache:clear

# Recalculer les statistiques
php bin/console app:recalculate-financial-stats

# Générer les rapports
php bin/console app:generate-financial-reports

# Nettoyer les données
php bin/console app:cleanup-financial-data
```

### Logs et Monitoring
- **Logs financiers** : Toutes les transactions sont loggées
- **Alertes** : Notifications pour les anomalies
- **Monitoring** : Surveillance des performances
- **Backup** : Sauvegarde automatique des données

---

**Module 6 - Gestion Financière**  
**Version** : 1.0.0  
**Dernière mise à jour** : Octobre 2025  
**Statut** : ✅ Complété
