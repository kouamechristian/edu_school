# Scripts de déploiement — edu_school

Scripts à exécuter **sur le serveur de production** (via SSH), à la racine du projet.
Application : Symfony 6.4 / PHP ≥ 8.1.

| Script | Rôle |
|---|---|
| [`deploy.sh`](deploy.sh) | Déploie la dernière version : `git pull` + Composer + sauvegarde BDD + migrations + cache. |
| [`rollback.sh`](rollback.sh) | Restaure la base depuis un dump (et, en option, ramène le code sur un commit précédent). |
| `generate_pptx.py` | Utilitaire de génération de présentation (hors déploiement). |

---

## Prérequis (sur le serveur)

- `git`, `php` (≥ 8.1), `composer`, `mysqldump` / `mysql` accessibles dans le `PATH`.
- Un fichier `.env.local` (ou `.env`) avec **`APP_ENV=prod`** et un `DATABASE_URL` valide.
- L'utilisateur SSH doit avoir les droits d'écriture sur `var/`.
- Les scripts sont déjà exécutables (`chmod +x` tracké par git). Sinon :
  ```bash
  chmod +x scripts/deploy.sh scripts/rollback.sh
  ```

---

## Déploiement

```bash
cd /chemin/vers/edu_school
git pull --ff-only origin master
./scripts/deploy.sh
```

Étapes enchaînées (**arrêt à la première erreur**, `set -euo pipefail`) :

1. `git pull` de la branche `master`
2. `composer install --no-dev --optimize-autoloader`
3. **Sauvegarde de la base** → `var/backups/<db>_<horodatage>.sql.gz`
4. `doctrine:migrations:migrate` (applique les migrations en attente)
5. `cache:clear` + `cache:warmup` en prod
6. Permissions `var/`

### Options

| Commande | Effet |
|---|---|
| `./scripts/deploy.sh --no-backup` | Déploie sans sauvegarder la base. |
| `PHP_BIN=php8.2 ./scripts/deploy.sh` | Utilise un binaire PHP spécifique. |
| `BACKUP_DIR=/var/backups/edu ./scripts/deploy.sh` | Change le dossier des dumps. |

Les paramètres de connexion à la base sont lus depuis `DATABASE_URL` (aucun mot de passe en dur).

---

## Rollback

```bash
# Lister les dumps disponibles
./scripts/rollback.sh --list

# Restaurer le dump le plus récent
./scripts/rollback.sh

# Restaurer un dump précis
./scripts/rollback.sh var/backups/edu_school_20260720_101500.sql.gz

# Restaurer la base ET ramener le code sur un commit
./scripts/rollback.sh --code <commit_ou_tag>
```

Garde-fous :

- **Confirmation obligatoire** : il faut taper `oui` (opération destructive).
- **Filet de sécurité** : l'état actuel est dumpé dans
  `var/backups/<db>_pre-rollback_<horodatage>.sql.gz` **avant** l'écrasement.
- Restaurer le dump remet aussi la table `doctrine_migration_versions` dans son
  état d'origine — **le dump EST le rollback du schéma**, pas besoin de
  `migrations:migrate prev`.

---

## Bonnes pratiques

- **Toujours garder la sauvegarde** produite par `deploy.sh` : c'est ce qui rend le
  rollback possible. Ne pas déployer avec `--no-backup` sauf cas maîtrisé.
- Purger périodiquement les vieux dumps de `var/backups/` (ils peuvent être volumineux).
- Vérifier après déploiement que l'application répond (page `/login`) et consulter
  `var/log/prod.log` en cas d'erreur.
- Ne jamais committer le `.env.local` du serveur (secrets de production).
