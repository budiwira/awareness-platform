import { expect, Page, test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

async function noOverflow(page: Page) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
}

async function inertiaResponse(page: Page, path: string, transform: (props: Record<string, unknown>) => void) {
  await page.goto('/platform/dashboard');
  await page.route(`**${path}`, async route => {
    const response = await route.fetch();
    const data = await response.json();
    transform(data.props);
    await route.fulfill({ response, json: data });
  });
  const labels: Record<string, string> = {
    '/platform/reports': 'Reports',
    '/platform/packages': 'Packages',
    '/platform/billing/requests': 'Billing Requests',
  };
  await page.getByRole('link', { name: labels[path], exact: true }).click();
}

test('reports render responsively in light and dark mode without changing metrics', async ({ page }) => {
  const errors: string[] = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('requestfailed', request => errors.push(request.url()));

  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/platform/reports');
  await expect(page.getByRole('heading', { name: 'Performa per organisasi' })).toBeVisible();
  await expect(page.getByText('Rata-rata platform', { exact: true })).toBeVisible();

  for (const theme of ['light', 'dark']) {
    if (await page.locator('html').getAttribute('data-theme') !== theme) {
      await page.getByRole('button', { name: 'Ganti tema' }).click();
    }
    await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
    for (const width of [1440, 768, 390]) {
      await page.setViewportSize({ width, height: 900 });
      await noOverflow(page);
      await expect(page.locator('.base-table-scroll')).toHaveCSS('overflow-x', 'auto');
    }
  }

  const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
  expect(results.violations.filter(violation => violation.impact === 'critical')).toEqual([]);
  expect(errors).toEqual([]);
});

test('reports and packages expose their empty states', async ({ page }) => {
  await inertiaResponse(page, '/platform/reports', props => { props.rows = []; });
  await expect(page.getByText('Belum ada laporan tenant', { exact: true })).toBeVisible();

  await page.unroute('**/platform/reports');
  await inertiaResponse(page, '/platform/packages', props => { props.packages = []; });
  await expect(page.getByText('Belum ada paket', { exact: true })).toBeVisible();
});

test('package form exposes validation and real processing state without mutating data', async ({ page }) => {
  await inertiaResponse(page, '/platform/packages', props => {
    props.errors = {
      name: 'Nama paket wajib diisi.',
      price_monthly: 'Harga paket tidak valid.',
      max_users: 'Kapasitas pengguna tidak valid.',
      features: 'Pilih sedikitnya satu fitur.',
      module_ids: 'Modul yang dipilih tidak valid.',
    };
  });

  await page.getByRole('button', { name: 'Buat paket baru' }).click();
  await expect(page.getByText('Nama paket wajib diisi.', { exact: true })).toBeVisible();
  await expect(page.getByLabel('Harga (ribu rupiah per bulan)')).toHaveAttribute('aria-invalid', 'true');
  await expect(page.getByLabel('Modul terpilih')).toHaveAttribute('aria-invalid', 'true');

  await page.getByLabel('Nama paket').fill('Paket QA tanpa simpan');
  let release!: () => void;
  const pending = new Promise<void>(resolve => { release = resolve; });
  await page.route('**/platform/packages', async route => {
    if (route.request().method() !== 'POST') return route.continue();
    await pending;
    await route.abort('failed');
  });
  await page.getByRole('button', { name: 'Simpan paket' }).click();
  await expect(page.getByRole('button', { name: 'Menyimpan...' })).toBeDisabled();
  release();
  await expect(page.getByRole('button', { name: 'Simpan paket' })).toBeEnabled();
});

test('billing approve and reject dialogs support keyboard dismissal and long notes', async ({ page }) => {
  await inertiaResponse(page, '/platform/billing/requests', props => {
    props.requests = [{
      id: 999999,
      tenant_id: 42,
      tenant_name: 'Tenant QA',
      plan_name: 'Paket QA',
      plan_slug: 'paket-qa',
      note: 'Catatan pengujian yang sengaja sangat panjang untuk memastikan isi tetap tersedia tanpa merusak lebar tabel pada viewport sempit.',
      requested_by: 'Admin QA',
      requested_at: '17 Sep 2026',
      status: 'pending',
      resolved_by: null,
      resolved_at: null,
    }];
    props.errors = {};
  });
  const pendingRow = page.locator('tbody tr').filter({ has: page.getByText('Menunggu', { exact: true }) }).first();
  await expect(pendingRow).toBeVisible();

  const note = pendingRow.locator('td').nth(2);
  await expect(note).toHaveCSS('text-overflow', 'ellipsis');
  await expect(note).toHaveAttribute('title');

  for (const action of ['Setujui', 'Tolak']) {
    const trigger = pendingRow.getByRole('button', { name: action, exact: true });
    await trigger.click();
    const dialog = page.getByRole('dialog', { name: 'Konfirmasi keputusan billing' });
    await expect(dialog).toBeVisible();
    await expect(dialog).toContainText(`${action} permintaan paket?`);
    await page.keyboard.press('Escape');
    await expect(dialog).not.toBeVisible();
    await expect(trigger).toBeFocused();
  }

  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    await noOverflow(page);
  }
});

test('billing renders empty and server-error states', async ({ page }) => {
  await inertiaResponse(page, '/platform/billing/requests', props => {
    props.requests = [];
    props.errors = { request_id: 'Permintaan tidak dapat diproses.' };
  });
  await expect(page.getByText('Antrean kosong', { exact: true })).toBeVisible();
  await expect(page.getByRole('alert')).toContainText('Permintaan tidak dapat diproses.');
});
