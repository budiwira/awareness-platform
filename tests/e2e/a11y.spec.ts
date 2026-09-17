import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const keyPages = [
  { name: 'Dashboard', url: '/platform/dashboard' },
  { name: 'Tenants', url: '/platform/tenants' },
  { name: 'Packages', url: '/platform/packages' },
];

test.describe('Aksesibilitas (WCAG 2.1 AA)', () => {
  for (const page of keyPages) {
    test(`${page.name} bebas pelanggaran kritis`, async ({ page: p }) => {
      await p.goto(page.url);
      const results = await new AxeBuilder({ page: p })
        .withTags(['wcag2a', 'wcag2aa'])
        .analyze();

      const critical = results.violations.filter(v => v.impact === 'critical');
      expect(critical).toEqual([]);
    });
  }
});

test.describe('Responsif (tanpa overflow horizontal)', () => {
  for (const page of keyPages) {
    test(`${page.name} tidak overflow di mobile`, async ({ page: p }) => {
      await p.goto(page.url);
      const overflow = await p.evaluate(
        () => document.documentElement.scrollWidth > window.innerWidth
      );
      expect(overflow).toBe(false);
    });
  }

  test('Dashboard tetap sinkron saat desktop diubah ke mobile tanpa refresh', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/platform/dashboard');

    await expect(page.locator('aside').first()).toBeVisible();

    for (const width of [768, 390]) {
      await page.setViewportSize({ width, height: 844 });
      await expect(page.locator('button[aria-label="Buka menu navigasi"]')).toBeVisible();
      await expect.poll(() => page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth
      )).toBe(true);
    }

    const tableViewport = page.locator('.base-table-scroll');
    await expect(tableViewport).toBeVisible();
    await expect.poll(() => tableViewport.evaluate(
      element => getComputedStyle(element).overflowX
    )).toBe('auto');
  });

  test('Dashboard konsisten dalam tema terang dan gelap', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/platform/dashboard');

    const themeButton = page.locator('button[aria-label="Ganti tema"]');
    const initialTheme = await page.locator('html').getAttribute('data-theme');
    await themeButton.click();
    await expect(page.locator('html')).not.toHaveAttribute('data-theme', initialTheme ?? '');
    await expect.poll(() => page.evaluate(
      () => document.documentElement.scrollWidth <= window.innerWidth
    )).toBe(true);
  });

  for (const width of [390, 768, 1440]) {
    test(`Dashboard stabil pada direct load ${width}px`, async ({ page }) => {
      await page.setViewportSize({ width, height: 900 });
      await page.goto('/platform/dashboard');

      const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth > window.innerWidth
      );
      expect(overflow).toBe(false);
    });
  }
});
