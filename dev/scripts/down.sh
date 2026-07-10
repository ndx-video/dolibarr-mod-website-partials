#!/usr/bin/env bash
# Stop module-dev Dolibarr stack (keeps named volumes).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "${ROOT}/dev"

echo "==> Stopping Docker Compose stack"
docker compose down

echo "==> Status"
docker ps -a --format '  {{.Names}} {{.Status}}' | grep -E 'partials-' || echo "  (none)"
