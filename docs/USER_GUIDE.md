# 📘 Guide Utilisateur - EDU-SCHOOL

## 🎯 Introduction

Bienvenue dans EDU-SCHOOL, votre système de gestion scolaire intégré. Ce guide vous accompagnera dans l'utilisation quotidienne de la plateforme.

## 👥 Types d'utilisateurs

### 1. 👨‍💼 Super Administrateur
**Accès complet** à toutes les fonctionnalités du système.

**Responsabilités principales** :
- Configuration globale du système
- Gestion des établissements
- Gestion des utilisateurs et rôles
- Surveillance et maintenance
- Génération de rapports globaux

### 2. 🏫 Administrateur d'établissement
**Gestion d'un établissement** spécifique.

**Responsabilités principales** :
- Gestion des années scolaires
- Création des classes et niveaux
- Attribution des enseignants
- Gestion financière
- Validation des inscriptions

### 3. 👨‍🏫 Enseignant
**Gestion pédagogique** de ses classes.

**Responsabilités principales** :
- Saisie des notes
- Gestion des absences
- Consultation de l'emploi du temps
- Communication avec les élèves/parents
- Génération de bulletins

### 4. 🎓 Élève/Étudiant
**Consultation** de ses informations scolaires.

**Fonctionnalités accessibles** :
- Consultation des notes
- Visualisation de l'emploi du temps
- Suivi des absences
- Téléchargement des documents
- Communication

### 5. 👪 Parent
**Suivi** de la scolarité de son/ses enfant(s).

**Fonctionnalités accessibles** :
- Consultation des notes
- Suivi des absences
- Paiements en ligne
- Communication avec les enseignants
- Téléchargement des bulletins

## 🔐 Connexion

### Première connexion

1. **Accéder à la plateforme**
   ```
   https://edu-school.com/login
   ```

2. **Saisir vos identifiants**
   - Nom d'utilisateur ou email
   - Mot de passe

3. **Option "Se souvenir de moi"**
   - Cochez pour rester connecté pendant 1 an

4. **Mot de passe oublié**
   - Cliquez sur "Mot de passe oublié"
   - Saisissez votre email
   - Suivez le lien reçu par email

### Sécurité

⚠️ **Important** :
- 3 tentatives maximum (blocage de 2 minutes)
- Changez votre mot de passe régulièrement
- Ne partagez jamais vos identifiants
- Déconnectez-vous après utilisation

## 📊 Tableau de bord

### Vue d'ensemble

Après connexion, vous accédez à votre tableau de bord personnalisé selon votre rôle.

#### Tableau de bord Administrateur
```
┌─────────────────────────────────────────┐
│  📊 Statistiques générales              │
├─────────────────────────────────────────┤
│  👥 Élèves: 850    👨‍🏫 Enseignants: 65   │
│  🏫 Classes: 35    💰 Taux collecte: 89%│
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│  📈 Graphiques                          │
│  - Évolution des inscriptions          │
│  - Répartition par niveau              │
│  - Performance financière              │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│  🔔 Notifications récentes              │
│  - 3 nouvelles inscriptions            │
│  - 5 paiements en attente              │
└─────────────────────────────────────────┘
```

#### Tableau de bord Enseignant
```
┌─────────────────────────────────────────┐
│  📅 Emploi du temps du jour             │
│  08:00 - 09:00 | 6ème A | Mathématiques │
│  10:00 - 11:00 | 5ème B | Mathématiques │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│  ✏️ Tâches en attente                   │
│  - 28 notes à saisir (6ème A)          │
│  - 2 bulletins à valider               │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│  📊 Mes classes                         │
│  6ème A (28 élèves) | Moyenne: 14.5    │
│  5ème B (25 élèves) | Moyenne: 13.8    │
└─────────────────────────────────────────┘
```

## 📚 Modules principaux

### 1. 👥 Gestion des Élèves

#### Inscrire un nouvel élève

1. **Navigation** : `Élèves > Nouveau`

2. **Informations personnelles**
   - Matricule (auto-généré ou manuel)
   - Nom et prénom
   - Date et lieu de naissance
   - Genre
   - Nationalité

3. **Coordonnées**
   - Adresse complète
   - Téléphone
   - Email (optionnel)
   - Contact d'urgence

4. **Informations scolaires**
   - Classe
   - Date d'inscription
   - Statut

5. **Parents/Tuteurs**
   - Ajouter au moins un parent
   - Définir le contact principal

