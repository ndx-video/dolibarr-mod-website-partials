# Agent Guide — website-partials

Instructions for AI coding agents working in this repository.

## Progress logging (Dot Progress)

Read and **implement** [.progress/README.md](.progress/README.md). That file is the single source of truth for the Dot Progress convention — entry types, subfolder layout, naming, immutability, YAML frontmatter template, and when to append progress files.

Entries live in type subfolders: `.progress/milestone/` (`M{n}.{index}.*`), `.progress/general/` (`G{number}.*`), `.progress/fixes/` (`F{number}.*`). Milestone index is three-digit per milestone; General/Fix use a global six-digit counter per prefix.

This repo ships the optional agent-harness layer:

- [.claude/rules/dotprogress.md](.claude/rules/dotprogress.md) — path-glob rule (loads on `.progress/**` / `ROADMAP.md`)
- [.claude/skills/dotprogress/SKILL.md](.claude/skills/dotprogress/SKILL.md) — append-an-entry skill

**Append a new progress entry** when a meaningful session completes — do not edit prior entries.

### Milestone numbering vs ROADMAP labels

[ROADMAP.md](ROADMAP.md) covers **P0–P2** (complete at **v1.0**). Dot Progress filenames use **`M000`–`M002`**:

| ROADMAP | Progress filename prefix | Frontmatter `milestone` |
|---------|--------------------------|-------------------------|
| P0 — Spec & scaffold | `M000` | `P0 — Spec & scaffold` |
| P1 — Public partials | `M001` | `P1 — Public partials` |
| P2 — REST control plane | `M002` | `P2 — REST control plane` |

Former P3/P4 (Astro wiring, production deploy, ops) live in [roadmap-handover.md](roadmap-handover.md) and should be planned/recorded in [cloudflare-worker-braypark](https://github.com/ndx-video/cloudflare-worker-braypark).

## Project context

Custom Dolibarr module that exposes Website CMS containers as HTTPS content islands for [braypark.church](https://braypark.church).

| Area | Location |
|------|----------|
| Milestone plan (v1.0 complete) | [ROADMAP.md](ROADMAP.md) |
| Consumer / ops handover | [roadmap-handover.md](roadmap-handover.md) |
| Human docs | [README.md](README.md) |
| Module code | `htdocs/custom/websitepartials/` |
| Public router | `public/partial.php` |
| REST API | `class/api_websitepartials.class.php` |
| Shared helpers | `lib/websitepartials.lib.php` |
| Descriptor | `core/modules/modWebsitePartials.class.php` |

### Locked product decisions

- Authoring: Dolibarr Website UI only (`main-website`)
- Public islands: **no API key**; both `.html` and `.json`
- REST: **DOLAPIKEY** for full site + container CRUD (incl. DELETE); module-owned per-type rights
- Slug = `pageurl`; unpublished → public `404`
- Do **not** execute Website PHP on the public path
- Do **not** fork Dolibarr core or query `llx_website*` from a sidecar as the primary API

### Hosts

| Host | Role |
|------|------|
| `admin.braypark.church` | Dolibarr UI + module public path |
| `api.braypark.church` | API alias (same appliance) |
| `braypark.church` | Astro consumer (separate repo) |
| `partials.gandalf.lan` | Local module-dev Dolibarr (this repo’s `dev/` stack) |

### Local module-dev stack

Compose under `dev/` — Dolibarr 22 + Postgres on `gandalf_proxy`, no host ports.

```bash
./dev/scripts/up.sh
./dev/scripts/down.sh
```

- Bind-mount: `htdocs/custom/websitepartials` → container `/var/www/html/custom/websitepartials`
- Secrets: `dev/.env` (gitignored; copy from `dev/.env.example`)
- Caddy fragment: `dev/caddy/partials.gandalf.lan.caddy`
- Sentinel / sealed ERP: separate repo [dolibarr-braypark](https://github.com/ndx-video/dolibarr-braypark) (`dlb.gandalf.lan`)

### Stack

- **Dolibarr 22** external module under `htdocs/custom/`
- **Website** module for authoring
- **REST** via Restler (`api_*.class.php`) for the control plane
- Consumer: Astro + Cloudflare Workers ([cloudflare-worker-braypark](https://github.com/ndx-video/cloudflare-worker-braypark))

## Working convention

**Module plan in [ROADMAP.md](ROADMAP.md) (v1.0 done). Consumer/ops in [roadmap-handover.md](roadmap-handover.md). Record module work in [.progress/](.progress/).**

For further module changes, append a General or Fix progress entry (or a new milestone if a post-v1.0 roadmap phase is added).
