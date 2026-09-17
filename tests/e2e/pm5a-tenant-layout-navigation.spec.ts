import { expect, Page, test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

async function noOverflow(page: Page) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
}

async function layoutForRole(page: Page, role: string) {
  await page.unrouteAll({ behavior: 'wait' });
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/platform/users');
  await page.route('**/platform/dashboard', async route => {
    const response = await route.fetch();
    const data = await response.json();
    data.props.auth.user.role = role;
    data.props.auth.user.role_label = role;
    data.props.entitlements = { features: ['phishing', 'ttx', 'case_studies', 'ctf'] };
    await route.fulfill({ response, json: data });
  });
  await page.getByRole('link', { name: 'Dashboard', exact: true }).click();
  await expect(page).toHaveURL(/\/platform\/dashboard$/);
  await page.unrouteAll({ behavior: 'wait' });
}

test('mobile drawer traps focus, closes with Escape, and restores focus', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/platform/dashboard');

  const trigger = page.getByRole('button', { name: 'Buka menu navigasi' });
  await expect(trigger).toHaveAttribute('aria-controls', 'mobile-navigation');
  await expect(trigger).toHaveAttribute('aria-expanded', 'false');
  await trigger.click();

  const dialog = page.getByRole('dialog', { name: 'Awareness' });
  await expect(trigger).toHaveAttribute('aria-expanded', 'true');
  await expect(dialog).toBeVisible();
  await expect(page.getByRole('button', { name: 'Tutup menu navigasi' })).toBeFocused();
  await expect.poll(() => page.evaluate(() => document.body.style.overflow)).toBe('hidden');

  await page.keyboard.press('Shift+Tab');
  expect(await dialog.evaluate(element => element.contains(document.activeElement))).toBe(true);
  await page.keyboard.press('Tab');
  expect(await dialog.evaluate(element => element.contains(document.activeElement))).toBe(true);

  await page.keyboard.press('Escape');
  await expect(dialog).not.toBeVisible();
  await expect(trigger).toBeFocused();
  await expect(trigger).toHaveAttribute('aria-expanded', 'false');
  await expect.poll(() => page.evaluate(() => document.body.style.overflow)).toBe('');
});

test('drawer link closes without stale focus and breakpoint resize synchronizes state', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/platform/dashboard');
  const trigger = page.getByRole('button', { name: 'Buka menu navigasi' });
  await trigger.click();
  await page.getByRole('dialog', { name: 'Awareness' }).getByRole('link', { name: 'Reports', exact: true }).click();
  await expect(page).toHaveURL(/\/platform\/reports$/);
  await expect(page.getByRole('dialog', { name: 'Awareness' })).not.toBeVisible();
  await expect(trigger).not.toBeFocused();

  await page.goto('/platform/dashboard');
  await trigger.click();
  await page.setViewportSize({ width: 1440, height: 900 });
  await expect(page.getByRole('dialog', { name: 'Awareness' })).not.toBeVisible();
  await expect.poll(() => page.evaluate(() => document.body.style.overflow)).toBe('');
  await expect(page.locator('aside').first()).toBeVisible();

  await page.setViewportSize({ width: 390, height: 844 });
  await expect(page.getByRole('button', { name: 'Buka menu navigasi' })).toBeVisible();
  await noOverflow(page);
});

test('active navigation exposes aria-current in desktop and mobile layouts', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/platform/reports');
  await expect(page.locator('aside').first().getByRole('link', { name: 'Reports', exact: true })).toHaveAttribute('aria-current', 'page');

  await page.setViewportSize({ width: 390, height: 844 });
  await page.getByRole('button', { name: 'Buka menu navigasi' }).click();
  await expect(page.getByRole('dialog', { name: 'Awareness' }).getByRole('link', { name: 'Reports', exact: true })).toHaveAttribute('aria-current', 'page');
});

test('AppLayout remains responsive and accessible for every role', async ({ page }) => {
  test.setTimeout(120_000);
  const roles = [
    { role: 'super_admin', labels: ['Dashboard', 'Users', 'Reports'] },
    { role: 'tenant_admin', labels: ['Pengguna', 'Penugasan Pelatihan', 'Laporan', 'Langganan'] },
    { role: 'user', labels: ['Dashboard', 'Training', 'Skor Saya'] },
  ];

  for (const role of roles) {
    await layoutForRole(page, role.role);
    await page.setViewportSize({ width: 1440, height: 900 });

    for (const theme of ['light', 'dark']) {
      if (await page.locator('html').getAttribute('data-theme') !== theme) await page.getByRole('button', { name: 'Ganti tema' }).click();
      await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
      for (const width of [1440, 768, 390]) {
        await page.setViewportSize({ width, height: 900 });
        await noOverflow(page);
        if (width < 1024) {
          const trigger = page.getByRole('button', { name: 'Buka menu navigasi' });
          await trigger.click();
          const dialog = page.getByRole('dialog', { name: 'Awareness' });
          for (const label of role.labels) await expect(dialog.getByRole('link', { name: label, exact: true })).toBeVisible();
          await page.keyboard.press('Escape');
        }
      }
    }

    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
    expect(results.violations.filter(violation => violation.impact === 'critical')).toEqual([]);
  }
});
