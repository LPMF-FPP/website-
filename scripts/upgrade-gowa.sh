#!/bin/bash
#
# Upgrade GOWA WhatsApp Service to v9.0.0
# Run on the server where GOWA Docker container is running.
#
# Usage:
#   bash scripts/upgrade-gowa.sh
#   bash scripts/upgrade-gowa.sh --dry-run
#
set -euo pipefail

DRY_RUN=false
if [[ "${1:-}" == "--dry-run" ]]; then
    DRY_RUN=true
    echo ">>> DRY RUN MODE — no changes will be made <<<"
    echo
fi

# ── Configuration (adjust as needed) ─────────────────────────────┐
IMAGE="aldinokemal2104/go-whatsapp-web-multidevice"
CONTAINER_NAME="${GOWA_CONTAINER:-gowa-whatsapp}"
GOWA_PORT="${GOWA_PORT:-3000}"
BASIC_AUTH="${GOWA_BASIC_AUTH:-lpmf:lpmfjaya1}"
VOLUME_NAME="${GOWA_VOLUME:-gowa-storages}"
STATICS_VOLUME="${GOWA_STATICS_VOLUME:-gowa-statics}"
# ──────────────────────────────────────────────────────────────────┘

echo "=== GOWA Upgrade to v9.0.0 ==="
echo "Container : ${CONTAINER_NAME}"
echo "Port      : ${GOWA_PORT}"
echo "Volume    : ${VOLUME_NAME}"
echo "Statics   : ${STATICS_VOLUME}"
echo

# 1. Pull latest image
echo "[1/5] Pulling latest GOWA image..."
if $DRY_RUN; then
    echo "  (dry-run) docker pull ${IMAGE}:latest"
else
    docker pull "${IMAGE}:latest"
fi

# 2. Stop & remove existing container
echo "[2/5] Stopping existing container..."
if docker ps -a --format '{{.Names}}' | grep -qx "${CONTAINER_NAME}"; then
    if $DRY_RUN; then
        echo "  (dry-run) docker stop ${CONTAINER_NAME} && docker rm ${CONTAINER_NAME}"
    else
        docker stop "${CONTAINER_NAME}" || true
        docker rm "${CONTAINER_NAME}" || true
    fi
else
    echo "  No existing container '${CONTAINER_NAME}' found."
fi

# 3. Clear gowa-ui cache (prevents stale v8 dashboard)
echo "[3/5] Clearing gowa-ui cache..."
if $DRY_RUN; then
    echo "  (dry-run) docker volume rm ${VOLUME_NAME} ... or rm storages/ui/*"
else
    # If using a named volume, we need a temporary container to clear it
    if docker volume ls --format '{{.Name}}' | grep -qx "${VOLUME_NAME}"; then
        docker run --rm -v "${VOLUME_NAME}:/app/storages" alpine:3 \
            sh -c 'rm -rf /app/storages/ui/* 2>/dev/null; echo "  UI cache cleared"'
    fi
fi

# 4. Start new v9 container
echo "[4/5] Starting GOWA v9.0.0..."
if $DRY_RUN; then
    echo "  (dry-run) docker run -d \\"
    echo "    --name ${CONTAINER_NAME} \\"
    echo "    --restart always \\"
    echo "    -p ${GOWA_PORT}:3000 \\"
    echo "    -v ${VOLUME_NAME}:/app/storages \\"
    echo "    -v ${STATICS_VOLUME}:/app/statics \\"
    echo "    -e APP_BASIC_AUTH=${BASIC_AUTH} \\"
    echo "    -e APP_PORT=3000 \\"
    echo "    -e APP_DEBUG=false \\"
    echo "    -e APP_UI_ENABLED=false \\"
    echo "    ${IMAGE}:latest rest"
else
    docker run -d \
        --name "${CONTAINER_NAME}" \
        --restart always \
        -p "${GOWA_PORT}:3000" \
        -v "${VOLUME_NAME}:/app/storages" \
        -v "${STATICS_VOLUME}:/app/statics" \
        -e APP_BASIC_AUTH="${BASIC_AUTH}" \
        -e APP_PORT=3000 \
        -e APP_DEBUG=false \
        -e APP_UI_ENABLED=false \
        "${IMAGE}:latest" rest
fi

# 5. Verify
echo "[5/5] Verifying upgrade..."
sleep 3

if $DRY_RUN; then
    echo "  (dry-run) curl -s http://localhost:${GOWA_PORT}/health"
    echo "  (dry-run) curl -s -u ${BASIC_AUTH} http://localhost:${GOWA_PORT}/app/info"
else
    echo "  Health check:"
    HEALTH=$(curl -s -o /dev/null -w '%{http_code}' "http://localhost:${GOWA_PORT}/health" 2>/dev/null || echo '000')
    echo "    /health -> HTTP ${HEALTH}"

    echo "  App info:"
    APP_INFO=$(curl -s -u "${BASIC_AUTH}" "http://localhost:${GOWA_PORT}/app/info" 2>/dev/null || echo '{}')
    echo "    /app/info -> ${APP_INFO}"

    VERSION=$(echo "${APP_INFO}" | grep -oP '"version"\s*:\s*"[^"]*"' 2>/dev/null || echo 'N/A')
    echo
    echo "  GOWA version: ${VERSION}"
fi

echo
echo "=== Upgrade complete ==="
echo "Verify at: http://localhost:${GOWA_PORT}/health"
echo "API docs:  https://aldinokemal-go-whatsapp-web-multidevice.mintlify.app"
