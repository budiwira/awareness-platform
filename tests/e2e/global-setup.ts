import { chromium, FullConfig } from '@playwright/test';

async function globalSetup(config: FullConfig) {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  await page.goto('http://127.0.0.1:8000/login');
  await page.waitForSelector('input#email', { timeout: 15000 });

  await page.fill('input#email', 'superadmin@platform.local');
  await page.locator('input[type="password"]').first().fill('password');
  await page.click('button[type="submit"]');

  await page.waitForURL('**/dashboard', { timeout: 15000 });

  await page.context().storageState({ path: 'tests/e2e/.auth/superadmin.json' });
  await browser.close();
}

export default globalSetup;