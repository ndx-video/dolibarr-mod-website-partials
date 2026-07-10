#!/usr/bin/env bash
# Run Dolibarr first-time install inside partials-dolibarr (PostgreSQL requires CLI install).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DEV="${ROOT}/dev"

if [[ -f "${DEV}/.env" ]]; then
  set -a
  # shellcheck disable=SC1091
  source "${DEV}/.env"
  set +a
fi

DOLI_ADMIN_LOGIN="${DOLI_ADMIN_LOGIN:-admin}"
DOLI_ADMIN_PASSWORD="${DOLI_ADMIN_PASSWORD:-admin_pass_change_me}"
DOLI_URL_ROOT="${DOLI_URL_ROOT:-https://partials.gandalf.lan}"
POSTGRES_USER="${POSTGRES_USER:-dolibarr_user}"
POSTGRES_DB="${POSTGRES_DB:-dolibarr}"

if docker exec partials-db psql -U "${POSTGRES_USER}" -d "${POSTGRES_DB}" -tAc \
  "SELECT 1 FROM llx_const WHERE name = 'MAIN_VERSION_LAST_INSTALL' LIMIT 1" 2>/dev/null | grep -q 1; then
  echo "Dolibarr already installed."
  exit 0
fi

if docker exec partials-dolibarr test -f /var/www/documents/install.lock 2>/dev/null; then
  echo "Removing stale install.lock (install did not finish)."
  docker exec partials-dolibarr rm -f /var/www/documents/install.lock
fi

echo "==> Running Dolibarr PostgreSQL install (CLI)"
docker exec partials-dolibarr bash -c "cd /var/www/html/install && \
  php step1.php set auto /var/www/html /var/www/documents '${DOLI_URL_ROOT}' && \
  php step2.php set auto && \
  php step5.php '' '' auto set '${DOLI_ADMIN_LOGIN}' '${DOLI_ADMIN_PASSWORD}' '${DOLI_ADMIN_PASSWORD}' 1"

if ! docker exec partials-dolibarr test -f /var/www/documents/install.lock; then
  echo "ERROR: Dolibarr install did not create install.lock" >&2
  exit 1
fi

if ! docker exec partials-db psql -U "${POSTGRES_USER}" -d "${POSTGRES_DB}" -tAc \
  "SELECT 1 FROM llx_const WHERE name = 'MAIN_VERSION_LAST_INSTALL' LIMIT 1" | grep -q 1; then
  echo "ERROR: Dolibarr install did not record MAIN_VERSION_LAST_INSTALL" >&2
  exit 1
fi

# CLI install runs as root inside the container; Apache serves as www-data.
echo "==> Fixing ownership on /var/www/documents (www-data must write module dirs)"
docker exec partials-dolibarr chown -R www-data:www-data /var/www/documents

echo "Dolibarr install complete."