6. **Documents**
   - Photo (optionnel)
   - Certificat de naissance
   - Certificat médical

7. **Enregistrer**
   - Vérifier les informations
   - Cliquer sur "Enregistrer"
   - Une carte d'étudiant avec QR Code sera générée

#### Rechercher un élève

**Méthodes de recherche** :
- Par nom/prénom
- Par matricule
- Par classe
- Par statut

**Filtres avancés** :
```
Classe: [6ème A ▼]
Genre: [Tous ▼]
Statut: [Actif ▼]
Date d'inscription: [Du] [Au]

[🔍 Rechercher] [🔄 Réinitialiser]
```

#### Modifier un élève

1. Rechercher l'élève
2. Cliquer sur son nom
3. Cliquer sur "Modifier"
4. Effectuer les modifications
5. Enregistrer

#### Actions en masse

- Sélectionner plusieurs élèves
- Choisir une action :
  - Changer de classe
  - Exporter en Excel/PDF
  - Envoyer une notification
  - Générer des cartes

### 2. 📝 Gestion des Notes

#### Saisir des notes

**Pour un enseignant** :

1. **Navigation** : `Notes > Saisir`

2. **Sélectionner**
   - Classe
   - Matière
   - Période (trimestre/semestre)
   - Type d'évaluation

3. **Tableau de saisie**
```
┌────────────────────────────────────────────┐
│ Classe: 6ème A | Matière: Mathématiques    │
│ Période: 1er Trimestre | Type: Devoir      │
├────────┬──────────┬──────────┬─────────────┤
│ Élève  │ Note /20 │ Coef.    │ Observation │
├────────┼──────────┼──────────┼─────────────┤
│ DUPONT │ [15.5]   │ [2]      │ Très bien   │
│ MARTIN │ [12.0]   │ [2]      │ Bien        │
│ ...    │          │          │             │
└────────┴──────────┴──────────┴─────────────┘

[💾 Enregistrer] [📊 Calculer moyennes]
```

4. **Validation**
   - Vérifier les notes saisies
   - Cliquer sur "Enregistrer"

#### Consulter les notes

**Pour un élève/parent** :

1. **Navigation** : `Mes notes` ou `Notes de [Enfant]`

2. **Filtrer par période**

3. **Vue détaillée**
```
┌─────────────────────────────────────────┐
│ 1er Trimestre 2024-2025                 │
├─────────────────────┬──────┬────────────┤
│ Matière             │ Moy. │ Coefficient│
├─────────────────────┼──────┼────────────┤
│ Mathématiques       │ 15.5 │ 3          │
│ │ - Devoir 1        │ 14   │            │
│ │ - Examen          │ 17   │            │
│ Français            │ 13.0 │ 3          │
│ Histoire-Géo        │ 14.5 │ 2          │
├─────────────────────┼──────┼────────────┤
│ MOYENNE GÉNÉRALE    │ 14.3 │            │
│ Classement          │ 3/28 │            │
└─────────────────────┴──────┴────────────┘

[📄 Télécharger bulletin] [📊 Voir graphique]
```

#### Générer un bulletin

1. **Navigation** : `Notes > Bulletins`

2. **Sélectionner**
   - Élève(s)
   - Période
   - Format (PDF/Excel)

3. **Options**
   - Inclure appréciations
   - Inclure signature
   - Inclure logo établissement

4. **Générer**
   - Prévisualiser
   - Télécharger ou envoyer par email

### 3. 📅 Gestion des Absences

#### Enregistrer une absence

**Pour un enseignant** :

1. **Navigation** : `Absences > Pointer`

2. **Sélectionner la classe et le cours**

3. **Feuille de présence**
```
┌────────────────────────────────────────┐
│ 6ème A - Mathématiques - 10/10/2024    │
├────────┬────────────────────────────────┤
│ Élève  │ Statut                         │
├────────┼────────────────────────────────┤
│ DUPONT │ ✅ Présent  ❌ Absent  ⏰ Retard│
│ MARTIN │ ✅ Présent  ❌ Absent  ⏰ Retard│
│ ...    │                                │
└────────┴────────────────────────────────┘

[💾 Enregistrer] [📱 Notifier parents]
```

4. **Pour un retard**
   - Cocher "Retard"
   - Saisir l'heure d'arrivée
   - Ajouter un motif (optionnel)

#### Justifier une absence

