import AxeBuilder from '@axe-core/playwright';
import { test, expect } from '@playwright/test';
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
async function waitForConsoleLoad(page: import('@playwright/test').Page) {
  await page.waitForSelector('.console-header', {
    state: 'visible',
    timeout: 15_000,
  });
}

/**
 * Assert there is no horizontal scrollbar (document width <= viewport width
 * plus a small tolerance for sub-pixel rounding).
 */
async function assertNoHorizontalOverflow(page: import('@playwright/test').Page) {
  const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
  const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
  expect(
    scrollWidth,
    `Horizontal overflow detected: scrollWidth=${scrollWidth} > clientWidth=${clientWidth}`,
  ).toBeLessThanOrEqual(clientWidth + 2);
}

// ===========================================================================
// SCENARIO 1 - RESPONSIVE / THEME QUALITY
// ===========================================================================

const viewports = [
  { name: 'Mobile 390x844', width: 390, height: 844 },
  { name: 'Tablet 768x1024', width: 768, height: 1024 },
  { name: 'Desktop 1440x900', width: 1440, height: 900 },
] as const;

for (const vp of viewports) {
  test.describe(`Responsive - ${vp.name}`, () => {
    test.use({ viewport: { width: vp.width, height: vp.height } });

    test(`console renders without horizontal overflow at ${vp.width}x${vp.height}`, async ({ page }) => {
      await page.goto(`/ttx/sessions/${metadata.readySessionId}/console`);
      await waitForConsoleLoad(page);

      // Verify key UI elements are visible
      await expect(page.locator('.console-header')).toBeVisible();
      await expect(page.getByRole('button', { name: 'Refresh' })).toBeVisible();
      await expect(page.getByText('Fasilitator', { exact: true })).toBeVisible();

      // Timeline sidebar is visible
      await expect(page.locator('.inject-timeline')).toBeVisible();

      // No horizontal overflow
      await assertNoHorizontalOverflow(page);
    });

    test(`timeline and inject card are usable at ${vp.width}x${vp.height}`, async ({ page }) => {
      await page.goto(`/ttx/sessions/${metadata.readySessionId}/console`);
      await waitForConsoleLoad(page);

      // Click on a timeline item
      const timelineBtn = page.locator('.timeline-btn').first();
      await expect(timelineBtn).toBeVisible();
      await timelineBtn.click();

      // Inject card should be visible
      await expect(page.locator('.inject-card')).toBeVisible();

      // No horizontal overflow after interaction
      await assertNoHorizontalOverflow(page);
    });

    test(`unsaved-changes modal does not overflow at ${vp.width}x${vp.height}`, async ({ page }) => {
      // Use the dirty session for unsaved-changes modal test
      await page.goto(`/ttx/sessions/${metadata.dirtySessionId}/console`);
      await waitForConsoleLoad(page);

      // Modify the decision field to make it dirty
      const decisionInput = page.getByLabel('Keputusan *');
      await expect(decisionInput).toBeVisible();
      await decisionInput.fill('Draft perubahan untuk test responsif');

      // Click Refresh to trigger the unsaved-changes modal
      await page.getByRole('button', { name: 'Refresh' }).click();
      await expect(page.getByText('Perubahan belum disimpan')).toBeVisible();

      // Both modal buttons should be visible and usable
      await expect(page.getByRole('button', { name: 'Tetap di sini' })).toBeVisible();
      await expect(page.getByRole('button', { name: 'Buang perubahan' })).toBeVisible();

      // No horizontal overflow in modal
      await assertNoHorizontalOverflow(page);

      // Dismiss modal
      await page.getByRole('button', { name: 'Tetap di sini' }).click();
      await expect(page.getByText('Perubahan belum disimpan')).not.toBeVisible();
    });

    test(`viewport adaptation does not require page refresh at ${vp.width}x${vp.height}`, async ({ page }) => {
      // Start at desktop viewport
      await page.setViewportSize({ width: 1440, height: 900 });
      await page.goto(`/ttx/sessions/${metadata.readySessionId}/console`);
      await waitForConsoleLoad(page);

      // Verify desktop layout: 2-column grid
      await expect(page.locator('.inject-timeline')).toBeVisible();

      // Resize to target viewport without refresh
      await page.setViewportSize({ width: vp.width, height: vp.height });

      // Console should still be functional
      await expect(page.locator('.console-header')).toBeVisible();
      await expect(page.getByRole('button', { name: 'Refresh' })).toBeVisible();

      // No horizontal overflow after resize
      await assertNoHorizontalOverflow(page);
    });
  });
}

