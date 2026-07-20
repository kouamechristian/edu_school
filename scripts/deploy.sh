#!/usr/bin/env bash
#
# Script de déploiement — edu_school (Symfony 6.4)
# À exécuter sur le SERVEUR de production, à la racine du projet, via SSH.
#
# Usage :
#   ./scripts/deploy.sh            # déploiement complet
#   ./scripts/deploy.sh --no-backup   # sans sauvegarde de la base
#
# Étapes : git pull -> composer -> sauvegarde BDD -> migrations -> cache.
# Le script s'arrête à la première erreur (aucune étape suivante n'est jouée).

set -euo pipefail

# ---------------------------------------------------------------------------
# Configuration (adapter si besoin)
# ---------------------------------------------------------------------------
BRANCH="master"                       # branche à déployer
PHP_BIN="${PHP_BIN:-php}"             # binaire PHP (ex: php8.2)
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
BACKUP_DIR="${BACKUP_DIR:-var/backups}"  # dossier des dumps SQL

# Se placer à la racine du projet (dossier parent de scripts/)
cd "$(dirname "$0")/.."
PROJECT_DIR="$(pwd)"

DO_BACKUP=1
[ "${1:-}" = "--no-backup" ] && DO_BACKUP=0

log() { printf '\n\033[1;32m==> %s\033[0m\n' "$*"; }
warn() { printf '\n\033[1;33m[!] %s\033[0m\n' "$*"; }

# ---------------------------------------------------------------------------
# 0. Vérifications
# ---------------------------------------------------------------------------
log "Projet : $PROJECT_DIR (branche cible : $BRANCH)"

if [ ! -f .env.local ] && [ ! -f .env ]; then
    warn "Aucun .env / .env.local trouvé. Assure-toi que APP_ENV=prod est configuré."
fi

# ---------------------------------------------------------------------------
# 1. Récupération du code
# ---------------------------------------------------------------------------
log "Récupération du code (git pull)"
git fetch --prune origin
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

# ---------------------------------------------------------------------------
# 2. Dépendances PHP (prod, optimisées)
# ---------------------------------------------------------------------------
log "Installation des dépendances Composer (prod)"
export APP_ENV=prod
export APP_DEBUG=0
$COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction --no-progress

# ---------------------------------------------------------------------------
# 3. Sauvegarde de la base AVANT migration
# ---------------------------------------------------------------------------
if [ "$DO_BACKUP" -eq 1 ]; then
    log "Sauvegarde de la base de données"
    mkdir -p "$BACKUP_DIR"

    # Récupère l'URL de connexion depuis Symfony (source de vérité unique)
    DB_URL="$($PHP_BIN -r '
        require "vendor/autoload.php";
        (new Symfony\Component\Dotenv\Dotenv())->bootEnv(".env");
        echo $_SERVER["DATABASE_URL"] ?? getenv("DATABASE_URL");
    ')"

    if [ -n "$DB_URL" ]; then
        # Parse mysql://user:pass@host:port/dbname
        proto_stripped="${DB_URL#*://}"
        creds="${proto_stripped%%@*}"
        hostpart="${proto_stripped#*@}"
        DB_USER="${creds%%:*}"
        DB_PASS="${creds#*:}"; DB_PASS="${DB_PASS%%@*}"
        hostport="${hostpart%%/*}"
        DB_HOST="${hostport%%:*}"
        DB_PORT="${hostport#*:}"; [ "$DB_PORT" = "$DB_HOST" ] && DB_PORT=3306
        DB_NAME="${hostpart#*/}"; DB_NAME="${DB_NAME%%\?*}"

        STAMP="$(date +%Y%m%d_%H%M%S)"
        DUMP_FILE="$BACKUP_DIR/${DB_NAME}_${STAMP}.sql"
        MYSQL_PWD="$DB_PASS" mysqldump \
            -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" \
            --single-transaction --quick --routines --triggers \
            "$DB_NAME" > "$DUMP_FILE"
        gzip "$DUMP_FILE"
        log "Sauvegarde créée : ${DUMP_FILE}.gz"
    else
        warn "DATABASE_URL introuvable — sauvegarde ignorée."
    fi
else
    warn "Sauvegarde ignorée (--no-backup)."
fi

# ---------------------------------------------------------------------------
# 4. Migrations de base de données
# ---------------------------------------------------------------------------
log "Exécution des migrations Doctrine"
$PHP_BIN bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# ---------------------------------------------------------------------------
# 5. Cache
# ---------------------------------------------------------------------------
log "Nettoyage + préchauffage du cache (prod)"
$PHP_BIN bin/console cache:clear --env=prod --no-debug
$PHP_BIN bin/console cache:warmup --env=prod --no-debug

# ---------------------------------------------------------------------------
# 6. Permissions du dossier var/ (cache + logs)
# ---------------------------------------------------------------------------
if [ -d var ]; then
    log "Permissions var/"
    chmod -R ug+rwX var || warn "chmod var/ échoué (permissions insuffisantes ?)"
fi

log "Déploiement terminé avec succès ✔"