**Pour un parent** :

1. **Navigation** : `Absences > Justifier`

2. **Sélectionner l'absence à justifier**

3. **Fournir le motif**
   - Maladie
   - Rendez-vous médical
   - Événement familial
   - Autre

4. **Joindre un document** (optionnel)
   - Certificat médical
   - Justificatif

5. **Soumettre**

#### Consulter l'assiduité

**Rapport d'assiduité** :
```
┌─────────────────────────────────────────┐
│ Élève: Marie DUPONT                     │
│ Période: 01/09/2024 - 15/10/2024       │
├─────────────────────────────────────────┤
│ 📊 Statistiques                         │
│ Total jours: 30                         │
│ Présences: 27 (90%)                     │
│ Absences: 2                             │
│   - Justifiées: 2                       │
│   - Non justifiées: 0                   │
│ Retards: 1                              │
├─────────────────────────────────────────┤
│ 📅 Détail des absences                  │
│ 05/10 - Maladie (Justifiée)            │
│ 12/10 - RDV médical (Justifiée)        │
└─────────────────────────────────────────┘

[📄 Exporter PDF] [📧 Envoyer par email]
```

### 4. 💰 Gestion Financière

#### Consulter les frais

**Pour un parent** :

1. **Navigation** : `Finances > Mes frais`

2. **Vue d'ensemble**
```
┌─────────────────────────────────────────┐
│ Année scolaire 2024-2025                │
├──────────────┬──────┬────────┬──────────┤
│ Type         │ Dû   │ Payé   │ Reste    │
├──────────────┼──────┼────────┼──────────┤
│ Scolarité    │ 1000 │ 600    │ 400      │
│ Cantine      │ 300  │ 300    │ 0        │
│ Transport    │ 200  │ 200    │ 0        │
├──────────────┼──────┼────────┼──────────┤
│ TOTAL        │ 1500 │ 1100   │ 400      │
└──────────────┴──────┴────────┴──────────┘

⚠️ Échéance prochaine: 30/11/2024 (200€)

[💳 Payer maintenant] [📅 Plan de paiement]
```

#### Effectuer un paiement

1. **Sélectionner le frais à payer**

2. **Montant**
   - Paiement total
   - Paiement partiel

3. **Méthode de paiement**
   - Espèces
   - Chèque
   - Carte bancaire
   - Virement
   - Mobile Money

4. **Confirmer**

5. **Reçu de paiement**
   - Téléchargement automatique
   - Envoi par email

#### Historique des paiements

```
┌──────────┬──────────┬────────┬──────────┬──────────┐
│ Date     │ Type     │ Montant│ Méthode  │ Reçu     │
├──────────┼──────────┼────────┼──────────┼──────────┤
│ 15/10/24 │ Scolarité│ 200€   │ Espèces  │ [📄 PDF] │
│ 01/10/24 │ Cantine  │ 300€   │ Carte    │ [📄 PDF] │
│ 01/09/24 │ Scolarité│ 400€   │ Virement │ [📄 PDF] │
└──────────┴──────────┴────────┴──────────┴──────────┘

[📊 Exporter] [📧 Envoyer historique]
```

### 5. 📅 Emploi du temps

#### Consulter son emploi du temps

**Pour un élève/enseignant** :

1. **Navigation** : `Emploi du temps`

2. **Vue hebdomadaire**
```
┌────────┬─────────┬─────────┬─────────┬─────────┬─────────┐
│ Heure  │ Lundi   │ Mardi   │ Mercr.  │ Jeudi   │ Vendr.  │
├────────┼─────────┼─────────┼─────────┼─────────┼─────────┤
│ 08:00  │ Math    │ Français│ Hist-Géo│ Math    │ Sport   │
│        │ M.Martin│ Mme Dupr│ M.Blanc │ M.Martin│ M.Sport │
│        │ S.101   │ S.205   │ S.302   │ S.101   │ Gymnase │
├────────┼─────────┼─────────┼─────────┼─────────┼─────────┤
│ 09:00  │ Français│ Math    │ Anglais │ SVT     │ Arts    │
│        │         │         │         │         │         │
├────────┼─────────┼─────────┼─────────┼─────────┼─────────┤
│ 10:00  │ 🕐 Pause│ 🕐 Pause│ 🕐 Pause│ 🕐 Pause│ 🕐 Pause│
└────────┴─────────┴─────────┴─────────┴─────────┴─────────┘

[📱 Synchroniser calendrier] [📄 Imprimer] [📧 Envoyer]
```

