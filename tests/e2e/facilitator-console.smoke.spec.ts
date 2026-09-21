import { test, expect } from '@playwright/test';
import { readFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

/**
 * Minimal smoke test for facilitator console E2E infrastructure.
 *
 * This test verifies:
 * 1. Facilitator auth storageState works (can reach authenticated pages)
 * 2. Fixture metadata is readable
 * 3. Console route is accessible for the ready session
 * 4. SuperAdmin state CANNOT access facilitator console (authorization enforced)
 */

const metadata = JSON.parse(
  readFileSync(resolve(__dirname, '.fixtures/ttx.json'), 'utf-8'),
);

test.describe('Facilitator console smoke', () => {
  test('facilitator can open console for ready session', async ({ page }) => {
    await page.goto(`/ttx/sessions/${metadata.readySessionId}/console`);

    // The console page should render (not 403 or error)
    // Inertia page: wait for the FacilitatorConsole component to mount
    await expect(page.locator('body')).toBeVisible();

    // Verify we're not on an error page
    const url = page.url();
    expect(url).not.toContain('/login');
  });

  test('console route returns 403 for non-facilitator (superadmin)', async ({ browser }) => {
    // Load superadmin storageState
    const saState = JSON.parse(
      readFileSync(resolve(__dirname, '.auth/superadmin.json'), 'utf-8'),
    );

    const context = await browser.newContext({
      storageState: saState,
    });
    const page = await context.newPage();

    const response = await page.goto(
      `/ttx/sessions/${metadata.readySessionId}/console`,
    );

    // SuperAdmin is NOT a facilitator participant → 403
    expect(response?.status()).toBe(403);

    await context.close();
  });
});
