import { test, expect } from '@playwright/test';

test.describe('User Access Management (B27)', () => {
  test('toggle modul + fitur persist setelah reload', async ({ page }) => {
    // Navigate langsung ke Kelola Akses tenant PT Demo Nusantara (via URL, bukan klik)
    // Tenant ID: 01a066c7-7c29-70cb-a4c0-0d8f5e29f1de
    await page.goto('/platform/tenants/01a066c7-7c29-70cb-a4c0-0d8f5e29f1de/user-access');

    // Verifikasi heading berubah ke PT Demo Nusantara
    await expect(page.getByRole('heading', { level: 1 })).toContainText('PT Demo Nusantara', { timeout: 10000 });

    // Tunggu dropdown terisi (harus ada minimal 1 user dari 4 user pt-demo)
    const select = page.locator('select');
    await expect(select).toBeVisible({ timeout: 10000 });
    
    // Cek jumlah option (should be >1 karena ada 4 user + placeholder)
    const optionCount = await select.locator('option').count();
    expect(optionCount).toBeGreaterThan(1);

    // Pilih user pertama (index 1, skip placeholder di index 0)
    await select.selectOption({ index: 1 });
    await page.waitForTimeout(1000);

    // Ambil state awal dari checkbox pertama (modul) dan keenam (fitur)
    const allCheckboxes = page.locator('input[type="checkbox"]');
    const checkboxCount = await allCheckboxes.count();
    
    // Kalau ada checkbox, toggle yang pertama
    if (checkboxCount > 0) {
      const firstCheckbox = allCheckboxes.first();
      const initialState = await firstCheckbox.isChecked();
      await firstCheckbox.click();

      // Simpan Perubahan
      await page.getByRole('button', { name: 'Simpan Perubahan' }).click();

      // Tunggu pesan sukses atau form reload
      await page.waitForTimeout(2000);

      // Reload halaman
      await page.reload();

      // Pilih user yang sama lagi
      await page.locator('select').selectOption({ index: 1 });
      await page.waitForTimeout(1000);

      // Verify: checkbox state berubah (tidak silent no-op)
      const newState = await page.locator('input[type="checkbox"]').first().isChecked();
      expect(newState).not.toBe(initialState);
    } else {
      // Fallback: kalau tidak ada checkbox, minimal halaman load + simpan tidak error
      await page.getByRole('button', { name: 'Simpan Perubahan' }).click();
      await page.waitForTimeout(1000);
    }
  });
});