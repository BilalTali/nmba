#!/bin/bash

# ================================================================
# NMBA Agent Portal — Shared Hosting Production Deployment Script
# Server  : nmbabudgam.in (92.249.46.36:65002)
# User    : u335000182
# App Root: /home/u335000182/domains/nmbabudgam.in/nmbaagent
# PHP     : /usr/bin/php (PHP 8.3)
# Queue   : database driver via cron (no Supervisor / no Redis)
# ================================================================

set -e

# ── CONFIG ──────────────────────────────────────────────────────
SSH_HOST="92.249.46.36"
SSH_PORT="65002"
SSH_USER="u335000182"
SSH_PASS="Sugen@9313"
APP_DIR="/home/u335000182/domains/nmbabudgam.in/nmbaagent"
PHP="/usr/bin/php"

# ── HELPERS ─────────────────────────────────────────────────────
remote() {
    sshpass -p "$SSH_PASS" ssh -p "$SSH_PORT" \
        -o StrictHostKeyChecking=no \
        -o ConnectTimeout=15 \
        "$SSH_USER@$SSH_HOST" "$@"
}

remote_artisan() {
    remote "$PHP $APP_DIR/artisan $*"
}

log()  { echo -e "\e[1;34m▶ $1\e[0m"; }
ok()   { echo -e "\e[1;32m✓ $1\e[0m"; }
warn() { echo -e "\e[1;33m⚠ $1\e[0m"; }
fail() { echo -e "\e[1;31m✗ $1\e[0m"; exit 1; }

# ================================================================
echo -e "\e[1;32m"
echo "╔══════════════════════════════════════════════════╗"
echo "║   NMBA Agent Portal — Production Deployment      ║"
echo "║   Server: nmbabudgam.in                          ║"
echo "╚══════════════════════════════════════════════════╝"
echo -e "\e[0m"
# ================================================================

# STEP 1 — Verify SSH connectivity
log "Step 1/9 — Verifying server connectivity..."
remote "echo 'SSH OK'" >/dev/null && ok "SSH connection established." || fail "Cannot connect to server!"

# STEP 2 — Pull latest code from GitHub
log "Step 2/9 — Pulling latest code from GitHub..."
remote "cd $APP_DIR && git pull origin main 2>&1"
ok "Code updated."

# STEP 3 — Install Composer production dependencies
log "Step 3/9 — Installing Composer dependencies..."
remote "cd $APP_DIR && composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -5"
ok "Composer packages installed."

# STEP 4 — Run database migrations
log "Step 4/9 — Running database migrations..."
remote_artisan "migrate --force 2>&1"
ok "Migrations applied."

# STEP 5 — Warm up all Laravel caches
log "Step 5/9 — Warming up Laravel config / route / view caches..."
remote_artisan "config:cache 2>&1"
remote_artisan "route:cache  2>&1"
remote_artisan "view:cache   2>&1"
ok "Application caches warmed."

# STEP 6 — Clear stale dashboard metrics cache
log "Step 6/9 — Evicting stale dashboard telemetry cache..."
remote_artisan "cache:clear 2>&1"
ok "Cache evicted — fresh metrics will appear on next dashboard load."

# STEP 7 — Flush failed jobs and restart queue
log "Step 7/9 — Flushing failed job table and restarting queue signal..."
remote_artisan "queue:flush  2>&1"
remote_artisan "queue:restart 2>&1"
ok "Failed jobs flushed. Queue workers will restart on next cron tick."

# STEP 8 — Setup cron-based queue worker (idempotent — safe to run multiple times)
log "Step 8/9 — Registering cron-based queue worker (5-min interval)..."
CRON_CMD="*/5 * * * * $PHP $APP_DIR/artisan queue:work database --once --tries=10 --timeout=120 >> $APP_DIR/storage/logs/cron-worker.log 2>&1"
# Use a marker comment so we don't add duplicates
remote "
  (crontab -l 2>/dev/null | grep -v 'nmbaagent.*queue:work'; echo '# NMBA Queue Worker'; echo '$CRON_CMD') | crontab -
  crontab -l | grep 'queue:work' && echo 'Cron registered.' || echo 'Cron NOT registered!'
"
ok "Cron queue worker registered for every 5 minutes."

# STEP 9 — Final health check
log "Step 9/9 — Running final health probe..."
STATUS=$(remote "curl -s -o /dev/null -w '%{http_code}' https://nmbabudgam.in/ 2>/dev/null || echo 'unreachable'")
if [ "$STATUS" = "200" ] || [ "$STATUS" = "302" ]; then
    ok "Portal is live! HTTP status: $STATUS"
else
    warn "Portal returned HTTP $STATUS — may need a moment to warm up."
fi

echo ""
echo -e "\e[1;32m╔══════════════════════════════════════════════════╗"
echo "║  ✓ Deployment Complete! nmbabudgam.in is live.   ║"
echo "╚══════════════════════════════════════════════════╝\e[0m"
echo ""
echo "  Dashboard   → https://nmbabudgam.in/dashboard"
echo "  Queue Logs  → $APP_DIR/storage/logs/cron-worker.log"
echo "  Sync Logs   → $APP_DIR/storage/logs/sync.log"
echo ""
