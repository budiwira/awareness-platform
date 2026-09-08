import { test, expect, Page } from '@playwright/test';

// Kartu package = ancestor terdekat dari heading yang punya tombol Edit
const cardOf = (page: Page, name: string) =>
  page.getByRole('heading', { name, exact: true })
    .locator('xpath=ancestor::div[.//button[normalize-space()="Edit"]][1]');

test('edit package fitur persist setelah reload (guard B04)', async ({ page }) => {
  await page.goto('/platform/packages');

  // Ambil package kartu pertama secara dinamis (tidak hardcode nama)
  const name = (await page.getByRole('heading', { level: 3 }).first().innerText()).trim();
  await cardOf(page, name).getByRole('button', { name: 'Edit' }).click();

  // Toggle fitur ttx di form edit
  const feature = page.getByRole('checkbox', { name: 'ttx', exact: true });
  const before = await feature.isChecked();
  await feature.click();
  await page.getByRole('button', { name: 'Simpan', exact: true }).click();

  // Form tertutup = save selesai (kalau tetap terbuka, berarti validasi/error -> test gagal, benar)
  await expect(page.getByRole('textbox')).toHaveCount(0, { timeout: 10000 });

  // Momen kebenaran: reload, lalu cek chip fitur di kartu berubah
  await page.reload();
  const chips = await cardOf(page, name).getByText('ttx', { exact: true }).count();
  if (before) {
    expect(chips).toBe(0);
  } else {
    expect(chips).toBe(1);
  }
});