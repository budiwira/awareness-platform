import { test, expect } from '@playwright/test';

test.describe('User Access Management (B27)', () => {
  test('toggle modul + fitur persist setelah reload', async ({ page }) => {
    // Navigate ke Kelola Akses tenant Acme
    await page.goto('/platform/tenants');
    await page.click('text=Acme Corporation');
    await page.click('text=Kelola Akses');

    // Klik user pertama di tabel
    const firstUser = page.locator('tr[data-user-id]').first();
    await firstUser.click();

    // Toggle satu modul
    const moduleToggle = page.locator('.module-toggles input[type="checkbox"]').first();
    const initialModuleState = await moduleToggle.isChecked();
    await moduleToggle.click();

    // Toggle satu fitur
    const featureToggle = page.locator('.feature-toggles input[type="checkbox"]').first();
    const initialFeatureState = await featureToggle.isChecked();
    await featureToggle.click();

    // Simpan Perubahan (unified button)
    await page.click('text=Simpan Perubahan');

    // Tunggu pesan sukses
    await page.waitForSelector('text=Perubahan akses berhasil disimpan');

    // Reload halaman
    await page.reload();

    // Klik user yang sama lagi
    await firstUser.click();

    // Verify: kedua toggle state berubah (tidak silent no-op)
    const newModuleState = await moduleToggle.isChecked();
    const newFeatureState = await featureToggle.isChecked();
    expect(newModuleState).not.toBe(initialModuleState);
    expect(newFeatureState).not.toBe(initialFeatureState);
  });
});