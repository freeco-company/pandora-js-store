/**
 * Next.js 16 instrumentation hook — runs once on server startup.
 * Initializes Sentry for both Node (SSR) and Edge runtimes when
 * NEXT_PUBLIC_SENTRY_DSN is set. No-op when DSN is missing.
 */

export async function register() {
  if (!process.env.NEXT_PUBLIC_SENTRY_DSN) return;

  if (process.env.NEXT_RUNTIME === 'nodejs') {
    await import('./sentry.server.config');
  } else if (process.env.NEXT_RUNTIME === 'edge') {
    await import('./sentry.edge.config');
  }
}

export async function onRequestError(...args: Parameters<typeof import('@sentry/nextjs').captureRequestError>) {
  if (!process.env.NEXT_PUBLIC_SENTRY_DSN) return;
  const { captureRequestError } = await import('@sentry/nextjs');
  return captureRequestError(...args);
}
