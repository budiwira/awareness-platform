import { test, expect, type Page } from '@playwright/test';
import { readFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const metadata = JSON.parse(
  readFileSync(resolve(__dirname, '.fixtures/ttx.json'), 'utf-8'),
);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Wait until the facilitator console header is visible (data loaded). */
async function waitForConsoleLoad(page: Page) {
  await page.waitForSelector('.console-header', { state: 'visible', timeout: 15_000 });
}

/**
 * Wait for the session-data GET endpoint to respond.
 * The Vue component fetches session data via axios GET to /ttx/sessions/{id}
 * on mount and after every refresh. This helper waits for one such response.
 */
function waitForSessionRefresh(page: Page, sessionId: number) {
  return page.waitForResponse(
    (r) =>
      r.url().endsWith(`/ttx/sessions/${sessionId}`) &&
      r.request().method() === 'GET' &&
      r.status() === 200,
    { timeout: 15_000 },
  );
}

// ===========================================================================
// SCENARIO 1 — FULL HAPPY PATH
// ===========================================================================

test.describe('Facilitator Console — Full Happy Path', () => {
  test('complete exercise lifecycle from READY to DEBRIEF', async ({ page }) => {
    const sessionId = metadata.readySessionId;

    // ── Navigate ──────────────────────────────────────────────────
    await page.goto(`/ttx/sessions/${sessionId}/console`);
    await waitForConsoleLoad(page);

    // ── VERIFY READY STATE ────────────────────────────────────────
    await expect(page.getByText('Siap untuk dimulai')).toBeVisible();

    const startButton = page.getByRole('button', { name: 'Mulai Exercise' });
    await expect(startButton).toBeVisible();
    await expect(startButton).toBeEnabled();

    // No ResponseEditor before start
    await expect(page.getByRole('form', { name: 'Response Tim' })).not.toBeVisible();

    // No advance action before start
    await expect(
      page.getByRole('button', { name: 'Rilis Inject Berikutnya' }),
    ).not.toBeVisible();
    await expect(
      page.getByRole('button', { name: 'Selesaikan Inject & Masuk Debrief' }),
    ).not.toBeVisible();

    // Facilitator identity badge visible
    await expect(page.getByText('Fasilitator', { exact: true })).toBeVisible();

    // ── CLICK: Mulai Exercise ─────────────────────────────────────
    await startButton.click();

    // ── VERIFY AFTER START ────────────────────────────────────────
    await expect(page.getByText('Exercise berhasil dimulai.')).toBeVisible({
      timeout: 10_000,
    });

    // Authoritative session status becomes IN_PROGRESS
    await expect(
      page.locator('.console-header-meta').getByText('Berlangsung'),
    ).toBeVisible();

    // First inject becomes ACTIVE — ResponseEditor available
    await expect(page.getByRole('form', { name: 'Response Tim' })).toBeVisible();

    // "Mulai Exercise" no longer visible
    await expect(startButton).not.toBeVisible();

    // ── SECURITY: pending inject never renders ResponseEditor ─────
    // Click a pending inject in the timeline
    const pendingTimelineBtn = page
      .locator('.timeline-btn')
      .filter({ hasText: 'Inject 3: Scenario Update' });
    await pendingTimelineBtn.click();

    // Response section is not rendered for pending injects
    await expect(page.getByText('Response Tim')).not.toBeVisible();
    await expect(
      page.getByRole('button', { name: /Simpan Response|Simpan Perubahan/ }),
    ).not.toBeVisible();

    // ── Create response for each inject ───────────────────────────
    const injectCount = 3;

    for (let i = 0; i < injectCount; i++) {
      // Select the active inject in the timeline
      const activeTimelineBtn = page
        .locator('.timeline-btn')
        .filter({ hasText: `Inject ${i + 1}: Scenario Update` });
      await activeTimelineBtn.click();

      // ResponseEditor should be visible for active inject
      await expect(page.getByRole('form', { name: 'Response Tim' })).toBeVisible();

      // Fill decision (required)
      await page
        .getByLabel('Keputusan *')
        .fill(`Decision for inject ${i + 1}: validated and contained`);

      // Fill rationale
      await page
        .getByLabel('Rationale')
        .fill(`Rationale for inject ${i + 1}: reduces exposure while preserving evidence`);

      // Save
      const saveButton = page.getByRole('button', { name: /Simpan Response|Simpan Perubahan/ });
      await expect(saveButton).toBeEnabled();
      await saveButton.click();

      // Verify successful save — use .last() because previous iteration's
      // toast may still be in the DOM (toasts stack until auto-dismiss).
      await expect(
        page.getByText(/Response berhasil disimpan|Response berhasil diperbarui/).last(),
      ).toBeVisible({ timeout: 10_000 });

      // Saved response remains visible
      await expect(page.getByLabel('Keputusan *')).toHaveValue(
        `Decision for inject ${i + 1}: validated and contained`,
      );

      // Progression action enabled
      const isFinal = i === injectCount - 1;
      const expectedLabel = isFinal
        ? 'Selesaikan Inject & Masuk Debrief'
        : 'Rilis Inject Berikutnya';
      const advanceButton = page.getByRole('button', { name: expectedLabel });
      await expect(advanceButton).toBeEnabled();

      // ── ADVANCE ─────────────────────────────────────────────────
      // Set up wait for session refresh BEFORE clicking advance,
      // so we can wait for it after the toast.
      const refreshPromise = waitForSessionRefresh(page, sessionId);

      await advanceButton.click();

      await expect(page.getByText('Inject berhasil dilanjutkan.').last()).toBeVisible({
        timeout: 10_000,
      });

      // Wait for session data to refresh so the next iteration
      // sees correct inject statuses.
      await refreshPromise;
    }

    // ── VERIFY DEBRIEF ───────────────────────────────────────────
    await expect(
      page.getByText('Exercise telah memasuki fase debrief.'),
    ).toBeVisible();

    // No ResponseEditor in debrief
    await expect(page.getByRole('form', { name: 'Response Tim' })).not.toBeVisible();

    // No Save / Start / Advance buttons
    await expect(
      page.getByRole('button', { name: /Simpan Response|Simpan Perubahan/ }),
    ).not.toBeVisible();
    await expect(page.getByRole('button', { name: 'Mulai Exercise' })).not.toBeVisible();
    await expect(
      page.getByRole('button', { name: /Rilis Inject Berikutnya|Selesaikan Inject & Masuk Debrief/ }),
    ).not.toBeVisible();

    // ── LOCKED INJECT PROTECTION ─────────────────────────────────
    // Click on a locked inject and verify read-only display
    const lastLockedBtn = page
      .locator('.timeline-btn')
      .filter({ hasText: `Inject ${injectCount}: Scenario Update` });
    await lastLockedBtn.click();

    // Locked response is reviewable
    await expect(page.getByText('Response terkunci dan tidak dapat diubah.')).toBeVisible();
    await expect(page.getByText(`Decision for inject ${injectCount}`)).toBeVisible();

    // No editable controls for locked inject
    await expect(
      page.getByRole('button', { name: /Simpan Response|Simpan Perubahan/ }),
    ).not.toBeVisible();
  });
});

// ===========================================================================
// SCENARIO 2 — REAL OPTIMISTIC CONCURRENCY 409
// ===========================================================================

test.describe('Facilitator Console — Real Optimistic Concurrency 409', () => {
  test('stale-revision conflict produces real HTTP 409', async ({ browser }) => {
    const sessionId = metadata.conflictSessionId;
    const facilitatorStatePath = resolve(__dirname, '.auth/facilitator.json');
    const storageState = JSON.parse(readFileSync(facilitatorStatePath, 'utf-8'));

    const contextA = await browser.newContext({ storageState });
    const contextB = await browser.newContext({ storageState });

    try {
      const pageA = await contextA.newPage();
      const pageB = await contextB.newPage();

      // ── Both contexts load the session BEFORE either saves ──────
      await pageA.goto(`/ttx/sessions/${sessionId}/console`);
      await waitForConsoleLoad(pageA);
      await expect(pageA.getByRole('form', { name: 'Response Tim' })).toBeVisible();

      await pageB.goto(`/ttx/sessions/${sessionId}/console`);
      await waitForConsoleLoad(pageB);
      await expect(pageB.getByRole('form', { name: 'Response Tim' })).toBeVisible();

      // ── CONTEXT A: modify decision locally (do NOT save yet) ────
      await pageA.getByLabel('Keputusan *').fill('Context A stale decision');

      // ── CONTEXT B: modify decision and save successfully ────────
      await pageB.getByLabel('Keputusan *').fill('Context B authoritative decision');

      // Set up response observation for Context B
      const [responseB] = await Promise.all([
        pageB.waitForResponse(
          (r) =>
            r.url().includes(`/ttx/sessions/${sessionId}/responses`) &&
            r.request().method() === 'PUT',
        ),
        pageB.getByRole('button', { name: /Simpan Response|Simpan Perubahan/ }).click(),
      ]);

      expect(responseB.status()).toBe(200);

      // Context B shows success
      await expect(
        pageB.getByText(/Response berhasil disimpan|Response berhasil diperbarui/),
      ).toBeVisible({ timeout: 10_000 });

      // ── CONTEXT A: save stale revision → real 409 ──────────────
      const [responseA] = await Promise.all([
        pageA.waitForResponse(
          (r) =>
            r.url().includes(`/ttx/sessions/${sessionId}/responses`) &&
            r.request().method() === 'PUT',
        ),
        pageA.getByRole('button', { name: /Simpan Response|Simpan Perubahan/ }).click(),
      ]);

      // Proof: Context A received real HTTP 409
      expect(responseA.status()).toBe(409);

      // ── VERIFY REAL 409 UX ─────────────────────────────────────
      // "Konflik Versi" appears
      await expect(pageA.getByText('Konflik Versi')).toBeVisible();

      // Stale A draft remains visible (not overwritten)
      await expect(pageA.getByLabel('Keputusan *')).toHaveValue('Context A stale decision');

      // "Muat Versi Terbaru" action is visible
      const loadLatestBtn = pageA.getByRole('button', { name: 'Muat Versi Terbaru' });
      await expect(loadLatestBtn).toBeVisible();

      // ── CLICK: Muat Versi Terbaru ───────────────────────────────
      await loadLatestBtn.click();

      // Editor now shows Context B's authoritative decision
      await expect(pageA.getByLabel('Keputusan *')).toHaveValue(
        'Context B authoritative decision',
      );

      // Conflict alert disappears
      await expect(pageA.getByText('Konflik Versi')).not.toBeVisible();

      // Editor is clean again (no dirty state)
      await pageA.waitForTimeout(500);
    } finally {
      await contextA.close();
      await contextB.close();
    }
  });
});

// ===========================================================================
// SCENARIO 3 — DIRTY DRAFT PROTECTION
// ===========================================================================

test.describe('Facilitator Console — Dirty Draft Protection', () => {
  test('unsaved changes protected on timeline switch and refresh', async ({ page }) => {
    const sessionId = metadata.dirtySessionId;

    // ── Navigate ──────────────────────────────────────────────────
    await page.goto(`/ttx/sessions/${sessionId}/console`);
    await waitForConsoleLoad(page);

    // Active inject (inject 2) should be auto-selected
    await expect(page.getByRole('form', { name: 'Response Tim' })).toBeVisible();

    // ── Track mutation requests ───────────────────────────────────
    let mutationRequestCount = 0;
    await page.route('**/ttx/sessions/*/responses**', (route) => {
      const method = route.request().method();
      if (method === 'PUT' || method === 'POST') {
        mutationRequestCount++;
      }
      route.continue();
    });

    // ── MODIFY decision locally (without saving) ─────────────────
    await page.getByLabel('Keputusan *').fill('Unsaved facilitator draft');

    // ── TIMELINE SWITCH ───────────────────────────────────────────
    // Click on the locked inject (inject 1)
    const lockedBtn = page
      .locator('.timeline-btn')
      .filter({ hasText: 'Inject 1: Scenario Update' });
    await lockedBtn.click();

    // VERIFY modal appears
    await expect(page.getByText('Perubahan belum disimpan')).toBeVisible();

    // ── CLICK: Tetap di sini ─────────────────────────────────────
    await page.getByRole('button', { name: 'Tetap di sini' }).click();

    // Modal closes
    await expect(page.getByText('Perubahan belum disimpan')).not.toBeVisible();

    // ACTIVE inject remains selected (ResponseEditor still visible)
    await expect(page.getByRole('form', { name: 'Response Tim' })).toBeVisible();

    // Local value remains intact
    await expect(page.getByLabel('Keputusan *')).toHaveValue('Unsaved facilitator draft');

    // No response update request was made
    expect(mutationRequestCount).toBe(0);

    // ── MANUAL REFRESH ───────────────────────────────────────────
    await page.getByRole('button', { name: 'Refresh' }).click();

    // VERIFY same unsaved-change modal appears
    await expect(page.getByText('Perubahan belum disimpan')).toBeVisible();

    // ── CLICK: Buang perubahan ───────────────────────────────────
    await page.getByRole('button', { name: 'Buang perubahan' }).click();

    // Modal closes
    await expect(page.getByText('Perubahan belum disimpan')).not.toBeVisible();

    // Authoritative refresh happened — decision field is now server value (empty).
    // handleUnsavedDiscard fires fetchSession(true) which is NOT awaited in the
    // handler, so the HTTP response arrives before Vue re-mounts the editor.
    // Wait for the input to actually clear (Vue reactivity + component re-mount).
    await expect(page.getByLabel('Keputusan *')).toHaveValue('');

    // Dirty state clears — clicking locked inject does NOT trigger modal
    await lockedBtn.click();
    await expect(page.getByText('Perubahan belum disimpan')).not.toBeVisible();

    // No accidental PUT/POST occurred for the discarded local draft
    expect(mutationRequestCount).toBe(0);

    await page.unroute('**/ttx/sessions/*/responses**');
  });
});
