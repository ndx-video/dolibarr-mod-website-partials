# websitepartials test-consumption

Astro **5** harness + island demo for the local module-dev Dolibarr at `https://partials.gandalf.lan`.

## What it covers

| Page | Purpose |
|------|---------|
| `/` | Env status + REST health |
| `/harness` | Interactive REST + public partials suite (pass/fail) |
| `/demo` | P3-shaped consumer: fetch `.json`, render HTML `body` |

Server-side API routes keep `DOLAPIKEY` off the browser:

- `POST /api/run-suite`
- `POST /api/seed`

## Setup

```bash
cd dev/test-consumption
cp .env.example .env
# set DOLAPIKEY from Dolibarr Users → admin → API key
npm install
npm run dev   # http://localhost:4322
```

| Variable | Default | Role |
|----------|---------|------|
| `PARTIALS_BASE_URL` | `http://partials.gandalf.lan` | Dolibarr origin (use `http://` for Node SSR; Caddy `tls internal` is untrusted by Node) |
| `DOLAPIKEY` | _(required)_ | REST auth |
| `WEBSITE_REF` | `demo-partials` | Destructive CRUD / seed target |
| `PUBLIC_WEBSITE_REF` | `main-website` | Island demo site |
| `PUBLIC_SLUG` | `welcome` | Island demo slug |

Requires the module-dev stack (`../scripts/up.sh`) and Caddy rewrite for pretty public URLs (see `../caddy/partials.gandalf.lan.caddy`).

## Public URL contract

```text
{PARTIALS_BASE_URL}/custom/websitepartials/public/partials/{website_ref}/{slug}.json
{PARTIALS_BASE_URL}/custom/websitepartials/public/partials/{website_ref}/{slug}.html
```
