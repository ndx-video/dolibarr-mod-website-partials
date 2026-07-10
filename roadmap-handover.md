# Handover — consumer & ops (former P3 / P4)

Work that was originally planned as **P3 — Astro wiring** and **P4 — Hardening & runbook** in this module’s roadmap. It does **not** belong to the `websitepartials` module codebase.

**Module status:** [ROADMAP.md](ROADMAP.md) is **v1.0 complete** (P0–P2). Public islands and REST CRUD are implemented and tested.

**Primary home for this work:** [ndx-video/cloudflare-worker-braypark](https://github.com/ndx-video/cloudflare-worker-braypark) — fold into that repo’s `ROADMAP.md` **M1** (and ops notes as needed). Record progress in the Astro repo’s `.progress/`, not here.

Upstream contracts (do not change without a module release): [ROADMAP.md](ROADMAP.md) public + REST sections.

---

## Astro consumer alignment

Today the Astro consumer’s `src/lib/content-api.ts` expects:

```text
GET {CONTENT_API_URL}/islands/{slug}
→ { id, slug, title, body, updatedAt? }
```

**Target:**

1. Set Worker `CONTENT_API_URL` to the public partials base (or a thin alias that preserves `/islands/{slug}`).
2. Prefer **`.json`** so `ContentBlock` stays JSON; map `body` as HTML.
3. Update Astro `ContentIsland.astro` to render `body` as HTML (trusted CMS fragment), not plain text.
4. Default `website_ref` = `main-website` (env override allowed, e.g. `CONTENT_WEBSITE_REF`).

Suggested fetch shape after wiring:

```text
{CONTENT_API_URL}/partials/main-website/{slug}.json
```

or keep `/islands/{slug}` as a rewrite/alias on the Dolibarr or Caddy side so the Astro client stays stable.

Local shape already proven by this repo’s [`dev/test-consumption/`](dev/test-consumption/) (demo + suite) against `partials.gandalf.lan`.

---

## Former P3 — Astro wiring

**Goal:** Live content islands on the Cloudflare Worker from published partials.

**Done when:**

- [ ] `CONTENT_API_URL` set in production (Worker var) to the public partials base or `/islands` alias
- [ ] Astro `content-api.ts` fetches `.json` (or alias) and maps to `ContentBlock` (incl. `CONTENT_WEBSITE_REF` if needed)
- [ ] Astro `ContentIsland.astro` renders HTML `body` safely as trusted CMS HTML
- [ ] Error handling / placeholder fallback when the origin is unreachable
- [ ] At least one real island (`welcome`) served in production — satisfies Astro ROADMAP M1
- [ ] Public path reachable from the Worker (DNS/Tunnel/`WEBSITEPARTIALS_PUBLIC_ALLOWED_IPS` includes Worker egress if allowlist is non-empty)

**Out of scope:** Migrating all static page copy into Dolibarr; removing the beta password gate; expanding this repo’s local harness

---

## Former P4 — Hardening, deploy & runbook

**Goal:** Production-ready ops notes, Bray Park Dolibarr enablement, and volunteer-safe publishing.

**Done when:**

- [ ] Module enabled on production Bray Park Dolibarr (`admin.braypark.church`, 22.x)
- [ ] Grant `websitepartials` rights to API users; set public allowlist / cache consts on module setup
- [ ] CORS policy documented (Worker server-side fetch may need none; browser fetch must not be required)
- [ ] Optional edge allowlist / Tunnel notes (Worker → Dolibarr) documented
- [ ] Rate-limit or abuse notes for the public path
- [ ] Volunteer runbook copied into church ops docs (module README already has the authoring steps)
- [x] Drafts never leak on public URLs (module harness: unpublished → `404`)
- [x] Module setup: REST uses global `API_RESTRICT_ON_IP`; public path uses `WEBSITEPARTIALS_PUBLIC_ALLOWED_IPS` (CIDR); consumer URL jump list (convenience only)

**IP split:** REST → Dolibarr `API_RESTRICT_ON_IP` (exact IPs). Public islands → module CIDR allowlist. Consumer URLs on the setup page are jump links only — not a CORS allowlist.

Document empty-allowlist semantics when deploying: confirm whether empty `WEBSITEPARTIALS_PUBLIC_ALLOWED_IPS` means allow-all or deny-all before exposing production.

**Out of scope:** Cloudflare Access on public partials; Turnstile

---

## Volunteer verify (after Astro M1)

After consumer wiring: confirm the island on [braypark.church](https://braypark.church) in addition to:

- `…/partials/main-website/welcome.json`
- `…/partials/main-website/welcome.html`

---

## Suggested next step in the Astro repo

1. Copy or link this file’s checklists into `cloudflare-worker-braypark` `ROADMAP.md` M1 (and an ops subsection if useful).
2. Decide fetch URL shape: direct `.json` vs `/islands/{slug}` Caddy alias.
3. Enable the module on production Dolibarr, then set `CONTENT_API_URL` and ship one `welcome` island.
