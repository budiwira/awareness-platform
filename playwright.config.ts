import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  workers: 1,
  reporter: 'list',
  globalSetup: './tests/e2e/global-setup',
  use: {
    baseURL: 'http://127.0.0.1:8000',
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'authenticated',
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'tests/e2e/.auth/superadmin.json',
      },
    },
    {
      name: 'mobile',
      use: {
        ...devices['Pixel 5'],  // Chromium mobile, bukan WebKit
        storageState: 'tests/e2e/.auth/superadmin.json',
      },
    },
  ],
  webServer: {
    command: 'php artisan serve',
    port: 8000,
    reuseExistingServer: !process.env.CI,
  },
});
