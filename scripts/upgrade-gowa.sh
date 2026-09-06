#!/usr/bin/env bash
#
# Upgrade GOWA WhatsApp Service to v9.2.2.
# Run on the server where the GOWA Docker container is running.
#
# Usage:
#   GOWA_BASIC_AUTH=<user:password> GOWA_IMAGE_DIGEST=sha256:<approved-digest> bash scripts/upgrade-gowa.sh
#   GOWA_IMAGE_DIGEST=sha256:<approved-digest> bash scripts/upgrade-gowa.sh --dry-run
#
set -euo pipefail

DRY_RUN=false
if [[ "${1:-}" == "--dry-run" ]]; then
    DRY_RUN=true
    echo ">>> DRY RUN MODE - no changes will be made <<<"
    echo
fi

# Configuration
IMAGE="aldinokemal2104/go-whatsapp-web-multidevice"
IMAGE_DIGEST="${GOWA_IMAGE_DIGEST:?Set GOWA_IMAGE_DIGEST to an approved sha256 digest}"
[[ "${IMAGE_DIGEST}" =~ ^sha256:[0-9a-f]{64}$ ]] || { echo 'GOWA_IMAGE_DIGEST must use sha256:<64 lowercase hex characters>.' >&2; exit 1; }
IMAGE_REF="${IMAGE}@${IMAGE_DIGEST}"
CONTAINER_NAME="${GOWA_CONTAINER:-gowa-whatsapp}"
GOWA_PORT="${GOWA_PORT:-3000}"
VOLUME_NAME="${GOWA_VOLUME:-gowa-storages}"
STATICS_VOLUME="${GOWA_STATICS_VOLUME:-gowa-statics}"

echo "=== GOWA Upgrade to ${IMAGE_REF} ==="
echo "Container : ${CONTAINER_NAME}"
echo "Port      : ${GOWA_PORT}"
echo "Volume    : ${VOLUME_NAME}"
echo "Statics   : ${STATICS_VOLUME}"
echo

# 1. Pull the pinned release image.
echo "[1/4] Pulling GOWA image..."
if $DRY_RUN; then
    echo "  (dry-run) docker pull ${IMAGE_REF}"
else
    BASIC_AUTH="${GOWA_BASIC_AUTH:?Set GOWA_BASIC_AUTH before running this script}"
    docker pull "${IMAGE_REF}"
fi

# 2. Stop & remove existing container
echo "[2/4] Replacing existing container..."
if $DRY_RUN; then
    echo "  (dry-run) docker stop ${CONTAINER_NAME} && docker rm ${CONTAINER_NAME}"
else
    if docker ps -a --format '{{.Names}}' | grep -qx "${CONTAINER_NAME}"; then
        docker stop "${CONTAINER_NAME}" || true
        docker rm "${CONTAINER_NAME}" || true
    else
        echo "  No existing container '${CONTAINER_NAME}' found."
    fi
fi

# 3. Start the replacement container.
echo "[3/4] Starting GOWA ${IMAGE_REF}..."
if $DRY_RUN; then
    echo "  (dry-run) docker run -d \\"
    echo "    --name ${CONTAINER_NAME} \\"
    echo "    --restart always \\"
    echo "    -p ${GOWA_PORT}:3000 \\"
    echo "    -v ${VOLUME_NAME}:/app/storages \\"
    echo "    -v ${STATICS_VOLUME}:/app/statics \\"
    echo "    -e APP_BASIC_AUTH=***REDACTED*** \\"
    echo "    -e APP_PORT=3000 \\"
    echo "    -e APP_DEBUG=false \\"
    echo "    -e APP_UI_ENABLED=false \\"
    echo "    -e MCP_ENABLED=false \\"
    echo "    ${IMAGE_REF} rest"
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
        -e MCP_ENABLED=false \
        "${IMAGE_REF}" rest
fi

# 4. Verify
echo "[4/4] Verifying upgrade..."
if $DRY_RUN; then
    echo "  (dry-run) curl -s http://localhost:${GOWA_PORT}/health"
    echo "  (dry-run) curl -s --user '***REDACTED***' http://localhost:${GOWA_PORT}/app/info"
else
    HEALTH="000"
    for attempt in {1..10}; do
        HEALTH=$(curl -s -o /dev/null -w '%{http_code}' "http://localhost:${GOWA_PORT}/health" 2>/dev/null || true)
        if [[ "${HEALTH}" == "200" ]]; then
            break
        fi

        sleep 3
    done

    if [[ "${HEALTH}" != "200" ]]; then
        echo "ERROR: GOWA health check failed after 30 seconds (HTTP ${HEALTH})." >&2
        exit 1
    fi

    echo "  /health -> HTTP ${HEALTH}"
    APP_INFO=$(curl -s --user "${BASIC_AUTH}" "http://localhost:${GOWA_PORT}/app/info" 2>/dev/null || echo '{}')
    VERSION=$(printf '%s' "${APP_INFO}" | grep -oE '"version"[[:space:]]*:[[:space:]]*"[^"]*"' 2>/dev/null || true)
    echo "  GOWA version: ${VERSION:-N/A}"
fi

echo
echo "=== Upgrade complete ==="
echo "Verify at: http://localhost:${GOWA_PORT}/health"
echo "API docs: https://aldinokemal-go-whatsapp-web-multidevice.mintlify.app"
