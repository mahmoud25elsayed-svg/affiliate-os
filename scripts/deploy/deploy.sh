#!/usr/bin/env bash

# ==============================================================================
# AffiliateOS Zero-Downtime Atomic Deployment Script for Hostinger
# Supports: staging and production environments
# Usage: ./scripts/deploy/deploy.sh [staging|production]
# ==============================================================================

set -euo pipefail

ENVIRONMENT="${1:-staging}"
TIMESTAMP=$(date +"%Y%m%d%H%M%S")

echo "================================================================="
echo " Starting Zero-Downtime Deployment for [$ENVIRONMENT]"
echo " Timestamp: $TIMESTAMP"
echo "================================================================="

# Environment Configuration
case "$ENVIRONMENT" in
    staging)
        SSH_HOST="${DEPLOY_SSH_HOST:-srv1799.hstgr.io}"
        SSH_PORT="${DEPLOY_SSH_PORT:-65002}"
        SSH_USER="${DEPLOY_SSH_USER:-u747110105}"
        DOMAIN="staging.affiliate-os.com"
        TARGET_PATH="/home/${SSH_USER}/domains/${DOMAIN}"
        ;;
    production)
        SSH_HOST="${DEPLOY_SSH_HOST:-srv1799.hstgr.io}"
        SSH_PORT="${DEPLOY_SSH_PORT:-65002}"
        SSH_USER="${DEPLOY_SSH_USER:-u747110105}"
        DOMAIN="affiliate-os.com"
        TARGET_PATH="/home/${SSH_USER}/domains/${DOMAIN}"
        ;;
    *)
        echo "Error: Invalid environment '$ENVIRONMENT'. Allowed: staging, production"
        exit 1
        ;;
esac

RELEASE_DIR="${TARGET_PATH}/releases/${TIMESTAMP}"

echo "Target Server: ${SSH_USER}@${SSH_HOST}:${SSH_PORT}"
echo "Target Path:   ${TARGET_PATH}"
echo "Release Path:  ${RELEASE_DIR}"
echo ""

# 1. Local Build Steps (Assets & Composer autoloader)
echo "[1/6] Building production assets and installing vendor packages..."
if command -v npm &> /dev/null; then
    echo "  -> Running npm run build..."
    npm ci --prefer-offline --no-audit
    npm run build
fi

if command -v composer &> /dev/null; then
    echo "  -> Optimizing composer autoloader..."
    composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
fi

# 2. Prepare Remote Release Directory & Persistent Storage
echo "[2/6] Preparing remote directories on Hostinger..."
ssh -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" << EOF
    set -e
    mkdir -p "${TARGET_PATH}/releases"
    mkdir -p "${TARGET_PATH}/storage/app/public"
    mkdir -p "${TARGET_PATH}/storage/framework/cache/data"
    mkdir -p "${TARGET_PATH}/storage/framework/sessions"
    mkdir -p "${TARGET_PATH}/storage/framework/views"
    mkdir -p "${TARGET_PATH}/storage/logs"
    mkdir -p "${RELEASE_DIR}"
EOF

# 3. Synchronize Files to New Timestamped Release
echo "[3/6] Syncing application files via rsync..."
rsync -avz -e "ssh -p ${SSH_PORT}" \
    --delete \
    --exclude='.git/' \
    --exclude='.github/' \
    --exclude='node_modules/' \
    --exclude='tests/' \
    --exclude='storage/' \
    --exclude='.env' \
    --exclude='k6/' \
    ./ "${SSH_USER}@${SSH_HOST}:${RELEASE_DIR}/"

# 4. Configure Symlinks, Run Migrations, and Optimize Cache
echo "[4/6] Linking shared storage, running migrations, and caching configurations..."
ssh -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" << EOF
    set -e
    cd "${RELEASE_DIR}"

    # Link shared .env and storage
    if [ ! -f "${TARGET_PATH}/.env" ]; then
        echo "ERROR: ${TARGET_PATH}/.env does not exist on server!"
        exit 1
    fi
    ln -sfn "${TARGET_PATH}/.env" .env
    rm -rf storage
    ln -sfn "${TARGET_PATH}/storage" storage

    # Run database migrations atomically
    echo "  -> Running database migrations..."
    php artisan migrate --force

    # Clear and rebuild optimized caches (routes, config, views, events)
    echo "  -> Optimizing application caches..."
    php artisan optimize:clear
    php artisan optimize

    # Link public storage to public/storage
    php artisan storage:link || true
EOF

# 5. Atomic Symlink Swap
echo "[5/6] Performing atomic symlink swap to release ${TIMESTAMP}..."
ssh -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" << EOF
    set -e
    # Atomic switch using temporary symlink
    ln -sfn "${RELEASE_DIR}" "${TARGET_PATH}/current_tmp"
    mv -Tf "${TARGET_PATH}/current_tmp" "${TARGET_PATH}/current"

    # Ensure webroot public_html points to current/public
    if [ ! -L "${TARGET_PATH}/public_html" ] && [ ! -d "${TARGET_PATH}/public_html" ]; then
        ln -sfn "${TARGET_PATH}/current/public" "${TARGET_PATH}/public_html"
    fi

    # Restart queue worker pulse to pick up new code
    cd "${TARGET_PATH}/current"
    php artisan queue:restart || true
EOF

# 6. Post-Deployment Health Check Verification
echo "[6/6] Verifying deployment health endpoint..."
HEALTH_URL="https://${DOMAIN}/health"
echo "  -> Pinging ${HEALTH_URL}..."

set +e
HTTP_RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 15 "${HEALTH_URL}" || echo "000")
set -e

if [ "$HTTP_RESPONSE" -eq 200 ]; then
    echo "  -> Health check PASSED (HTTP 200 OK)."
else
    echo "  -> WARNING: Health check returned HTTP ${HTTP_RESPONSE}."
    echo "     If critical failure occurred, run: ./scripts/deploy/rollback.sh ${ENVIRONMENT}"
fi

# 7. Prune Old Releases (Keep last 5)
echo "Cleaning up older releases, keeping the 5 most recent..."
ssh -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" << EOF
    cd "${TARGET_PATH}/releases"
    ls -1dt 20* | tail -n +6 | xargs -r rm -rf
EOF

echo ""
echo "================================================================="
echo " Deployment Successfully Completed for [$ENVIRONMENT]!"
echo " Active Release: ${TIMESTAMP}"
echo "================================================================="

