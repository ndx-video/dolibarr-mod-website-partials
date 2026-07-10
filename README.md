# dolibarr-mod-website-partials

Dolibarr **22** custom module that surfaces **HTML content islands** from the bundled [Website CMS](https://wiki.dolibarr.org/index.php/Module_Website#Introduction) over HTTPS.

Volunteers author pages in the Website UI (`main-website`). This module publishes those containers as partials for a headless front end — today the Astro site at [braypark.church](https://braypark.church) ([ndx-video/cloudflare-worker-braypark](https://github.com/ndx-video/cloudflare-worker-braypark)).

## Status

**v1.0 released** — module complete (roadmap **P0–P2**). Tagged `v1.0`.

| | |
|--|--|
| Code | [`htdocs/custom/websitepartials/`](htdocs/custom/websitepartials/) |
| Verified | `partials.gandalf.lan` + [`dev/test-consumption/`](dev/test-consumption/) |
| Module plan | **[ROADMAP.md](ROADMAP.md)** (complete) |
| Consumer / ops | **[roadmap-handover.md](roadmap-handover.md)** → Astro repo M1 |

| Surface | Auth | Purpose |
|---------|------|---------|
| `…/public/partials/{website_ref}/{slug}.html` | None | Published HTML fragment |
| `…/public/partials/{website_ref}/{slug}.json` | None | `{ slug, title, body, updatedAt }` |
| `GET/POST/PUT/DELETE /api/index.php/websitepartials/…` | `DOLAPIKEY` | Site + container CRUD (per-type module rights) |

Default website ref: **`main-website`**. Slug = Dolibarr `pageurl`. Unpublished pages → `404` on public URLs. Public path does **not** execute page PHP.

## Target install path

```text
htdocs/custom/websitepartials/
```

Deploy onto the Bray Park Dolibarr host (`admin.braypark.church` / `api.braypark.church`) — production enablement is covered in [roadmap-handover.md](roadmap-handover.md), not this module’s open roadmap.

## Local module-dev Dolibarr

Lean Dolibarr **22** + Postgres stack for developing this module on gandalf (no Sentinel).

```bash
./dev/scripts/up.sh     # start + first-time install
./dev/scripts/down.sh   # stop (keeps volumes)
```

| | |
|--|--|
| URL | https://partials.gandalf.lan |
| Admin | see `dev/.env` (from `dev/.env.example`) |
| Module mount | `htdocs/custom/websitepartials` → `/var/www/html/custom/websitepartials` |

Requires Docker network `gandalf_proxy` and Caddy site blocks for `partials.gandalf.lan` (fragment in `dev/caddy/`). Secrets live in `dev/.env` (gitignored).

**Consumer harness:** [`dev/test-consumption/`](dev/test-consumption/) — Astro app that runs REST + public island checks and a mini island demo (`npm run dev` on port 4322).

For the Bray Park Sentinel portal stack, use [dolibarr-braypark](https://github.com/ndx-video/dolibarr-braypark) (`dlb.gandalf.lan` / `sndlb.gandalf.lan`) instead.

## Related repos

| Repo | Role |
|------|------|
| [cloudflare-worker-braypark](https://github.com/ndx-video/cloudflare-worker-braypark) | Astro consumer — wire islands via [roadmap-handover.md](roadmap-handover.md) / that repo’s M1 |
| [dolibarr-braypark](https://github.com/ndx-video/dolibarr-braypark) | Local Sentinel + sealed Dolibarr stack |

## Docs

- [ROADMAP.md](ROADMAP.md) — module milestones P0–P2 (**v1.0 complete**) and public/REST contracts
- [roadmap-handover.md](roadmap-handover.md) — Astro wiring + production ops (former P3/P4)
- [AGENTS.md](AGENTS.md) — agent orientation
- [.progress/README.md](.progress/README.md) — append-only progress log (Dot Progress)
- [htdocs/custom/websitepartials/README.md](htdocs/custom/websitepartials/README.md) — module install / API notes

## License

GPL-3.0-or-later (see module [`COPYING`](htdocs/custom/websitepartials/COPYING)). Website template content in Dolibarr may carry CC-BY-SA.
