#!/usr/bin/env bash
# Start module-dev Dolibarr stack and run first-time install if needed.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DEV="${ROOT}/dev"
cd "${DEV}"

if [[ ! -f .env ]]; then
  echo "==> Creating .env from .env.example"
  cp .env.example .env
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

POSTGRES_USER="${POSTGRES_USER:-dolibarr_user}"
POSTGRES_DB="${POSTGRES_DB:-dolibarr}"

if ! docker network inspect gandalf_proxy >/dev/null 2>&1; then
  echo "ERROR: Docker network gandalf_proxy is missing. Create it first:" >&2
  echo "  docker network create gandalf_proxy" >&2
  exit 1
fi

echo "==> Starting Dolibarr module-dev stack (PostgreSQL + Dolibarr 22)"
docker compose up -d

echo "==> Waiting for PostgreSQL"
for _ in $(seq 1 60); do
  if docker exec partials-db pg_isready -U "${POSTGRES_USER}" -d "${POSTGRES_DB}" >/dev/null 2>&1; then
    echo "PostgreSQL is ready."
    break
  fi
  sleep 2
done

echo "==> Waiting for Dolibarr web container"
for _ in $(seq 1 60); do
  if docker exec partials-dolibarr curl -fsS "http://127.0.0.1/install/" >/dev/null 2>&1 \
    || docker exec partials-dolibarr curl -fsS "http://127.0.0.1/" >/dev/null 2>&1; then
    echo "Dolibarr web is reachable."
    break
  fi
  sleep 3
done

"${DEV}/scripts/install.sh"

# Flip llx_const alone does NOT run module init() — menus, rights, and SQL tables
# are registered only via activateModule(). Never enable modules with SQL-only const flips.
activate_dolibarr_module() {
  local mod="$1"
  docker cp "${DEV}/scripts/activate-module.php" partials-dolibarr:/tmp/activate-module.php
  docker exec partials-dolibarr php /tmp/activate-module.php "${mod}"
}

set_const() {
  local name="$1"
  local value="$2"
  docker exec partials-db psql -U "${POSTGRES_USER}" -d "${POSTGRES_DB}" -c \
    "UPDATE llx_const SET value = '${value}' WHERE name = '${name}';
     INSERT INTO llx_const (name, value, type, visible, entity)
     SELECT '${name}', '${value}', 'chaine', 0, 1
     WHERE NOT EXISTS (SELECT 1 FROM llx_const WHERE name = '${name}');" \
    >/dev/null
}

echo "==> MAIN_FEATURES_LEVEL=2 (show development modules)"
set_const MAIN_FEATURES_LEVEL 2

echo "==> Minimal company setup (avoids setupnotcomplete UI gate)"
docker cp "${DEV}/scripts/setup-company.php" partials-dolibarr:/tmp/setup-company.php
docker exec -e DOLI_COMPANY_NAME="${DOLI_COMPANY_NAME:-Bray Park Partials Dev}" \
  -e DOLI_COMPANY_COUNTRYCODE="${DOLI_COMPANY_COUNTRYCODE:-AU}" \
  partials-dolibarr php /tmp/setup-company.php

# Modules that must be fully activated for this repo's work.
# (CLI install may already enable agenda/import/export/etc. with proper init —
#  we only force API + Website + websitepartials here.)
echo "==> Enabling Dolibarr REST API module (activateModule)"
activate_dolibarr_module modApi

echo "==> Enabling Dolibarr Website module (activateModule — creates llx_website*)"
activate_dolibarr_module modWebsite

if [[ -f "${ROOT}/htdocs/custom/websitepartials/core/modules/modWebsitePartials.class.php" ]]; then
  echo "==> Enabling websitepartials (descriptor present)"
  activate_dolibarr_module modWebsitePartials || true
fi

echo "==> Fixing /var/www/documents ownership after CLI activateModule"
docker exec partials-dolibarr chown -R www-data:www-data /var/www/documents

echo "==> Waiting for Dolibarr API"
for _ in $(seq 1 60); do
  # Root /api/index.php often returns 404 without a resource; any HTTP response means the front is up.
  code="$(docker exec partials-dolibarr curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1/api/index.php" 2>/dev/null || true)"
  if [[ "${code}" =~ ^[12345][0-9][0-9]$ ]]; then
    echo "Dolibarr API is reachable (HTTP ${code})."
    break
  fi
  sleep 3
done

echo
echo "Stack is up."
echo "  UI:  ${DOLI_URL_ROOT:-https://partials.gandalf.lan}"
echo "  Login: ${DOLI_ADMIN_LOGIN:-admin} / (see dev/.env)"
echo "  Module mount: htdocs/custom/websitepartials → /var/www/html/custom/websitepartials"
echo "  Stop: ./dev/scripts/down.sh"
