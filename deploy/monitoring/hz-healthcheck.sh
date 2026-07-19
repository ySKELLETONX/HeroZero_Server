#!/usr/bin/env bash
# Hero Zero — healthcheck ativo dos 3 componentes (web, socket, banco).
# Alerta via webhook (Slack/Discord/generico) quando algo cai. Rode pelo
# deploy/systemd/herozero-healthcheck.{service,timer} (a cada 1-2 min).
#
# Config em /etc/herozero/monitoring.env:
#   HZ_HEALTH_WEB=https://your-game-host.example/up
#   HZ_HEALTH_SOCKET=http://127.0.0.1:8090/health
#   HZ_ALERT_WEBHOOK=https://hooks.slack.com/...   (opcional)
#   HZ_DB_HOST / HZ_DB_USER / HZ_DB_PASS / HZ_DB_NAME (opcional, checa o banco)
set -uo pipefail

: "${HZ_HEALTH_WEB:=http://127.0.0.1:8000/up}"
: "${HZ_HEALTH_SOCKET:=http://127.0.0.1:8090/health}"
: "${HZ_ALERT_WEBHOOK:=}"

fails=()

check_http() {
    local name="$1" url="$2" expect="${3:-200}"
    local code
    code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 "$url")"
    code="${code:-000}"
    if [ "$code" != "$expect" ]; then
        fails+=("$name (HTTP $code em $url)")
    fi
}

check_http "web/Laravel" "$HZ_HEALTH_WEB" 200
check_http "socket-server" "$HZ_HEALTH_SOCKET" 200

# Banco (opcional): so checa se as credenciais estao no env.
if [ -n "${HZ_DB_PASS:-}" ]; then
    if ! MYSQL_PWD="$HZ_DB_PASS" mysqladmin --host="${HZ_DB_HOST:-127.0.0.1}" \
        --user="${HZ_DB_USER:-herozero}" ping --silent 2>/dev/null; then
        fails+=("mysql (ping falhou)")
    fi
fi

if [ ${#fails[@]} -eq 0 ]; then
    echo "OK: web, socket$( [ -n "${HZ_DB_PASS:-}" ] && echo ', mysql' ) saudaveis"
    exit 0
fi

msg="Hero Zero DOWN: $(IFS='; '; echo "${fails[*]}")"
echo "$msg" >&2

if [ -n "$HZ_ALERT_WEBHOOK" ]; then
    curl -s --max-time 5 -H 'Content-Type: application/json' \
        -d "{\"text\":$(printf '%s' "$msg" | python3 -c 'import json,sys;print(json.dumps(sys.stdin.read()))')}" \
        "$HZ_ALERT_WEBHOOK" >/dev/null || true
fi
exit 1
