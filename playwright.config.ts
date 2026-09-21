import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  workers: 1,
  reporter: 'list',
  globalSetup: './tests/e2e/global-setup',
  use: {
    baseURL: 'http://127.0.0.1:8001',
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'authenticated',
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'tests/e2e/.auth/superadmin.json',
      },
      testMatch: ['**/*.spec.ts'],
      testIgnore: ['**/facilitator-*.spec.ts'],
    },
    {
      name: 'mobile',
      use: {
        ...devices['Pixel 5'],  // Chromium mobile, bukan WebKit
        storageState: 'tests/e2e/.auth/superadmin.json',
      },
      testMatch: ['**/*.spec.ts'],
      testIgnore: ['**/facilitator-*.spec.ts'],
    },
    {
      name: 'facilitator',
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'tests/e2e/.auth/facilitator.json',
      },
      testMatch: ['**/facilitator-*.spec.ts'],
    },
  ],
  webServer: {
    command: 'php artisan serve --host=127.0.0.1 --port=8001',
    port: 8001,
    reuseExistingServer: false,
    env: {
      APP_ENV: 'e2e',
      DB_DATABASE: 'awareness_e2e',
    },
  },
});
