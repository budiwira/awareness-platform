import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

async function noOverflow(page) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  for (const row of await page.getByTestId('tenant-row').all()) {
    const bounds = await row.boundingBox();
    expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(await page.evaluate(() => innerWidth));
    await expect(row.getByRole('button', { name: /Atur paket/ })).toBeVisible();
  }
}

// Alter only the browser response for edge states; never seed or mutate the local database.
async function tenantResponse(page, transform) {
  await page.route('**/platform/tenants', async route => {
    const response = await route.fetch();
    const body = await response.text();
    if (response.headers()['content-type']?.includes('application/json')) {
      const data = JSON.parse(body); transform(data.props);
      await route.fulfill({ response, json: data });
    } else {
      const match = body.match(/data-page="([^"]+)"/);
      const decode = value => value.replace(/&quot;/g, '"').replace(/&#039;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
      const data = JSON.parse(decode(match![1])); transform(data.props);
      const encoded = JSON.stringify(data).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
      await route.fulfill({ response, body: body.replace(match![0], `data-page="${encoded}"`) });
    }
  });
}

test('real tenant list supports themes, responsive resize, filters and reachable actions', async ({ page }) => {
  const errors: string[] = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('response', response => { if (response.status() >= 400) errors.push(`${response.status()} ${response.url()}`); });
  page.on('requestfailed', request => errors.push(request.url()));
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/platform/tenants');
  await expect(page.getByRole('heading', { name: 'Organisasi pelanggan' })).toBeVisible();
  expect(await page.getByTestId('tenant-row').count()).toBeGreaterThan(0);
  for (const theme of ['light', 'dark']) {
    if (await page.locator('html').getAttribute('data-theme') !== theme) await page.getByRole('button', { name: 'Ganti tema' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
    for (const width of [1440, 768, 390]) {
      await page.setViewportSize({ width, height: 900 });
      await noOverflow(page);
      const action = page.getByRole('button', { name: /^Atur paket / }).first();
      await action.click();
      const dialog = page.getByRole('dialog', { name: 'Atur paket organisasi' });
      await expect(dialog).toBeVisible();
      await expect(page.getByLabel('Paket pengganti')).toBeFocused();
      await expect(dialog.getByRole('button', { name: 'Terapkan paket' })).toBeDisabled();
      await page.keyboard.press('Shift+Tab');
      expect(await dialog.evaluate(el => el.contains(document.activeElement))).toBe(true);
      await page.keyboard.press('Escape');
      await expect(dialog).not.toBeVisible();
      await expect(action).toBeFocused();
    }
  }
  await page.getByLabel('Cari organisasi').fill('no-match-pm4b');
  await expect(page.getByText('Organisasi tidak ditemukan')).toBeVisible();
  await page.getByRole('button', { name: 'Reset filter' }).click();
  await expect(page.getByTestId('tenant-row').first()).toBeVisible();
  await page.getByLabel('Status organisasi').selectOption('active');
  for (const row of await page.getByTestId('tenant-row').all()) await expect(row.getByText('Aktif', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Reset filter' }).click();
  await page.getByRole('link', { name: /^Kelola akses / }).first().click();
  await expect(page.getByRole('heading', { name: 'Akses pengguna', exact: true })).toBeVisible();
  await noOverflow(page);
  expect(errors).toEqual([]);
});

test('empty tenants and empty packages have actionable states', async ({ page }) => {
  await tenantResponse(page, props => { props.tenants = []; props.packages = []; });
  await page.goto('/platform/tenants');
  await expect(page.getByText('Belum ada organisasi', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Tambah organisasi', exact: true }).click();
  await expect(page.getByLabel('Nama organisasi')).toBeFocused();
  await page.getByRole('button', { name: 'Batal', exact: true }).click();
  await expect(page.getByRole('button', { name: 'Tambah organisasi', exact: true })).toBeFocused();
});

test('long names, inactive status, package filter and absent package options', async ({ page }) => {
  await tenantResponse(page, props => {
    props.tenants = [{ ...props.tenants[0], name: 'Organisasi'.repeat(12), status: 'inactive', current_package: null, subscriptions: [] }];
    props.packages = [];
  });
  await page.goto('/platform/tenants');
  for (const width of [390, 768, 1440]) {
    await page.setViewportSize({ width, height: 900 }); await noOverflow(page);
  }
  await page.getByLabel('Status organisasi').selectOption('inactive');
  await page.getByLabel('Paket tercatat', { exact: true }).selectOption('__none');
  await expect(page.getByTestId('tenant-row')).toHaveCount(1);
  await expect(page.getByText('Tidak aktif', { exact: true }).last()).toBeVisible();
  await page.getByRole('button', { name: /^Atur paket / }).click();
  await expect(page.getByText('Belum ada paket aktif yang dapat dipilih.')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Terapkan paket' })).toBeDisabled();
});

test('reload has a loading state and recovers from a failed connection', async ({ page }) => {
  await page.goto('/platform/tenants');
  let release;
  const pending = new Promise<void>(resolve => { release = resolve; });
  await page.route('**/platform/tenants', async route => { await pending; await route.abort('failed'); });
  await page.getByRole('button', { name: 'Muat ulang', exact: true }).click();
  await expect(page.getByText('Memuat daftar organisasi')).toBeAttached();
  release();
  await expect(page.getByRole('alert')).toContainText('Koneksi terputus');
  await page.unroute('**/platform/tenants');
  await page.getByRole('button', { name: 'Muat ulang', exact: true }).click();
  await expect(page.getByTestId('tenant-row').first()).toBeVisible();
});

test('tenant forms show server validation without closing the dialog', async ({ page }) => {
  await page.goto('/platform/tenants');
  await page.getByRole('button', { name: 'Tambah organisasi', exact: true }).click();
  await page.getByLabel('Nama organisasi').fill('x'.repeat(121));
  await page.getByRole('button', { name: 'Simpan organisasi' }).click();
  await expect(page.getByLabel('Nama organisasi')).toHaveAttribute('aria-invalid', 'true');
  await expect(page.getByRole('dialog', { name: 'Tambah organisasi' })).toBeVisible();
  await page.keyboard.press('Escape');
});

test('tenant content has no WCAG AA violations in both themes', async ({ page }) => {
  await page.goto('/platform/tenants');
  for (const theme of ['light', 'dark']) {
    if (await page.locator('html').getAttribute('data-theme') !== theme) await page.getByRole('button', { name: 'Ganti tema' }).click();
    const results = await new AxeBuilder({ page }).include('.tenant-page').withTags(['wcag2a', 'wcag2aa']).analyze();
    expect(results.violations).toEqual([]);
  }
});
