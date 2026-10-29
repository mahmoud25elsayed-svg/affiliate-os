#!/usr/bin/env bash

# ==============================================================================
# AffiliateOS Instant Rollback Script for Hostinger
# Reverts the active symlink to the previous successful release
# Usage: ./scripts/deploy/rollback.sh [staging|production]
# ==============================================================================

set -euo pipefail

ENVIRONMENT="${1:-staging}"

echo "================================================================="
echo " Initiating Instant Rollback for [$ENVIRONMENT]"
echo "================================================================="

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

echo "Connecting to Hostinger: ${SSH_USER}@${SSH_HOST}:${SSH_PORT}..."

ssh -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" << EOF
    set -e
    cd "${TARGET_PATH}/releases"

    # Find the previous release
    PREVIOUS_RELEASE=\$(ls -1dt 20* | sed -n '2p')

    if [ -z "\$PREVIOUS_RELEASE" ]; then
        echo "ERROR: No previous release found in ${TARGET_PATH}/releases to rollback to!"
        exit 1
    fi

    echo "Current Release: \$(readlink -f ${TARGET_PATH}/current)"
    echo "Rolling back to: ${TARGET_PATH}/releases/\$PREVIOUS_RELEASE"

    # Atomic symlink swap
    ln -sfn "${TARGET_PATH}/releases/\$PREVIOUS_RELEASE" "${TARGET_PATH}/current_tmp"
    mv -Tf "${TARGET_PATH}/current_tmp" "${TARGET_PATH}/current"

    # Re-cache configuration and routes
    cd "${TARGET_PATH}/current"
    php artisan optimize:clear
    php artisan optimize

    # Restart queue worker
    php artisan queue:restart || true

    echo "Rollback atomic swap completed successfully."
EOF

echo ""
echo "Verifying health status after rollback..."
HEALTH_URL="https://${DOMAIN}/health"
set +e
HTTP_RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 15 "${HEALTH_URL}" || echo "000")
set -e

if [ "$HTTP_RESPONSE" -eq 200 ]; then
    echo "Health check after rollback PASSED (HTTP 200 OK)."
else
    echo "WARNING: Post-rollback health check returned HTTP ${HTTP_RESPONSE}."
fi

echo "================================================================="
echo " Instant Rollback Finished for [$ENVIRONMENT]!"
echo "================================================================="

