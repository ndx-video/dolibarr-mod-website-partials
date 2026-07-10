# websitepartials

Dolibarr **22** external module: surface HTML content islands from the [Website CMS](https://wiki.dolibarr.org/index.php/Module_Website#Introduction) over HTTPS for headless consumers (e.g. [braypark.church](https://braypark.church)).

Install path: `htdocs/custom/websitepartials/`

## Status

**v1.0** — public `.html`/`.json` islands, DOLAPIKEY REST (full site/container CRUD), admin setup page. Repo [ROADMAP.md](../../../ROADMAP.md) is complete; consumer/ops: [roadmap-handover.md](../../../roadmap-handover.md).

## Dependencies

- Dolibarr ≥ 22.0
- Module **Website** (`modWebsite`) — required
- Module **API** (`modApi`) — required for REST

## Enable

1. Place this tree under `htdocs/custom/websitepartials/` (or use the repo `dev/` bind-mount).
2. Version is `1.0.0` (stable). Local `dev/scripts/up.sh` still sets `MAIN_FEATURES_LEVEL=2` for convenience.
3. Enable via **Home → Setup → Modules**, or `./dev/scripts/up.sh` (calls `activateModule`, not SQL-only const flips).
4. Grant API users rights under **Users → Permissions → Website Partials**.

## Permissions

Nested toggles (`websitepartials/{object}/{action}`):

| Object | Actions |
|--------|---------|
| `website` | `read`, `write`, `delete` — sites |
| `page`, `blogpost`, `menu`, `banner`, `other`, `service`, `library`, `setup` | `read`, `write`, `delete` — containers of that `type_container` |

Core Website UI rights remain separate for volunteers editing in the Website module.

## REST (DOLAPIKEY)

Base: `/api/index.php/websitepartials/`

| Method | Path | Right |
|--------|------|-------|
| `GET` | `status` | any module right |
| `GET` | `websites` | `website/read` |
| `GET` | `websites/{ref}` | `website/read` |
| `POST` | `websites` | `website/write` |
| `PUT` | `websites/{ref}` | `website/write` |
| `DELETE` | `websites/{ref}` | `website/delete` |
| `GET` | `websites/{ref}/pages?status=&type=` | `{type}/read` (omit `type` → union of readable types) |
| `GET` | `websites/{ref}/pages/{slug}` | `{type}/read` |
| `POST` | `websites/{ref}/pages` | `{type}/write` |
| `PUT` | `websites/{ref}/pages/{slug}` | `{type}/write` |
| `DELETE` | `websites/{ref}/pages/{slug}` | `{type}/delete` |

Path `pages` = containers of any type. Auth: `DOLAPIKEY` header.

## Public islands (no API key)

Base: `/custom/websitepartials/public/`

| Method | Path | Notes |
|--------|------|-------|
| `GET` | `partials/{website_ref}/{slug}.html` | Raw HTML fragment; published only |
| `GET` | `partials/{website_ref}/{slug}.json` | `{ slug, title, body, updatedAt }` |

Pretty URLs require a reverse-proxy rewrite onto `partial.php` PATH_INFO (see `dev/caddy/partials.gandalf.lan.caddy`). Direct fallback:

```text
/custom/websitepartials/public/partial.php/partials/{website_ref}/{slug}.json
```

Unpublished / missing → `404`. Malformed ref/slug → `400`. IP allowlist via `WEBSITEPARTIALS_PUBLIC_ALLOWED_IPS`. PHP in page content is **not** executed.

## Setup (admin)

**Home → Setup → Modules → Website Partials → Setup**, or `/custom/websitepartials/admin/setup.php`.

| Setting | Const | Notes |
|---------|-------|-------|
| REST IP restriction | Global `API_RESTRICT_ON_IP` | Exact IPs only (Dolibarr Web Services / API). Linked from setup; not duplicated here. |
| Public path IPs / CIDR | `WEBSITEPARTIALS_PUBLIC_ALLOWED_IPS` | For `/custom/websitepartials/public/…` only. Empty = allow all. Supports CIDR. |
| Default website ref | `WEBSITEPARTIALS_DEFAULT_WEBSITE_REF` | Default `main-website` |
| Public Cache-Control | `WEBSITEPARTIALS_CACHE_CONTROL` | Used by P1 public responses |
| Consumer URLs | `WEBSITEPARTIALS_CONSUMER_URLS` | One `https://` URL per line — **jump links only**, not CORS |

Example:

```bash
curl -sk -H "DOLAPIKEY: $KEY" -H 'Content-Type: application/json' \
  -X POST 'https://partials.gandalf.lan/api/index.php/websitepartials/websites/testsite/pages' \
  -d '{"slug":"welcome","title":"Welcome","body":"<p>…</p>","status":"draft","type":"page"}'
```

## Layout

```text
websitepartials/
  core/modules/modWebsitePartials.class.php
  admin/setup.php
  langs/en_US/websitepartials.lang
  class/api_websitepartials.class.php
  lib/websitepartials.lib.php
  public/partial.php   # public .html / .json router
```

## License

GNU GPL v3 — see [COPYING](COPYING).
