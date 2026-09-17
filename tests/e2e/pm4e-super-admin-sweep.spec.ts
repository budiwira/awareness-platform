import { expect, Page, test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

async function noOverflow(page: Page) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
}

async function inertiaResponse(page: Page, path: string, label: string, transform: (props: Record<string, unknown>) => void) {
  await page.goto('/platform/dashboard');
  await page.route(`**${path}`, async route => {
    const response = await route.fetch();
    const data = await response.json();
    transform(data.props);
    await route.fulfill({ response, json: data });
  });
  await page.getByRole('link', { name: label, exact: true }).click();
}

test('shared super-admin navigation has accessible controls in light and dark mode', async ({ page }) => {
  await page.goto('/platform/modules');
  await expect(page.getByRole('link', { name: 'Modul Training', exact: true })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Ganti tema' })).toHaveCSS('min-height', '44px');
  await expect(page.getByRole('link', { name: 'Buka notifikasi' })).toHaveCSS('min-width', '44px');

  for (const theme of ['light', 'dark']) {
    if (await page.locator('html').getAttribute('data-theme') !== theme) await page.getByRole('button', { name: 'Ganti tema' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
  }

  const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
  expect(results.violations.filter(violation => violation.impact === 'critical')).toEqual([]);
});

test('training modules filters and create action stay responsive', async ({ page }) => {
  await page.goto('/platform/modules');
  const create = page.getByRole('link', { name: 'Buat modul', exact: true });
  await expect(create).toHaveAttribute('href', /platform\/modules\/create$/);
  await page.getByRole('button', { name: /^Draft \(/ }).click();

  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    await noOverflow(page);
    await expect(create).toBeVisible();
  }
});

test('case-study filters and action form remain responsive', async ({ page }) => {
  await page.goto('/platform/cases');
  await page.getByRole('button', { name: /Buat Case Study/ }).click();
  await expect(page.getByLabel('Judul')).toBeVisible();
  await page.getByRole('button', { name: /^Published \(/ }).click();

  for (const width of [390, 768, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    await noOverflow(page);
  }
});

test('CTF uses Indonesian statuses and exposes real action processing state', async ({ page }) => {
  await inertiaResponse(page, '/platform/ctf', 'CTF', props => {
    props.challenges = [{
      id: 999999,
      title: 'Challenge QA',
      category: 'general',
      difficulty: 'beginner',
      status: 'draft',
      points: 100,
      solves_count: 0,
    }, {
      id: 999998,
      title: 'Challenge Terbit',
      category: 'general',
      difficulty: 'intermediate',
      status: 'published',
      points: 200,
      solves_count: 1,
    }, {
      id: 999997,
      title: 'Challenge Arsip',
      category: 'general',
      difficulty: 'advanced',
      status: 'archived',
      points: 300,
      solves_count: 2,
    }];
  });

  await expect(page.getByText('Terbit', { exact: true }).first()).toBeVisible();
  await expect(page.getByText('Diarsipkan', { exact: true }).first()).toBeVisible();

  let release!: () => void;
  const pending = new Promise<void>(resolve => { release = resolve; });
  await page.route('**/platform/ctf/999999/publish', async route => {
    await pending;
    await route.abort('failed');
  });
  await page.getByRole('button', { name: 'Terbitkan', exact: true }).first().click();
  await expect(page.getByRole('button', { name: 'Memproses...' })).toBeDisabled();
  release();
  await expect(page.getByRole('button', { name: 'Terbitkan', exact: true }).first()).toBeEnabled();

  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    await noOverflow(page);
  }
});
