import { getConfig, restBase, type AppConfig } from './config';
import type { RestPage, RestStatus, RestWebsite } from './types';

export interface RestResponse<T> {
  ok: boolean;
  status: number;
  data: T | null;
  raw: string;
  error?: string;
}

async function restFetch<T>(
  path: string,
  init: RequestInit = {},
  config: AppConfig = getConfig(),
  withKey = true,
): Promise<RestResponse<T>> {
  const headers = new Headers(init.headers);
  if (withKey && config.apiKey) {
    headers.set('DOLAPIKEY', config.apiKey);
  }
  if (init.body && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }

  const url = `${restBase(config)}${path}`;
  try {
    const res = await fetch(url, { ...init, headers });
    const raw = await res.text();
    let data: T | null = null;
    let error: string | undefined;
    if (raw) {
      try {
        const parsed = JSON.parse(raw) as T & { error?: { message?: string } };
        data = parsed;
        if (
          parsed &&
          typeof parsed === 'object' &&
          'error' in parsed &&
          parsed.error?.message
        ) {
          error = parsed.error.message;
        }
      } catch {
        error = 'Non-JSON response';
      }
    }
    return { ok: res.ok, status: res.status, data, raw, error };
  } catch (err) {
    return {
      ok: false,
      status: 0,
      data: null,
      raw: '',
      error: err instanceof Error ? err.message : String(err),
    };
  }
}

export function getStatus(config?: AppConfig) {
  return restFetch<RestStatus>('/status', {}, config);
}

export function getStatusNoKey(config?: AppConfig) {
  return restFetch<RestStatus>('/status', {}, config, false);
}

export function listWebsites(config?: AppConfig) {
  return restFetch<RestWebsite[]>('/websites', {}, config);
}

export function getWebsite(ref: string, config?: AppConfig) {
  return restFetch<RestWebsite>(`/websites/${encodeURIComponent(ref)}`, {}, config);
}

export function createWebsite(
  body: { ref: string; description?: string; status?: number },
  config?: AppConfig,
) {
  return restFetch<RestWebsite>(
    '/websites',
    { method: 'POST', body: JSON.stringify(body) },
    config,
  );
}

export function updateWebsite(
  ref: string,
  body: Record<string, unknown>,
  config?: AppConfig,
) {
  return restFetch<RestWebsite>(
    `/websites/${encodeURIComponent(ref)}`,
    { method: 'PUT', body: JSON.stringify(body) },
    config,
  );
}

export function deleteWebsite(ref: string, config?: AppConfig) {
  return restFetch<{ success: boolean; ref: string }>(
    `/websites/${encodeURIComponent(ref)}`,
    { method: 'DELETE' },
    config,
  );
}

export function listPages(
  ref: string,
  query: Record<string, string | number> = {},
  config?: AppConfig,
) {
  const qs = new URLSearchParams();
  for (const [k, v] of Object.entries(query)) {
    qs.set(k, String(v));
  }
  const suffix = qs.toString() ? `?${qs}` : '';
  return restFetch<RestPage[]>(
    `/websites/${encodeURIComponent(ref)}/pages${suffix}`,
    {},
    config,
  );
}

export function getPage(ref: string, slug: string, config?: AppConfig) {
  return restFetch<RestPage>(
    `/websites/${encodeURIComponent(ref)}/pages/${encodeURIComponent(slug)}`,
    {},
    config,
  );
}

export function createPage(
  ref: string,
  body: Record<string, unknown>,
  config?: AppConfig,
) {
  return restFetch<RestPage>(
    `/websites/${encodeURIComponent(ref)}/pages`,
    { method: 'POST', body: JSON.stringify(body) },
    config,
  );
}

export function updatePage(
  ref: string,
  slug: string,
  body: Record<string, unknown>,
  config?: AppConfig,
) {
  return restFetch<RestPage>(
    `/websites/${encodeURIComponent(ref)}/pages/${encodeURIComponent(slug)}`,
    { method: 'PUT', body: JSON.stringify(body) },
    config,
  );
}

export function deletePage(ref: string, slug: string, config?: AppConfig) {
  return restFetch<{ success: boolean; ref: string; slug: string }>(
    `/websites/${encodeURIComponent(ref)}/pages/${encodeURIComponent(slug)}`,
    { method: 'DELETE' },
    config,
  );
}

/** Ensure website exists (create if missing). */
export async function ensureWebsite(
  ref: string,
  config: AppConfig = getConfig(),
): Promise<RestResponse<RestWebsite>> {
  const existing = await getWebsite(ref, config);
  if (existing.ok && existing.data) {
    return existing;
  }
  return createWebsite({ ref, description: 'Harness demo site', status: 1 }, config);
}
