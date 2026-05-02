#!/usr/bin/env bash
# Deploy frontend + backend to pandora server and run smoke tests.
# Safe to re-run. Fails fast on any error.
#
# Env:
#   SERVER_HOST (default: 139.162.121.187)
#   SERVER_PASS (required; set via: export SERVER_PASS=xxx OR use 1Password/macOS keychain)
#
# Usage:
#   ./scripts/deploy.sh                  # full deploy + smoke test
#   ./scripts/deploy.sh --skip-build     # rsync only, no rebuild
#   ./scripts/deploy.sh --smoke-only     # just run smoke tests

set -euo pipefail

SERVER_HOST="${SERVER_HOST:-139.162.121.187}"
SERVER_PASS="${SERVER_PASS:?Set SERVER_PASS env var}"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Refuse to deploy against production unless explicitly confirmed — prevents foot-gun
# when a working shell still has SERVER_PASS set. CI sets DEPLOY_CONFIRMED=1 to bypass.
if [[ "$SERVER_HOST" =~ (pandora\.js-store|shop\.jerosse|^139\.162\.121\.187$) ]]; then
  if [[ "${DEPLOY_CONFIRMED:-}" != "1" ]] && [[ " $* " != *" --yes "* ]] && [[ " $* " != *" -y "* ]]; then
    echo "⚠️  You're about to deploy to PRODUCTION ($SERVER_HOST)."
    read -r -p "Continue? Type 'yes' to proceed: " confirm
    [[ "$confirm" == "yes" ]] || { echo "aborted."; exit 1; }
  fi
fi

SSH="sshpass -p $SERVER_PASS ssh -o StrictHostKeyChecking=accept-new root@$SERVER_HOST"
RSYNC="sshpass -p $SERVER_PASS rsync -az -e 'ssh -o StrictHostKeyChecking=accept-new'"

FLAG="${1:-}"

step() { printf "\n\033[1;34m▶\033[0m %s\n" "$1"; }
ok()   { printf "  \033[0;32m✓\033[0m %s\n" "$1"; }

if [[ "$FLAG" != "--smoke-only" ]]; then
  step "Sync backend (excluding env, caches, vendor, storage)"
  # NOTE: bootstrap/cache excluded because Pail dev provider leaks in local cache
  eval "$RSYNC \
    --exclude='node_modules/' --exclude='.next/' --exclude='.git/' --exclude='vendor/' \
    --exclude='storage/logs/' \
    --exclude='storage/framework/cache/' --exclude='storage/framework/sessions/' --exclude='storage/framework/views/' \
    --exclude='bootstrap/cache/*.php' \
    --exclude='.env' --exclude='.env.local' --exclude='.env.production' \
    '$REPO_ROOT/backend/' root@$SERVER_HOST:/var/www/pandora/backend/" | tail -1
  ok "Backend synced"

  step "Sync frontend"
  eval "$RSYNC \
    --exclude='node_modules/' --exclude='.next/' --exclude='.git/' \
    --exclude='.env' --exclude='.env.local' --exclude='.env.production' \
    '$REPO_ROOT/frontend/' root@$SERVER_HOST:/var/www/pandora/frontend/" | tail -1
  ok "Frontend synced"

  step "Install backend deps + rebuild caches"
  $SSH "
    cd /var/www/pandora/backend
    composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -3
    rm -f bootstrap/cache/*.php
    php artisan config:cache 2>&1 | tail -1
    php artisan route:cache 2>&1 | tail -1
    php artisan migrate --force 2>&1 | tail -2
    chown -R www-data:www-data storage bootstrap/cache
  "
  ok "Backend deps installed + caches rebuilt"

  if [[ "$FLAG" != "--skip-build" ]]; then
    step "Install frontend deps + build (may take 5–8 min)"
    $SSH "
      cd /var/www/pandora/frontend
      npm install --prefer-offline --no-audit --no-fund 2>&1 | tail -3
      rm -rf .next
      NODE_OPTIONS='--max-old-space-size=1400' npm run build 2>&1 | tail -4
      pm2 restart pandora-frontend 2>&1 | tail -1
      : > /root/.pm2/logs/pandora-frontend-error-0.log
    "
    ok "Frontend deps installed, rebuilt + restarted"
  fi
fi

step "Smoke tests"
bash "$REPO_ROOT/scripts/smoke.sh"

step "Done"
ok "Deploy complete"
