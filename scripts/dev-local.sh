#!/usr/bin/env bash
#
# Local dev launcher for the MDM Knowledge Hub (macOS + Herd PHP).
# (Re)starts all services. Safe to re-run any time — it frees ports first.
#
#   Postgres + pgvector  : 5433   (isolated cluster, ~/.mdm-pgdata)
#   Embeddings sidecar   : 8001   (Python venv, BAAI/bge-large-en-v1.5)
#   API (Laravel)        : 8011   (NOT 8000 — that's the herringandherring app)
#   Queue worker         : -      (ingests uploads; OCR-enabled)
#   Web (Next.js)        : 3000
#
# Overridable: PHP_BIN, PG_BIN, PGDATA. Logs land in /tmp/mdm-*.log.
set -u

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP="${PHP_BIN:-$HOME/Library/Application Support/Herd/bin/php}"
PG_BIN="${PG_BIN:-/opt/homebrew/opt/postgresql@17/bin}"
PGDATA="${PGDATA:-$HOME/.mdm-pgdata}"
export PATH="/opt/homebrew/bin:$(dirname "$PHP"):$PATH"   # so pdftotext/tesseract resolve

log() { printf '\033[36m[mdm]\033[0m %s\n' "$1"; }
free_port() { lsof -nP -iTCP:"$1" -sTCP:LISTEN 2>/dev/null | awk 'NR>1{print $2}' | xargs -r kill -9 2>/dev/null || true; }

# 1. Postgres (only if not already listening)
if nc -z 127.0.0.1 5433 2>/dev/null; then
  log "Postgres :5433 already up"
else
  log "starting Postgres :5433"
  "$PG_BIN/pg_ctl" -D "$PGDATA" -o "-p 5433 -k /tmp" -l "$PGDATA/server.log" start
fi

# 2. Embeddings sidecar
free_port 8001
log "starting embeddings sidecar :8001"
( cd "$ROOT/embeddings" && nohup ./.venv/bin/uvicorn app:app --host 127.0.0.1 --port 8001 >/tmp/mdm-sidecar.log 2>&1 & )

# 3. API — built-in server with raised upload limits, run from public/ (router uses cwd)
free_port 8011
log "starting API :8011"
( cd "$ROOT/api/public" && nohup "$PHP" \
    -d post_max_size=160M -d upload_max_filesize=128M -d memory_limit=1024M -d max_execution_time=300 \
    -S 127.0.0.1:8011 \
    "$ROOT/api/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php" \
    >/tmp/mdm-serve-8011.log 2>&1 & )

# 4. Queue worker (ingests uploaded sources; needs more memory for large PDFs)
pkill -f "artisan queue:work" 2>/dev/null || true
log "starting queue worker"
( cd "$ROOT/api" && nohup "$PHP" -d memory_limit=1024M artisan queue:work --tries=1 --timeout=600 >/tmp/mdm-worker.log 2>&1 & )

# 5. Web
free_port 3000
log "starting web :3000"
( cd "$ROOT/web" && nohup npm run dev >/tmp/mdm-web-dev.log 2>&1 & )

sleep 2
log "up — app: http://localhost:3000   admin: http://localhost:8011/admin"
log "logs: /tmp/mdm-{sidecar,serve-8011,worker,web-dev}.log"
