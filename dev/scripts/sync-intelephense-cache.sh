#!/usr/bin/env bash
# Copy Dolibarr htdocs from the module-dev container for Intelephense indexing.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DEST="${ROOT}/dev/.cache/dolibarr-htdocs"
mkdir -p "${DEST}"
docker exec partials-dolibarr tar -C /var/www/html -cf - . | tar -C "${DEST}" -xf -
echo "Synced Dolibarr htdocs -> ${DEST}"
