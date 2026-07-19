#!/usr/bin/env bash
# Hero Zero — backup do MySQL com rotacao.
# Uso: hz-backup.sh   (le config de /etc/herozero/backup.env)
# Agende via deploy/systemd/herozero-backup.{service,timer} (diario).
set -euo pipefail

# Config (sobrescreva via /etc/herozero/backup.env).
: "${HZ_DB_HOST:=127.0.0.1}"
: "${HZ_DB_PORT:=3306}"
: "${HZ_DB_NAME:=herozero}"
: "${HZ_DB_USER:=herozero}"
: "${HZ_DB_PASS:?defina HZ_DB_PASS (ou em /etc/herozero/backup.env)}"
: "${HZ_BACKUP_DIR:=/var/backups/herozero}"
: "${HZ_BACKUP_KEEP_DAYS:=14}"

ts="$(date +%Y%m%d-%H%M%S)"
out="${HZ_BACKUP_DIR}/herozero-${ts}.sql.gz"
mkdir -p "$HZ_BACKUP_DIR"

# Dump consistente (single-transaction p/ InnoDB, sem travar o jogo).
MYSQL_PWD="$HZ_DB_PASS" mysqldump \
    --host="$HZ_DB_HOST" --port="$HZ_DB_PORT" --user="$HZ_DB_USER" \
    --single-transaction --quick --routines --triggers --events \
    --default-character-set=utf8mb4 \
    "$HZ_DB_NAME" | gzip -9 > "$out"

# Valida que o arquivo nao ficou vazio/corrompido.
if ! gzip -t "$out" 2>/dev/null || [ ! -s "$out" ]; then
    echo "ERRO: backup invalido: $out" >&2
    rm -f "$out"
    exit 1
fi

# Rotacao: remove backups mais velhos que N dias.
find "$HZ_BACKUP_DIR" -name 'herozero-*.sql.gz' -type f -mtime "+${HZ_BACKUP_KEEP_DAYS}" -delete

echo "backup ok: $out ($(du -h "$out" | cut -f1))"
