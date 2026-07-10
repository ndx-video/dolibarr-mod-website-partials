import type { APIRoute } from 'astro';
import { getConfig } from '../../lib/config';
import { runSuite } from '../../lib/suite';

export const POST: APIRoute = async () => {
  try {
    const report = await runSuite(getConfig());
    return new Response(JSON.stringify(report), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    });
  } catch (err) {
    return new Response(
      JSON.stringify({
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