3. **Options d'affichage**
   - Vue jour
   - Vue semaine
   - Vue mois
   - Vue agenda

#### Créer un cours (Administrateur)

1. **Navigation** : `Emploi du temps > Nouveau cours`

2. **Informations**
   - Matière
   - Enseignant
   - Classe
   - Jour de la semaine
   - Heure de début/fin
   - Salle

3. **Récurrence**
   - Hebdomadaire
   - Bi-hebdomadaire
   - Personnalisée

4. **Enregistrer**
   - Vérification des conflits
   - Confirmation

### 6. 📚 Bibliothèque

#### Rechercher un livre

1. **Navigation** : `Bibliothèque > Catalogue`

2. **Recherche**
   - Par titre
   - Par auteur
   - Par ISBN
   - Par catégorie

3. **Résultats**
```
┌──────────────────────────────────────────┐
│ 📖 Les Misérables                        │
│ Auteur: Victor Hugo                      │
│ ISBN: 978-2-07-036XXX                    │
│ Disponible: 3 / 5                        │
│ Emplacement: Rayon A3                    │
│                                          │
│ [📚 Emprunter] [ℹ️ Détails]             │
└──────────────────────────────────────────┘
```

#### Emprunter un livre

1. **Sélectionner le livre**

2. **Vérifier disponibilité**

3. **Confirmer l'emprunt**
   - Date de retour prévue (automatique)
   - Conditions d'emprunt

4. **Reçu d'emprunt**

#### Mes emprunts

```
┌────────┬──────────────┬────────┬──────────┬────────┐
│ Livre  │ Date emprunt │ Retour │ Statut   │ Action │
├────────┼──────────────┼────────┼──────────┼────────┤
│ Les    │ 01/10/24     │ 15/10  │ ⚠️ Retard│ [Rendre│
│ Miséra.│              │        │ 3 jours  │        │
├────────┼──────────────┼────────┼──────────┼────────┤
│ 1984   │ 10/10/24     │ 24/10  │ ✅ En    │ [Rendre│
│        │              │        │ cours    │        │
└────────┴──────────────┴────────┴──────────┴────────┘
```

### 7. 📱 Communication

#### Envoyer un message

1. **Navigation** : `Messages > Nouveau`

2. **Destinataires**
   - Sélectionner individuellement
   - Par classe
   - Par rôle (tous les parents, tous les enseignants)

3. **Composer le message**
   - Sujet
   - Corps du message
   - Pièces jointes (optionnel)

4. **Options**
   - Notification email
   - Notification SMS
   - Marquer comme important

5. **Envoyer**

#### Boîte de réception

```
┌────────────────────────────────────────────────┐
│ 📥 Messages reçus                              │
├──────┬───────────────────┬──────────┬─────────┤
│ De   │ Sujet             │ Date     │ Statut  │
├──────┼───────────────────┼──────────┼─────────┤
│ 🔴 M.│ Réunion parents   │ 15/10 09h│ 📫 Non  │
│ Direc│                   │          │ lu      │
├──────┼───────────────────┼──────────┼─────────┤
│ ✅ Mme│ Notes du trimestre│ 14/10 14h│ ✓ Lu    │
│ Dupré│                   │          │         │
└──────┴───────────────────┴──────────┴─────────┘

[✏️ Nouveau message] [🗑️ Supprimer] [📁 Archiver]
```

#### Notifications

**Centre de notifications** :
```
┌────────────────────────────────────────┐
│ 🔔 Notifications (5)                   │
├────────────────────────────────────────┤
│ 🆕 Nouvelle note en Mathématiques      │
│    Il y a 2 heures                     │
│                                        │
│ 💰 Paiement confirmé - 200€            │
│    Hier à 15:30                        │
│                                        │
│ 📅 Rappel: Réunion parents demain      │
│    Avant-hier à 10:00                  │
└────────────────────────────────────────┘

[⚙️ Paramètres] [✓ Tout marquer lu]
```

### 8. 📄 Documents

#### Télécharger des documents

1. **Navigation** : `Documents`

2. **Types de documents**
   - Bulletins de notes
   - Attestations de scolarité
   - Certificats
   - Relevés de notes
   - Reçus de paiement
   - Cartes d'étudiant

3. **Recherche/Filtre**
   - Par type
   - Par date
   - Par élève (pour parents)

