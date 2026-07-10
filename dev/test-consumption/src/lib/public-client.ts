import { getConfig, publicPartialsBase, type AppConfig } from './config';
import type { PublicIsland } from './types';

export interface PublicResponse {
  ok: boolean;
  status: number;
  contentType: string | null;
  cacheControl: string | null;
  raw: string;
  json: PublicIsland | null;
  error?: string;
}

export async function fetchPublicPartial(
  websiteRef: string,
  slug: string,
  format: 'json' | 'html',
  config: AppConfig = getConfig(),
): Promise<PublicResponse> {
  const url = `${publicPartialsBase(config)}/${encodeURIComponent(websiteRef)}/${encodeURIComponent(slug)}.${format}`;
  try {
    const res = await fetch(url, {
      headers: {
        Accept: format === 'json' ? 'application/json' : 'text/html',
      },
    });
    const raw = await res.text();
    let json: PublicIsland | null = null;
    let error: string | undefined;
    if (format === 'json' && raw) {
      try {
        json = JSON.parse(raw) as PublicIsland;
      } catch {
        error = 'Invalid JSON';
      }
    }
    return {
      ok: res.ok,
      status: res.status,
      contentType: res.headers.get('content-type'),
      cacheControl: res.headers.get('cache-control'),
      raw,
      json,
      error,
    };
  } catch (err) {
    return {
      ok: false,
      status: 0,
      contentType: null,
      cacheControl: null,
      raw: '',
      json: null,
      error: err instanceof Error ? err.message : String(err),
    };
  }
}

/** Hit a raw path under the public base (for malformed-path tests). */
export async function fetchPublicRawPath(
  pathSuffix: string,
  config: AppConfig = getConfig(),
): Promise<PublicResponse> {
  const url = `${publicPartialsBase(config)}/${pathSuffix.replace(/^\/+/, '')}`;
  try {
    const res = await fetch(url);
    const raw = await res.text();
    return {
      ok: res.ok,
      status: res.status,
      contentType: res.headers.get('content-type'),
      cacheControl: res.headers.get('cache-control'),
      raw,
      json: null,
    };
  } catch (err) {
    return {
      ok: false,
      status: 0,
      contentType: null,
      cacheControl: null,
      raw: '',
      json: null,
      error: err instanceof Error ? err.message : String(err),
    };
  }
}
