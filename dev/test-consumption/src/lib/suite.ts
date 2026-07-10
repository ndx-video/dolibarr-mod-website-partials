import { getConfig, type AppConfig } from './config';
import { fetchPublicPartial, fetchPublicRawPath } from './public-client';
import * as rest from './rest-client';
import type { SuiteReport, TestResult, TestStatus } from './types';

function preview(raw: string, max = 280): string {
  const t = raw.replace(/\s+/g, ' ').trim();
  return t.length > max ? `${t.slice(0, max)}…` : t;
}

async function runCase(
  id: string,
  name: string,
  group: 'rest' | 'public',
  fn: () => Promise<{ status: TestStatus; httpStatus?: number; detail: string; bodyPreview?: string }>,
): Promise<TestResult> {
  const started = Date.now();
  try {
    const out = await fn();
    return {
      id,
      name,
      group,
      status: out.status,
      httpStatus: out.httpStatus,
      detail: out.detail,
      bodyPreview: out.bodyPreview,
      durationMs: Date.now() - started,
    };
  } catch (err) {
    return {
      id,
      name,
      group,
      status: 'fail',
      detail: err instanceof Error ? err.message : String(err),
      durationMs: Date.now() - started,
    };
  }
}

function pass(detail: string, httpStatus?: number, bodyPreview?: string) {
  return { status: 'pass' as const, detail, httpStatus, bodyPreview };
}

function fail(detail: string, httpStatus?: number, bodyPreview?: string) {
  return { status: 'fail' as const, detail, httpStatus, bodyPreview };
}

/**
 * Seed fixtures used by public tests on WEBSITE_REF.
 * Creates/updates: welcome (published), draftonly (draft), phpliteral (published with <?php).
 */
export async function seedFixtures(config: AppConfig = getConfig()) {
  const ref = config.websiteRef;
  const site = await rest.ensureWebsite(ref, config);
  if (!site.ok) {
    return { ok: false as const, error: site.error || `ensureWebsite ${site.status}`, site };
  }

  const pages = [
    {
      slug: 'welcome',
      title: 'Welcome',
      body: '<p>Harness <strong>welcome</strong> island.</p>',
      status: 'published',
      type: 'page',
    },
    {
      slug: 'draftonly',
      title: 'Draft Only',
      body: '<p>Should not appear on public path.</p>',
      status: 'draft',
      type: 'page',
    },
    {
      slug: 'phpliteral',
      title: 'PHP Literal',
      body: '<p>Before</p><?php echo "EXECUTED"; ?><p>After</p>',
      status: 'published',
      type: 'page',
    },
    {
      slug: 'blog-one',
      title: 'Blog One',
      body: '<p>Blogpost container.</p>',
      status: 'published',
      type: 'blogpost',
    },
  ];

  const results = [];
  for (const page of pages) {
    const existing = await rest.getPage(ref, page.slug, config);
    if (existing.ok) {
      results.push(await rest.updatePage(ref, page.slug, page, config));
    } else {
      results.push(await rest.createPage(ref, page, config));
    }
  }

  return { ok: true as const, ref, site: site.data, pages: results };
}

