# ⚡ Démarrage Rapide - Module 2

## 🚀 Installation en 5 minutes

### Étape 1 : Exécuter les migrations

```bash
php bin/console doctrine:migrations:migrate
```

Répondez **yes** pour confirmer.

### Étape 2 : Charger les données de test

```bash
php bin/console doctrine:fixtures:load --append
```

### Étape 3 : Créer votre compte admin

```bash
php bin/console app:create-admin
```

Suivez les instructions interactives :
```
Nom d'utilisateur: myadmin
Email: moi@email.com
Mot de passe: ******
Confirmer le mot de passe: ******
Rôle à attribuer: ROLE_SUPER_ADMIN
Prénom (optionnel): Jean
Nom (optionnel): DUPONT
```

### Étape 4 : Démarrer le serveur

```bash
symfony server:start
# ou
php -S localhost:8000 -t public/
```

### Étape 5 : Se connecter

1. Ouvrir : `http://localhost:8000/login`
2. Utiliser vos identifiants ou un compte de test
3. Accéder au tableau de bord

## 🔑 Comptes de test disponibles

| Type | Login | Mot de passe | Accès |
|------|-------|--------------|-------|
| Super Admin | `superadmin` | `Admin@123` | Tous les modules |
| Admin | `admin` | `Admin@123` | Administration |
| Directeur | `directeur1` | `Password@123` | Gestion établissement |
| Enseignant | `jmartin` | `Teacher@123` | Notes, absences |
| Personnel | `secretaire1` | `Staff@123` | Saisie données |
| Élève | `lucas.dubois` | `Student@123` | Consultation |
| Parent | `parent1` | `Parent@123` | Suivi enfant |

## ✅ Vérification rapide

### Tester l'accès au module

```bash
# Ouvrir dans le navigateur
http://localhost:8000/admin/users
```

Vous devriez voir la liste des 24 utilisateurs créés.

### Vérifier en base de données

```bash
php bin/console doctrine:query:sql "SELECT username, email, user_type FROM user LIMIT 10"
```

### Créer un nouvel utilisateur via l'interface

1. Aller sur `/admin/users`
2. Cliquer "Nouvel Utilisateur"
3. Remplir le formulaire
4. Enregistrer
5. Vérifier dans la liste

## 🎯 Fonctionnalités à tester

- ✅ Connexion/Déconnexion
- ✅ Création d'utilisateur
- ✅ Modification d'utilisateur  
- ✅ Recherche et filtres
- ✅ Activation/Désactivation
- ✅ Réinitialisation de mot de passe
- ✅ Affichage des rôles
- ✅ Remember Me (rester connecté)

## ⚠️ Problèmes courants

### "Access denied for user 'root'@'localhost'"

Configurez votre `.env.local` :
```env
DATABASE_URL="mysql://root:votre_password@127.0.0.1:3306/edu_school"
```

### "Table 'user' doesn't exist"

Exécutez les migrations :
```bash
php bin/console doctrine:migrations:migrate
```

### Page blanche après login

Vérifiez que la route `app_home` existe dans `HomeController.php`.

## 🎉 Prochaine étape

Maintenant que le Module 2 est installé, vous pouvez :

1. **Créer vos utilisateurs réels**
2. **Configurer les rôles selon vos besoins**
3. **Passer au Module 3** : Gestion académique

---

**Support** : Pour toute question, consultez `docs/MODULE_2_UTILISATEURS.md`

