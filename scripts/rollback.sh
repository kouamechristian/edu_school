#!/usr/bin/env bash
#
# Script de rollback — edu_school (Symfony 6.4)
# À exécuter sur le SERVEUR de production, à la racine du projet, via SSH.
#
# Restaure la base à partir d'un dump créé par deploy.sh, et (optionnel)
# ramène le code sur un commit précédent.
#
# Usage :
#   ./scripts/rollback.sh                       # restaure le dernier dump
#   ./scripts/rollback.sh var/backups/xxx.sql.gz  # restaure un dump précis
#   ./scripts/rollback.sh --list                # liste les dumps disponibles
#   ./scripts/rollback.sh --code <ref>          # + revient sur un commit/tag git
#
# Note : restaurer le dump remet aussi la table doctrine_migration_versions
# dans son état d'avant la migration — il n'y a donc PAS besoin de
# "migrations:migrate prev". Le dump EST le rollback du schéma.

set -euo pipefail

PHP_BIN="${PHP_BIN:-php}"
BACKUP_DIR="${BACKUP_DIR:-var/backups}"

cd "$(dirname "$0")/.."

log()  { printf '\n\033[1;32m==> %s\033[0m\n' "$*"; }
warn() { printf '\n\033[1;33m[!] %s\033[0m\n' "$*"; }
die()  { printf '\n\033[1;31m[x] %s\033[0m\n' "$*" >&2; exit 1; }

# ---------------------------------------------------------------------------
# Arguments
# ---------------------------------------------------------------------------
DUMP=""
CODE_REF=""
while [ $# -gt 0 ]; do
    case "$1" in
        --list)
            log "Dumps disponibles dans $BACKUP_DIR :"
            ls -1t "$BACKUP_DIR"/*.sql.gz 2>/dev/null || warn "Aucun dump trouvé."
            exit 0
            ;;
        --code)
            CODE_REF="${2:-}"; shift 2 || die "--code attend une référence git."
            ;;
        *)
            DUMP="$1"; shift
            ;;
    esac
done

# Dump par défaut = le plus récent
if [ -z "$DUMP" ]; then
    DUMP="$(ls -1t "$BACKUP_DIR"/*.sql.gz 2>/dev/null | head -n1 || true)"
    [ -z "$DUMP" ] && die "Aucun dump dans $BACKUP_DIR. Précise un fichier ou vérifie le dossier."
fi
[ -f "$DUMP" ] || die "Dump introuvable : $DUMP"

# ---------------------------------------------------------------------------
# Extraction des paramètres de connexion depuis DATABASE_URL
# ---------------------------------------------------------------------------
DB_URL="$($PHP_BIN -r '
    require "vendor/autoload.php";
    (new Symfony\Component\Dotenv\Dotenv())->bootEnv(".env");
    echo $_SERVER["DATABASE_URL"] ?? getenv("DATABASE_URL");
')"
[ -n "$DB_URL" ] || die "DATABASE_URL introuvable."

proto_stripped="${DB_URL#*://}"
creds="${proto_stripped%%@*}"
hostpart="${proto_stripped#*@}"
DB_USER="${creds%%:*}"
DB_PASS="${creds#*:}"; DB_PASS="${DB_PASS%%@*}"
hostport="${hostpart%%/*}"
DB_HOST="${hostport%%:*}"
DB_PORT="${hostport#*:}"; [ "$DB_PORT" = "$DB_HOST" ] && DB_PORT=3306
DB_NAME="${hostpart#*/}"; DB_NAME="${DB_NAME%%\?*}"

# ---------------------------------------------------------------------------
# Confirmation (opération destructive)
# ---------------------------------------------------------------------------
warn "ATTENTION — cette opération VA ÉCRASER la base « $DB_NAME » sur $DB_HOST."
warn "Dump à restaurer : $DUMP"
[ -n "$CODE_REF" ] && warn "Code : retour sur « $CODE_REF »."
printf "Taper 'oui' pour confirmer : "
read -r CONFIRM
[ "$CONFIRM" = "oui" ] || die "Rollback annulé."

# ---------------------------------------------------------------------------
# Filet de sécurité : sauvegarde de l'état ACTUEL avant d'écraser
# ---------------------------------------------------------------------------
log "Sauvegarde de l'état actuel avant restauration"
mkdir -p "$BACKUP_DIR"
SAFETY="$BACKUP_DIR/${DB_NAME}_pre-rollback_$(date +%Y%m%d_%H%M%S).sql"
MYSQL_PWD="$DB_PASS" mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" \
    --single-transaction --quick --routines --triggers \
    "$DB_NAME" > "$SAFETY" && gzip "$SAFETY"
log "État actuel sauvegardé : ${SAFETY}.gz"

# ---------------------------------------------------------------------------
# Restauration de la base
# ---------------------------------------------------------------------------
log "Restauration de la base depuis $DUMP"
gunzip -c "$DUMP" | MYSQL_PWD="$DB_PASS" mysql \
    -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME"
log "Base restaurée."

# ---------------------------------------------------------------------------
# Rollback du code (optionnel)
# ---------------------------------------------------------------------------
if [ -n "$CODE_REF" ]; then
    log "Retour du code sur $CODE_REF"
    git fetch --prune origin
    git checkout "$CODE_REF"
    export APP_ENV=prod APP_DEBUG=0
    ${COMPOSER_BIN:-composer} install --no-dev --optimize-autoloader --no-interaction --no-progress
fi

# ---------------------------------------------------------------------------
# Cache
# ---------------------------------------------------------------------------
log "Nettoyage du cache (prod)"
$PHP_BIN bin/console cache:clear --env=prod --no-debug
$PHP_BIN bin/console cache:warmup --env=prod --no-debug

log "Rollback terminé ✔  (filet de sécurité : ${SAFETY}.gz)"
