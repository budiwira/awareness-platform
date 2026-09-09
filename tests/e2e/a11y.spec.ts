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
});