export async function runSuite(config: AppConfig = getConfig()): Promise<SuiteReport> {
  const startedAt = new Date().toISOString();
  const results: TestResult[] = [];
  const ref = config.websiteRef;

  if (!config.apiKey) {
    results.push({
      id: 'config-apikey',
      name: 'DOLAPIKEY configured',
      group: 'rest',
      status: 'fail',
      detail: 'Set DOLAPIKEY in .env',
      durationMs: 0,
    });
    const finishedAt = new Date().toISOString();
    return {
      startedAt,
      finishedAt,
      results,
      summary: { pass: 0, fail: 1, skip: 0 },
    };
  }

  // --- REST ---
  results.push(
    await runCase('rest-status', 'GET status', 'rest', async () => {
      const r = await rest.getStatus(config);
      if (!r.ok || !r.data?.db_ok || r.data.module !== 'websitepartials') {
        return fail(`Unexpected status payload`, r.status, preview(r.raw));
      }
      return pass(`module=${r.data.module} db_ok=${r.data.db_ok}`, r.status, preview(r.raw));
    }),
  );

  results.push(
    await runCase('rest-auth-401', 'GET status without key → 401', 'rest', async () => {
      const r = await rest.getStatusNoKey(config);
      if (r.status !== 401) {
        return fail(`Expected 401, got ${r.status}`, r.status, preview(r.raw));
      }
      return pass('Unauthorized without DOLAPIKEY', r.status, preview(r.raw));
    }),
  );

  results.push(
    await runCase('rest-website-crud', 'Website create / get / update', 'rest', async () => {
      await rest.deleteWebsite(ref, config); // ignore result
      const created = await rest.createWebsite(
        { ref, description: 'Harness site', status: 1 },
        config,
      );
      if (!created.ok || created.data?.ref !== ref) {
        // may already exist from race — try get
        const got = await rest.getWebsite(ref, config);
        if (!got.ok) {
          return fail(`Create failed: ${created.error || created.status}`, created.status, preview(created.raw));
        }
      }
      const updated = await rest.updateWebsite(ref, { description: 'Harness site updated' }, config);
      if (!updated.ok) {
        return fail(`Update failed: ${updated.error}`, updated.status, preview(updated.raw));
      }
      const got = await rest.getWebsite(ref, config);
      if (!got.ok || got.data?.description !== 'Harness site updated') {
        return fail('Get after update mismatch', got.status, preview(got.raw));
      }
      return pass(`Website ${ref} ok`, got.status, preview(got.raw));
    }),
  );

  results.push(
    await runCase('rest-page-crud', 'Page create / get / update (page type)', 'rest', async () => {
      await rest.ensureWebsite(ref, config);
      const slug = 'crudpage';
      await rest.deletePage(ref, slug, config);
      const created = await rest.createPage(
        ref,
        {
          slug,
          title: 'CRUD Page',
          body: '<p>v1</p>',
          status: 'draft',
          type: 'page',
        },
        config,
      );
      if (!created.ok) {
        return fail(`Create page failed: ${created.error}`, created.status, preview(created.raw));
      }
      const updated = await rest.updatePage(
        ref,
        slug,
        { title: 'CRUD Page', body: '<p>v2</p>', status: 'published' },
        config,
      );
      if (!updated.ok || updated.data?.status !== 1) {
        return fail(`Publish update failed`, updated.status, preview(updated.raw));
      }
      const got = await rest.getPage(ref, slug, config);
      if (!got.ok || got.data?.body !== '<p>v2</p>') {
        return fail('Get page body mismatch', got.status, preview(got.raw));
      }
      return pass(`Page ${slug} published`, got.status, preview(got.raw));
    }),
  );

  results.push(
    await runCase('rest-blogpost', 'Create blogpost container', 'rest', async () => {
      await rest.ensureWebsite(ref, config);
      const slug = 'blogcrud';
      await rest.deletePage(ref, slug, config);
      const created = await rest.createPage(
        ref,
        {
          slug,
          title: 'Blog CRUD',
          body: '<p>blog</p>',
          status: 'published',
          type: 'blogpost',
        },
        config,
      );
      if (!created.ok || created.data?.type !== 'blogpost') {
        return fail(`blogpost create failed: ${created.error}`, created.status, preview(created.raw));
      }
      return pass('blogpost created', created.status, preview(created.raw));
    }),
  );

  results.push(
    await runCase('rest-list-filters', 'List pages status/type filters', 'rest', async () => {
      await seedFixtures(config);
      const published = await rest.listPages(ref, { status: 'published' }, config);
      const drafts = await rest.listPages(ref, { status: 'draft' }, config);
      const all = await rest.listPages(ref, { status: 'all' }, config);
      const pagesOnly = await rest.listPages(ref, { status: 'all', type: 'page' }, config);
      if (!published.ok || !drafts.ok || !all.ok || !pagesOnly.ok) {
        return fail('List request failed', published.status);
      }
      const pubSlugs = (published.data || []).map((p) => p.slug);
      const draftSlugs = (drafts.data || []).map((p) => p.slug);
      if (!pubSlugs.includes('welcome') || draftSlugs.includes('welcome')) {
        return fail(`published filter wrong: ${pubSlugs.join(',')}`, published.status);
      }
      if (!draftSlugs.includes('draftonly')) {
        return fail(`draft filter missing draftonly`, drafts.status);
      }
      return pass(
        `published=${pubSlugs.length} draft=${draftSlugs.length} all=${(all.data || []).length}`,
        all.status,
      );
    }),
  );

  results.push(
    await runCase('rest-slug-max', 'Slug >16 chars → 400', 'rest', async () => {
      await rest.ensureWebsite(ref, config);
      const r = await rest.createPage(
        ref,
        {
          slug: 'this-slug-is-way-too-long',
          title: 'Too Long',
          body: '<p>x</p>',
          status: 'draft',
          type: 'page',
        },
        config,
      );
      if (r.status !== 400) {
        return fail(`Expected 400, got ${r.status}`, r.status, preview(r.raw));
      }
      return pass('Rejected long slug', r.status, preview(r.raw));
    }),
  );

  results.push(
    await runCase('rest-slug-dup', 'Duplicate slug → 409', 'rest', async () => {
      await rest.ensureWebsite(ref, config);
      const slug = 'dupslug';
      await rest.deletePage(ref, slug, config);
      const first = await rest.createPage(
        ref,
        { slug, title: 'Dup', body: '<p>a</p>', status: 'draft', type: 'page' },
        config,
      );
      if (!first.ok) {
        return fail(`First create failed: ${first.error}`, first.status, preview(first.raw));
      }
      const second = await rest.createPage(
        ref,
        { slug, title: 'Dup2', body: '<p>b</p>', status: 'draft', type: 'page' },
        config,
      );
      if (second.status !== 409) {
        return fail(`Expected 409, got ${second.status}`, second.status, preview(second.raw));
      }
      return pass('Duplicate rejected', second.status, preview(second.raw));
    }),
  );

  results.push(
    await runCase('rest-status-transition', 'Draft ↔ published via PUT', 'rest', async () => {
      await rest.ensureWebsite(ref, config);
      const slug = 'toggle';
      await rest.deletePage(ref, slug, config);
      await rest.createPage(
        ref,
        { slug, title: 'Toggle', body: '<p>t</p>', status: 'draft', type: 'page' },
        config,
      );
      const pub = await rest.updatePage(ref, slug, { status: 'published' }, config);
      if (!pub.ok || pub.data?.status !== 1) {
        return fail('Publish failed', pub.status, preview(pub.raw));
      }
      const draft = await rest.updatePage(ref, slug, { status: 'draft' }, config);
      if (!draft.ok || draft.data?.status !== 0) {
        return fail('Unpublish failed', draft.status, preview(draft.raw));
      }
      return pass('Status toggled', draft.status);
    }),
  );

  // --- Public ---
  const seed = await seedFixtures(config);
  if (!seed.ok) {
    results.push({
      id: 'public-seed',
      name: 'Seed public fixtures',
      group: 'public',
      status: 'fail',
      detail: seed.error || 'seed failed',
      durationMs: 0,
    });
  }

  results.push(
    await runCase('public-json', 'Published .json shape + Cache-Control', 'public', async () => {
      const r = await fetchPublicPartial(ref, 'welcome', 'json', config);
      if (!r.ok || !r.json) {
        return fail(`json fetch failed: ${r.error || r.status}`, r.status, preview(r.raw));
      }
      const { slug, title, body, updatedAt } = r.json;
      const keys = Object.keys(r.json).sort().join(',');
      if (keys !== 'body,slug,title,updatedAt' && keys !== 'body,slug,title') {
        // allow missing updatedAt key only if null omitted — still require the four fields present
        if (!('slug' in r.json) || !('title' in r.json) || !('body' in r.json) || !('updatedAt' in r.json)) {
          return fail(`Unexpected keys: ${keys}`, r.status, preview(r.raw));
        }
      }
      if (slug !== 'welcome' || !title || !body?.includes('welcome')) {
        return fail('Payload fields mismatch', r.status, preview(r.raw));
      }
      if (!r.cacheControl || !r.cacheControl.includes('max-age')) {
        return fail(`Missing Cache-Control: ${r.cacheControl}`, r.status);
      }
      if (!r.contentType?.includes('application/json')) {
        return fail(`Bad Content-Type: ${r.contentType}`, r.status);
      }
      return pass(`updatedAt=${updatedAt ?? 'null'}`, r.status, preview(r.raw));
    }),
  );

  results.push(
    await runCase('public-html', 'Published .html fragment', 'public', async () => {
      const r = await fetchPublicPartial(ref, 'welcome', 'html', config);
      if (!r.ok) {
        return fail(`html fetch failed`, r.status, preview(r.raw));
      }
      if (!r.contentType?.includes('text/html')) {
        return fail(`Bad Content-Type: ${r.contentType}`, r.status);
      }
      if (!r.raw.includes('welcome')) {
        return fail('Body missing expected content', r.status, preview(r.raw));
      }
      return pass('HTML fragment ok', r.status, preview(r.raw));
    }),
  );

  results.push(
    await runCase('public-draft-404', 'Draft page → 404', 'public', async () => {
      const r = await fetchPublicPartial(ref, 'draftonly', 'json', config);
      if (r.status !== 404) {
        return fail(`Expected 404, got ${r.status}`, r.status, preview(r.raw));
      }
      return pass('Draft hidden', r.status);
    }),
  );

  results.push(
    await runCase('public-missing-404', 'Missing slug → 404', 'public', async () => {
      const r = await fetchPublicPartial(ref, 'nosuchpage', 'json', config);
      if (r.status !== 404) {
        return fail(`Expected 404, got ${r.status}`, r.status, preview(r.raw));
      }
      return pass('Missing hidden', r.status);
    }),
  );

  results.push(
    await runCase('public-malformed-400', 'Malformed path → 400', 'public', async () => {
      // Space / illegal chars — Caddy may encode; use a path that fails our regex after rewrite
      const r = await fetchPublicRawPath(`${ref}/bad!.json`, config);
      if (r.status !== 400 && r.status !== 404) {
        // Some proxies may 404 before rewrite; accept 400 preferred
        return fail(`Expected 400 (or 404), got ${r.status}`, r.status, preview(r.raw));
      }
      if (r.status === 404) {
        return pass('Malformed rejected (404 from proxy/path)', r.status);
      }
      return pass('Malformed → 400', r.status, preview(r.raw));
    }),
  );

  results.push(
    await runCase('public-no-php-exec', 'PHP in content not executed', 'public', async () => {
      const r = await fetchPublicPartial(ref, 'phpliteral', 'html', config);
      if (!r.ok) {
        return fail('fetch failed', r.status, preview(r.raw));
      }
      if (r.raw.includes('EXECUTED') && !r.raw.includes('<?php')) {
        return fail('PHP appears to have executed', r.status, preview(r.raw));
      }
      if (!r.raw.includes('<?php')) {
        return fail('Expected literal <?php in body', r.status, preview(r.raw));
      }
      return pass('PHP served as text', r.status, preview(r.raw));
    }),
  );

  // Optional cleanup note: leave demo-partials site for demo page reuse
  const finishedAt = new Date().toISOString();
  const summary = results.reduce(
    (acc, r) => {
      acc[r.status] += 1;
      return acc;
    },
    { pass: 0, fail: 0, skip: 0 },
  );

  return { startedAt, finishedAt, results, summary };
}
