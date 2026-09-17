import { expect, test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.beforeEach(async ({ page }) => {
  // Fail closed: browser QA must never delete a real local user.
  await page.route('**/platform/users/destroy', route => route.abort('blockedbyclient'));
});

async function noOverflow(page) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
}

// Alter only the browser response for edge states; never seed or mutate the local database.
async function usersResponse(page, transform) {
  await page.goto('/platform/dashboard');
  await page.route('**/platform/users', async route => {
    const response = await route.fetch();
    const data = await response.json();
    transform(data.props);
    await route.fulfill({ response, json: data });
  });
  // Use a real Inertia navigation so fixtures do not rewrite the HTML bootstrap.
  const navigation = page.getByRole('button', { name: 'Buka menu navigasi' });
  if (await navigation.isVisible()) await navigation.click();
  await page.getByRole('link', { name: 'Users', exact: true }).click();
}
test('platform users supports search, tenant filtering, themes, and responsive resize', async ({ page }) => {
  const errors: string[] = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('response', response => { if (response.status() >= 400) errors.push(`${response.status()} ${response.url()}`); });
  page.on('requestfailed', request => errors.push(request.url()));

  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/platform/users');
  await expect(page.getByRole('heading', { level: 2, name: 'Pengguna platform', exact: true })).toBeVisible();
  await expect(page.getByRole('table', { name: 'Daftar pengguna platform' })).toBeVisible();

  for (const theme of ['light', 'dark']) {
    if (await page.locator('html').getAttribute('data-theme') !== theme) await page.getByRole('button', { name: 'Ganti tema' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
    for (const width of [1440, 768, 390]) {
      await page.setViewportSize({ width, height: 900 });
      await noOverflow(page);
      await expect(page.getByRole('button', { name: /^Hapus pengguna / }).first()).toBeVisible();
    }
  }

  const tenant = page.getByLabel('Tenant');
  const tenantValue = await tenant.locator('option:not([value=""])').first().getAttribute('value');
  await tenant.selectOption(tenantValue!);
  await expect(page.getByText(/dari .* pengguna ditampilkan/)).toBeVisible();

  await tenant.selectOption('');
  await page.getByLabel('Cari pengguna').fill('Super Admin');
  await page.getByRole('button', { name: 'Cari', exact: true }).click();
  await expect(page.getByRole('table', { name: 'Daftar pengguna platform' }).getByText('Super Admin', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Reset', exact: true }).click();
  await expect(page.getByRole('table', { name: 'Daftar pengguna platform' })).toBeVisible();
  expect(errors).toEqual([]);
});

test('platform users renders empty and no-result states', async ({ page }) => {
  await usersResponse(page, props => { props.users = []; });
  await expect(page.getByText('Belum ada pengguna', { exact: true })).toBeVisible();
  await page.unroute('**/platform/users');

  await page.goto('/platform/users');
  const tenant = page.getByLabel('Tenant');
  await tenant.selectOption(await tenant.locator('option:not([value=""])').first().getAttribute('value') ?? '');
  await page.getByLabel('Cari pengguna').fill('Super Admin');
  await page.getByRole('button', { name: 'Cari', exact: true }).click();
  await expect(page.getByText('Pengguna tidak ditemukan', { exact: true })).toBeVisible();
});

test('delete modal traps focus, closes with Escape, and exposes real processing feedback', async ({ page }) => {
  await page.goto('/platform/users');
  const trigger = page.getByRole('button', { name: /^Hapus pengguna / }).first();
  await trigger.click();
  const dialog = page.getByRole('alertdialog', { name: 'Hapus pengguna?' });
  await expect(dialog).toBeVisible();
  await expect(dialog.getByRole('button', { name: 'Batal' })).toBeFocused();
  await page.keyboard.press('Shift+Tab');
  expect(await dialog.evaluate(element => element.contains(document.activeElement))).toBe(true);
  await page.keyboard.press('Escape');
  await expect(dialog).not.toBeVisible();
  await expect(trigger).toBeFocused();

  await trigger.click();
  let release;
  const pending = new Promise<void>(resolve => { release = resolve; });
  await page.route('**/platform/users/destroy', async route => {
    await pending;
    await route.abort('failed');
  });
  await dialog.getByRole('button', { name: 'Hapus pengguna' }).click();
  await expect(dialog.getByRole('button', { name: 'Menghapus…' })).toBeDisabled();
  release!();
  await expect(page.getByRole('alert')).toContainText('Koneksi terputus');
  await expect(dialog).toBeVisible();
});

test('server validation is visible and platform users has no WCAG AA violations', async ({ page }) => {
  await usersResponse(page, props => { props.errors = { user_id: 'Pengguna tidak dapat dihapus.' }; });
  await expect(page.getByRole('alert')).toContainText('Pengguna tidak dapat dihapus.');

  for (const theme of ['light', 'dark']) {
    if (await page.locator('html').getAttribute('data-theme') !== theme) await page.getByRole('button', { name: 'Ganti tema' }).click();
    const results = await new AxeBuilder({ page }).include('.platform-users-page').withTags(['wcag2a', 'wcag2aa']).analyze();
    expect(results.violations).toEqual([]);
  }
});

test('tenant selection remains explicit after server search removes its users', async ({ page }) => {
  await page.goto('/platform/users');
  const tenant = page.getByLabel('Tenant', { exact: true });
  const option = tenant.locator('option:not([value=""])').first();
  const id = await option.getAttribute('value');
  const label = await option.textContent();
  await tenant.selectOption(id!);
  const rows = page.getByRole('table').locator('tbody tr');
  for (const row of await rows.all()) await expect(row.locator('[data-label="Tenant"]')).toHaveText(label!);
  await page.getByLabel('Cari pengguna').fill('Super Admin');
  await page.getByRole('button', { name: 'Cari', exact: true }).click();
  await expect(page.getByText('Pengguna tidak ditemukan', { exact: true })).toBeVisible();
  await expect(tenant).toHaveValue(id!);
  await expect(tenant.locator('option:checked')).toHaveText(label!);
  await tenant.selectOption('');
  await expect(page.getByRole('table').locator('tbody tr')).toHaveCount(1);
  await page.getByRole('button', { name: 'Reset', exact: true }).click();
  await expect.poll(() => rows.count()).toBeGreaterThan(1);
});

test('search skeleton represents a pending request and recovers after failure', async ({ page }) => {
  await page.goto('/platform/users');
  let release!: () => void;
  const pending = new Promise<void>(resolve => { release = resolve; });
  await page.route('**/platform/users?*', async route => { await pending; await route.abort('failed'); });
  await page.getByLabel('Cari pengguna').fill('example');
  await page.getByRole('button', { name: 'Cari', exact: true }).click();
  await expect(page.locator('.skeleton').first()).toBeVisible();
  await expect(page.getByRole('button', { name: /Memuat/ })).toBeDisabled();
  await expect(page.getByLabel('Tenant', { exact: true })).toBeDisabled();
  release();
  await expect(page.getByRole('alert')).toContainText('Koneksi terputus');
  await expect(page.locator('.skeleton')).toHaveCount(0);
  await page.unroute('**/platform/users?*');
  await page.getByRole('button', { name: 'Reset', exact: true }).click();
  await expect(page.getByRole('table')).toBeVisible();
});
