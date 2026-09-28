import { chromium, FullConfig } from '@playwright/test';
import { execSync } from 'child_process';
import { readFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const E2E_BASE_URL = 'http://127.0.0.1:8001';
const E2E_ENV = { ...process.env, APP_ENV: 'e2e', DB_DATABASE: 'awareness_e2e' };

async function globalSetup(config: FullConfig) {
  // ── 1. VERIFY SAFETY GUARD (non-destructive) ──────────────────
  // Prove the command refuses wrong environments without touching the DB.
  console.log('[global-setup] Verifying safety guard (wrong env → refuse) …');
  let guardRefused = false;
  try {
    execSync('php artisan ttx:e2e-fixture', {
      env: { ...E2E_ENV, APP_ENV: 'production' },
      stdio: 'pipe',
      timeout: 15_000,
    });
  } catch {
    guardRefused = true;
  }
  if (!guardRefused) {
    throw new Error('Safety guard did NOT refuse APP_ENV=production — aborting.');
  }
  console.log('[global-setup] Safety guard verified.');

  // ── 2. FIRST FIXTURE RUN (migrate:fresh + seed + fixtures) ─────
  console.log('[global-setup] Running ttx:e2e-fixture (run 1/2) …');
  execSync('php artisan ttx:e2e-fixture', {
    env: E2E_ENV,
    stdio: 'inherit',
    timeout: 60_000,
  });
  console.log('[global-setup] Fixture run 1 complete.');

  // ── 3. REPEATABILITY CHECK (second run proves deterministic) ───
  console.log('[global-setup] Running ttx:e2e-fixture (run 2/2 — repeatability) …');
  execSync('php artisan ttx:e2e-fixture', {
    env: E2E_ENV,
    stdio: 'inherit',
    timeout: 60_000,
  });

  const metadata = JSON.parse(
    readFileSync(resolve(__dirname, '.fixtures/ttx.json'), 'utf-8'),
  );
  if (!metadata.readySessionId || !metadata.conflictSessionId || !metadata.dirtySessionId) {
    throw new Error('Fixture metadata missing required session IDs after repeatability run.');
  }
  console.log(`[global-setup] Repeatability verified. Ready=${metadata.readySessionId} Conflict=${metadata.conflictSessionId} Dirty=${metadata.dirtySessionId}`);

  // ── 4. SUPERADMIN AUTH ────────────────────────────────────────
  // Existing E2E specs depend on superadmin storageState.
  const browser = await chromium.launch();
  const saPage = await browser.newPage();

  await saPage.goto(`${E2E_BASE_URL}/login`);
  await saPage.waitForSelector('input#email', { timeout: 15000 });

  await saPage.fill('input#email', 'superadmin@platform.local');
  await saPage.locator('input[type="password"]').first().fill('password');
  await saPage.click('button[type="submit"]');

  await saPage.waitForURL('**/dashboard', { timeout: 15000 });

  await saPage.context().storageState({ path: 'tests/e2e/.auth/superadmin.json' });
  console.log('[global-setup] SuperAdmin storageState saved.');

  // ── 5. FACILITATOR AUTH ──────────────────────────────────────
  // Deterministic facilitator for facilitator-console E2E tests.
  const facPage = await browser.newPage();

  await facPage.goto(`${E2E_BASE_URL}/login`);
  await facPage.waitForSelector('input#email', { timeout: 15000 });

  await facPage.fill('input#email', 'facilitator@e2e.local');
  await facPage.locator('input[type="password"]').first().fill('password');
  await facPage.click('button[type="submit"]');

  // Facilitator (User role) lands on /me/dashboard after login.
  await facPage.waitForURL('**/dashboard', { timeout: 15000 });

  await facPage.context().storageState({ path: 'tests/e2e/.auth/facilitator.json' });
  console.log('[global-setup] Facilitator storageState saved.');

  await browser.close();
}

export default globalSetup;
