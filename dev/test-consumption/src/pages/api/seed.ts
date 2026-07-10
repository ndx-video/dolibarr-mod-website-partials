import type { APIRoute } from 'astro';
import { getConfig } from '../../lib/config';
import { seedFixtures } from '../../lib/suite';

export const POST: APIRoute = async () => {
  const config = getConfig();
  if (!config.apiKey) {
    return new Response(JSON.stringify({ ok: false, error: 'DOLAPIKEY not set' }), {
      status: 400,
      headers: { 'Content-Type': 'application/json' },
    });
  }
  try {
    const result = await seedFixtures(config);
    return new Response(JSON.stringify(result), {
      status: result.ok ? 200 : 500,
      headers: { 'Content-Type': 'application/json' },
    });
  } catch (err) {
    return new Response(
      JSON.stringify({
        ok: false,
        error: err instanceof Error ? err.message : String(err),
      }),
      { status: 500, headers: { 'Content-Type': 'application/json' } },
    );
  }
};

export const GET: APIRoute = async () => {
  return new Response(JSON.stringify({ error: 'Use POST' }), {
    status: 405,
    headers: { 'Content-Type': 'application/json' },
  });
};
