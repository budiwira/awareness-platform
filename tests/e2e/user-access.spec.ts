import { test, expect } from '@playwright/test';

// Exercise the existing local support workflow, then restore the original access.
test('platform user access persists and restores a module toggle', async ({ page }) => {
  await page.goto('/platform/tenants');
  await page.getByRole('link', { name: /^Kelola akses / }).first().click();
  await expect(page.getByRole('heading', { name: 'Akses pengguna', exact: true })).toBeVisible();
  const selector = page.getByLabel('Pilih pengguna');
  await expect(selector).toBeVisible();
  const userId = await selector.inputValue();
  const toggle = page.getByRole('switch', { name: /^Akses modul / }).first();
  await expect(toggle).toBeVisible();
  const original = await toggle.getAttribute('aria-checked');
  try {
    await toggle.click();
    await page.getByRole('button', { name: 'Simpan perubahan', exact: true }).click();
    await expect(page.getByText('Perubahan akses berhasil disimpan.', { exact: true })).toBeVisible();
    await page.reload();
    await selector.selectOption(userId);
    await expect(toggle).toHaveAttribute('aria-checked', original === 'true' ? 'false' : 'true');
  } finally {
    await page.reload();
    await selector.selectOption(userId);
    if (await toggle.getAttribute('aria-checked') !== original) {
      await toggle.click();
      await page.getByRole('button', { name: 'Simpan perubahan', exact: true }).click();
      await expect(page.getByText('Perubahan akses berhasil disimpan.', { exact: true })).toBeVisible();
    }
    await page.reload();
    await selector.selectOption(userId);
    await expect(toggle).toHaveAttribute('aria-checked', original!);
  }
});