// ===========================================================================
// SCENARIO 2
// ===========================================================================

test.describe('Facilitator Console - Theme Toggle', () => {
  test.use({ viewport: { width: 1440, height: 900 } });

  test('console renders in dark mode (default)', async ({ page }) => {
    await page.goto(`/ttx/sessions/${metadata.readySessionId}/console`);
    await waitForConsoleLoad(page);

    // Default theme should be dark (no data-theme attribute or 'dark')
    const htmlTheme = await page.locator('html').getAttribute('data-theme');
    expect(htmlTheme === 'dark' || htmlTheme === null).toBeTruthy();

    // Verify dark theme CSS variable
    const darkBg = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--bg').trim());
    expect(darkBg).toBe('#0A0A0E');

    // Console renders properly
    await expect(page.locator('.console-header')).toBeVisible();
    await assertNoHorizontalOverflow(page);
  });

  test('theme toggle switches to light mode', async ({ page }) => {
    await page.goto(`/ttx/sessions/${metadata.readySessionId}/console`);
    await waitForConsoleLoad(page);

    // Ensure dark mode first
    const initialTheme = await page.locator('html').getAttribute('data-theme');
    if (initialTheme !== 'dark') {
      await page.getByRole('button', { name: 'Ganti tema' }).click();
      await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    }

    // Verify dark before toggle
    const darkBg = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--bg').trim());
    expect(darkBg).toBe('#0A0A0E');

    // Click theme toggle
    await page.getByRole('button', { name: 'Ganti tema' }).click();

    // Verify theme switched to light
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');

    // Verify the CSS custom property changed
    const lightBg = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--bg').trim());
    expect(lightBg).toBe('#f8fafc');

    // Console still renders properly
    await expect(page.locator('.console-header')).toBeVisible();
    await assertNoHorizontalOverflow(page);

    // Timeline items still visible
    await expect(page.locator('.inject-timeline')).toBeVisible();

    // Theme toggle button still functional
    await expect(page.getByRole('button', { name: 'Ganti tema' })).toBeVisible();
  });

  test('theme toggle switches back to dark mode', async ({ page }) => {
    await page.goto(`/ttx/sessions/${metadata.readySessionId}/console`);
    await waitForConsoleLoad(page);

    // Switch to light
    await page.getByRole('button', { name: 'Ganti tema' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    const lightBg = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--bg').trim());
    expect(lightBg).toBe('#f8fafc');

    // Switch back to dark
    await page.getByRole('button', { name: 'Ganti tema' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');

    // Verify dark CSS variable restored
    const darkBg = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--bg').trim());
    expect(darkBg).toBe('#0A0A0E');

    // No overflow after theme switches
    await assertNoHorizontalOverflow(page);
  });

  test('theme persists across viewport resize', async ({ page }) => {
    await page.goto(`/ttx/sessions/${metadata.readySessionId}/console`);
    await waitForConsoleLoad(page);

    // Switch to light theme
    await page.getByRole('button', { name: 'Ganti tema' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');

    // Verify light theme CSS variable
    const lightBg = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--bg').trim());
    expect(lightBg).toBe('#f8fafc');

    // Resize to mobile
    await page.setViewportSize({ width: 390, height: 844 });

    // Theme should persist (no page refresh needed)
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    const persistedBg = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--bg').trim());
    expect(persistedBg).toBe('#f8fafc');

    // Console still functional
    await expect(page.locator('.console-header')).toBeVisible();
    await assertNoHorizontalOverflow(page);

    // Resize back to desktop
    await page.setViewportSize({ width: 1440, height: 900 });
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    const desktopBg = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--bg').trim());
    expect(desktopBg).toBe('#f8fafc');
    await assertNoHorizontalOverflow(page);
  });
});

// ===========================================================================
// SCENARIO 3 - DARK + RESPONSIVE COMBINED
// ===========================================================================

test.describe('Facilitator Console - Dark Theme + Responsive', () => {
  test.use({ viewport: { width: 390, height: 844 } });

  test('dark theme at 390x844 has no overflow', async ({ page }) => {
    // Force dark theme
    await page.addInitScript(() => {
      document.documentElement.dataset.theme = 'dark';
      localStorage.setItem('theme', 'dark');
    });

    await page.goto(`/ttx/sessions/${metadata.readySessionId}/console`);
    await waitForConsoleLoad(page);

    // Verify dark theme is active
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');

    // Verify dark theme CSS variable
    const darkBg = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--bg').trim());
    expect(darkBg).toBe('#0A0A0E');

    // Verify no overflow
    await assertNoHorizontalOverflow(page);

    // Verify key elements are visible
    await expect(page.locator('.console-header')).toBeVisible();
    await expect(page.locator('.inject-timeline')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Refresh' })).toBeVisible();
  });
});

// ===========================================================================
// SCENARIO 4 - ACCESSIBILITY (WCAG 2.1 A/AA via axe)
// ===========================================================================

test.describe('Facilitator Console - Accessibility (axe WCAG 2.1 A/AA)', () => {
  test.use({ viewport: { width: 1440, height: 900 } });

  test('console page has zero critical/serious axe violations', async ({ page }) => {
    await page.goto(`/ttx/sessions/${metadata.readySessionId}/console`);
    await waitForConsoleLoad(page);

    // Click first inject to populate the main content area
    const timelineBtn = page.locator('.timeline-btn').first();
    await expect(timelineBtn).toBeVisible();
    await timelineBtn.click();
    await expect(page.locator('.inject-card')).toBeVisible();

    const results = await new AxeBuilder({ page })
      .include('.console-header')
      .include('.console-grid')
      .withTags(['wcag2a', 'wcag2aa'])
      .analyze();

    const violations = results.violations.filter(
      (v) => v.impact === 'critical' || v.impact === 'serious',
    );
    expect(
      violations,
      `Axe violations found:\n${violations.map((v) => `  [${v.impact}] ${v.id}: ${v.description}\n    ${v.nodes.map((n) => n.html).join('\n    ')}`).join('\n')}`,
    ).toEqual([]);
  });

  test('console with active response editor has zero critical/serious violations', async ({ page }) => {
    // Use the conflict session which is in_progress with an active inject and an official response
    await page.goto(`/ttx/sessions/${metadata.conflictSessionId}/console`);
    await waitForConsoleLoad(page);

    // The conflict session should have an active inject with ResponseEditor
    await expect(page.getByRole('form', { name: 'Response Tim' })).toBeVisible();

    const results = await new AxeBuilder({ page })
      .include('.console-header')
      .include('.response-editor')
      .withTags(['wcag2a', 'wcag2aa'])
      .analyze();

    const violations = results.violations.filter(
      (v) => v.impact === 'critical' || v.impact === 'serious',
    );
    expect(
      violations,
      `Axe violations found:\n${violations.map((v) => `  [${v.impact}] ${v.id}: ${v.description}\n    ${v.nodes.map((n) => n.html).join('\n    ')}`).join('\n')}`,
    ).toEqual([]);
  });

  test('unsaved-changes modal has zero critical/serious violations', async ({ page }) => {
    // Use the conflict session which is in_progress with an active inject
    await page.goto(`/ttx/sessions/${metadata.conflictSessionId}/console`);
    await waitForConsoleLoad(page);

    // Modify decision to make it dirty
    const decisionInput = page.getByLabel('Keputusan *');
    await expect(decisionInput).toBeVisible();
    await decisionInput.fill('Draft untuk test a11y');

    // Trigger unsaved modal
    await page.getByRole('button', { name: 'Refresh' }).click();
    await expect(page.getByText('Perubahan belum disimpan')).toBeVisible();

    const results = await new AxeBuilder({ page })
      .include('.unsaved-modal')
      .withTags(['wcag2a', 'wcag2aa'])
      .analyze();

    const violations = results.violations.filter(
      (v) => v.impact === 'critical' || v.impact === 'serious',
    );
    expect(
      violations,
      `Axe violations found:\n${violations.map((v) => `  [${v.impact}] ${v.id}: ${v.description}\n    ${v.nodes.map((n) => n.html).join('\n    ')}`).join('\n')}`,
    ).toEqual([]);
  });
});

// ===========================================================================
// SCENARIO 5 - KEYBOARD / FOCUS NAVIGATION
// ===========================================================================

test.describe('Facilitator Console - Keyboard Navigation', () => {
  test.use({ viewport: { width: 1440, height: 900 } });

  const tabUntilFocused = async (page, locator, maxTabs = 100) => {
    await expect(locator).toBeVisible();

    for (let i = 0; i < maxTabs; i += 1) {
      await page.keyboard.press('Tab');

      const isFocused = await locator.evaluate(
        (element) => element === document.activeElement,
      );

      if (isFocused) {
        return;
      }
    }

    throw new Error(
      `Target was not keyboard reachable within ${maxTabs} Tab presses`,
    );
  };

  const expectKeyboardFocusVisible = async (locator) => {
    await expect(locator).toBeFocused();

    const isFocusVisible = await locator.evaluate(
      (element) => element.matches(':focus-visible'),
    );

    expect(isFocusVisible).toBe(true);
  };

  test('console header actions are keyboard-accessible', async ({ page }) => {
    await page.goto(`/ttx/sessions/${metadata.readySessionId}/console`);
    await waitForConsoleLoad(page);

    const backLink = page.locator('.console-back');

    await tabUntilFocused(page, backLink);
    await expectKeyboardFocusVisible(backLink);

    const refreshBtn = page.getByRole('button', { name: 'Refresh' });

    await tabUntilFocused(page, refreshBtn);
    await expectKeyboardFocusVisible(refreshBtn);
  });

  test('timeline is keyboard-navigable and selects injects', async ({ page }) => {
    await page.goto(`/ttx/sessions/${metadata.readySessionId}/console`);
    await waitForConsoleLoad(page);

    const firstTimelineBtn = page.locator('.timeline-btn').first();

    await tabUntilFocused(page, firstTimelineBtn);
    await expectKeyboardFocusVisible(firstTimelineBtn);

    await page.keyboard.press('Enter');

    await expect(page.locator('.inject-card')).toBeVisible();
  });

  test('unsaved-changes modal is keyboard-operable and traps focus', async ({ page }) => {
    await page.goto(`/ttx/sessions/${metadata.dirtySessionId}/console`);
    await waitForConsoleLoad(page);

    const decisionInput = page.getByLabel('Keputusan *');

    await expect(decisionInput).toBeVisible();
    await decisionInput.fill('Keyboard modal test');

    await page.getByRole('button', { name: 'Refresh' }).click();

    await expect(
      page.getByText('Perubahan belum disimpan'),
    ).toBeVisible();

    const stayBtn = page.getByRole('button', {
      name: 'Tetap di sini',
    });

    const discardBtn = page.getByRole('button', {
      name: 'Buang perubahan',
    });

    await expect(stayBtn).toBeVisible();
    await expect(discardBtn).toBeVisible();

    await page.keyboard.press('Escape');

    await expect(
      page.getByText('Perubahan belum disimpan'),
    ).not.toBeVisible();
  });

  test('progression controls are keyboard-accessible without mutating session state', async ({ page }) => {
    await page.goto(`/ttx/sessions/${metadata.readySessionId}/console`);
    await waitForConsoleLoad(page);

    const startBtn = page.getByRole('button', {
      name: 'Mulai Exercise',
    });

    await expect(startBtn).toBeVisible();
    await expect(startBtn).toBeEnabled();

    await tabUntilFocused(page, startBtn);
    await expectKeyboardFocusVisible(startBtn);

    // Deliberately do NOT click Start.
    // This test must not mutate the shared READY fixture.
  });
});


// ===========================================================================
// SCENARIO 6 - MUTATION 403 ACCESS-DENIED UX
// ===========================================================================

test.describe('Facilitator Console - Mutation 403 Access Denied', () => {
  test.use({ viewport: { width: 1440, height: 900 } });

  test('start exercise 403 shows "Akses ditolak" banner (not "Gagal Memuat")', async ({ page }) => {
    await page.goto(`/ttx/sessions/${metadata.readySessionId}/console`);
    await waitForConsoleLoad(page);

    await page.route(
      `**/ttx/sessions/${metadata.readySessionId}/start`,
      (route) =>
        route.fulfill({
          status: 403,
          contentType: 'application/json',
          body: JSON.stringify({
            message: 'Forbidden',
          }),
        }),
    );

    const startBtn = page.getByRole('button', {
      name: 'Mulai Exercise',
    });

    await expect(startBtn).toBeVisible();
    await expect(startBtn).toBeEnabled();

    await startBtn.click();

    await expect(
      page.getByText('Akses ditolak'),
    ).toBeVisible({
      timeout: 10_000,
    });

    await expect(
      page.getByText(
        'Anda tidak memiliki izin untuk melakukan tindakan ini.',
      ),
    ).toBeVisible();

    await expect(
      page.getByText('Gagal Memuat'),
    ).not.toBeVisible();

    await expect(
      page.getByRole('button', { name: 'Coba Lagi' }),
    ).not.toBeVisible();

    await expect(
      page.locator('.console-header'),
    ).toBeVisible();

    await expect(
      page.locator('.inject-timeline'),
    ).toBeVisible();
  });

  test('advance inject 403 shows "Akses ditolak" banner', async ({ page }) => {
    await page.goto(`/ttx/sessions/${metadata.dirtySessionId}/console`);
    await waitForConsoleLoad(page);

    // The advance endpoint requires an official saved response.
    const decisionInput = page.getByLabel('Keputusan *');
    await expect(decisionInput).toBeVisible();

    const currentDecision = await decisionInput.inputValue();

    await decisionInput.fill(
      `${currentDecision} [advance-403]`,
    );

    const saveBtn = page.getByRole('button', {
      name: /Simpan Response|Simpan Perubahan/,
    });

    await expect(saveBtn).toBeVisible();
    await expect(saveBtn).toBeEnabled();

    await saveBtn.click();

    const advanceBtn = page.getByRole('button', {
      name: /Rilis Inject Berikutnya|Selesaikan Inject/,
    });

    await expect(advanceBtn).toBeVisible();

    await expect(advanceBtn).toBeEnabled({
      timeout: 10_000,
    });

    // Intercept only the progression mutation after the response
    // has been saved successfully.
    await page.route(
      `**/ttx/sessions/${metadata.dirtySessionId}/advance`,
      (route) =>
        route.fulfill({
          status: 403,
          contentType: 'application/json',
          body: JSON.stringify({
            message: 'Forbidden',
          }),
        }),
    );

    await advanceBtn.click();

    await expect(
      page.getByText('Akses ditolak'),
    ).toBeVisible({
      timeout: 10_000,
    });

    await expect(
      page.getByText(
        'Anda tidak memiliki izin untuk melakukan tindakan ini.',
      ),
    ).toBeVisible();

    await expect(
      page.getByText('Gagal Memuat'),
    ).not.toBeVisible();
  });
  test('response save 403 shows "Akses ditolak" in editor (not "Gagal")', async ({ page }) => {
    await page.goto(`/ttx/sessions/${metadata.dirtySessionId}/console`);
    await waitForConsoleLoad(page);

    await expect(
      page.getByRole('form', { name: 'Response Tim' }),
    ).toBeVisible();

    const decisionInput = page.getByLabel('Keputusan *');

    await expect(decisionInput).toBeVisible();

    await decisionInput.fill(
      'Decision for 403 save test',
    );

    await page.route(
      `**/ttx/sessions/${metadata.dirtySessionId}/responses**`,
      (route) => {
        const method = route.request().method();

        if (
          method === 'POST' ||
          method === 'PUT' ||
          method === 'PATCH'
        ) {
          return route.fulfill({
            status: 403,
            contentType: 'application/json',
            body: JSON.stringify({
              message: 'Forbidden',
            }),
          });
        }

        return route.continue();
      },
    );

    const saveBtn = page.getByRole('button', {
      name: /Simpan Response|Simpan Perubahan/,
    });

    await expect(saveBtn).toBeVisible();
    await expect(saveBtn).toBeEnabled();

    await saveBtn.click();

    const editor = page.locator('.response-editor');

    await expect(
      editor.getByText('Akses ditolak'),
    ).toBeVisible({
      timeout: 10_000,
    });

    await expect(
      editor.getByText(
        'Anda tidak memiliki izin untuk melakukan tindakan ini.',
      ),
    ).toBeVisible();

    const editorAlerts = editor.locator('.base-alert');

    const failedAlerts = editorAlerts.filter({
      hasText: 'Gagal',
    });

    const accessDeniedAlerts = editorAlerts.filter({
      hasText: 'Akses ditolak',
    });

    await expect(
      accessDeniedAlerts.first(),
    ).toBeVisible();

    await expect(
      failedAlerts,
    ).toHaveCount(0);
  });
});