4. **Télécharger**
```
┌──────────────────────────────────────────┐
│ 📄 Mes documents                         │
├───────────────┬─────────┬────────────────┤
│ Document      │ Date    │ Action         │
├───────────────┼─────────┼────────────────┤
│ Bulletin T1   │ 20/12/24│ [📥 PDF] [✉️] │
│ Attestation   │ 15/10/24│ [📥 PDF] [✉️] │
│ Carte étudiant│ 01/09/24│ [📥 PDF] [🖨️] │
└───────────────┴─────────┴────────────────┘
```

#### Demander un document

1. **Navigation** : `Documents > Demander`

2. **Type de document**
   - Attestation de scolarité
   - Relevé de notes
   - Certificat de radiation
   - Autre

3. **Motif** (optionnel)

4. **Soumettre**
   - Délai de traitement: 2-3 jours ouvrés
   - Notification par email à la génération

## ⚙️ Paramètres du compte

### Modifier son profil

1. **Navigation** : `Mon compte > Profil`

2. **Informations modifiables**
   - Photo de profil
   - Numéro de téléphone
   - Adresse email
   - Adresse postale

3. **Enregistrer les modifications**

### Changer son mot de passe

1. **Navigation** : `Mon compte > Sécurité`

2. **Saisir**
   - Mot de passe actuel
   - Nouveau mot de passe
   - Confirmer le nouveau mot de passe

3. **Exigences**
   - Minimum 8 caractères
   - Au moins une majuscule
   - Au moins un chiffre
   - Au moins un caractère spécial

4. **Valider**

### Préférences de notification

1. **Navigation** : `Mon compte > Notifications`

2. **Configurer**
```
┌─────────────────────────────────────────┐
│ 📧 Notifications email                  │
│ ✅ Nouvelles notes                      │
│ ✅ Absences                             │
│ ✅ Paiements                            │
│ ❌ Messages marketing                   │
│                                         │
│ 📱 Notifications SMS                    │
│ ✅ Urgences uniquement                  │
│ ❌ Toutes les notifications             │
│                                         │
│ 🔔 Notifications push (mobile)          │
│ ✅ Activées                             │
└─────────────────────────────────────────┘

[💾 Enregistrer]
```

## 📱 Application mobile

### Installation

**iOS** :
- App Store > Rechercher "EDU-SCHOOL"
- Télécharger et installer

**Android** :
- Google Play > Rechercher "EDU-SCHOOL"
- Télécharger et installer

### Fonctionnalités mobiles

- ✅ Consultation des notes
- ✅ Emploi du temps
- ✅ Notifications push
- ✅ Scanner QR Code (carte étudiant)
- ✅ Paiements mobiles
- ✅ Messagerie
- ✅ Pointage des absences (enseignants)

## ❓ FAQ

### Questions fréquentes

**Q: J'ai oublié mon mot de passe, que faire ?**
R: Cliquez sur "Mot de passe oublié" sur la page de connexion et suivez les instructions.

**Q: Comment justifier une absence ?**
R: Allez dans Absences > Justifier, sélectionnez l'absence et fournissez un motif avec document si nécessaire.

**Q: Quand les bulletins sont-ils disponibles ?**
R: Les bulletins sont générés automatiquement 3 jours après la fin de chaque période.

**Q: Comment payer les frais de scolarité en ligne ?**
R: Finances > Mes frais > Sélectionner le frais > Payer maintenant.

**Q: Puis-je télécharger les notes de mon enfant en Excel ?**
R: Oui, dans Notes > Actions > Exporter > Format Excel.

**Q: Comment contacter un enseignant ?**
R: Messages > Nouveau > Sélectionner l'enseignant dans la liste.

## 📞 Support technique

### Obtenir de l'aide

**Email** : support@edu-school.com  
**Téléphone** : +33 1 XX XX XX XX  
**Horaires** : Lun-Ven 8h-18h

**Chat en direct** : Disponible sur la plateforme (coin inférieur droit)

### Signaler un problème

1. **Navigation** : `Aide > Signaler un problème`

2. **Remplir le formulaire**
   - Type de problème
   - Description détaillée
   - Captures d'écran (optionnel)

3. **Soumettre**
   - Ticket créé automatiquement
   - Réponse sous 24h

---

**Version** : 1.0  
**Dernière mise à jour** : Octobre 2025  
**© EDU-SCHOOL - Tous droits réservés**

