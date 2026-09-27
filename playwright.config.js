// @ts-check
const { defineConfig, devices } = require('@playwright/test');
const path = require('path');

process.env.NO_PROXY = '127.0.0.1,localhost';
process.env.no_proxy = '127.0.0.1,localhost';

const testDbPath = path.resolve(__dirname, 'tests/fixtures/test_hekmat.db');

module.exports = defineConfig({
  testDir: './tests/e2e',
  timeout: 60000,
  expect: {
    timeout: 10000
  },
  fullyParallel: false,
  workers: 1,
  forbidOnly: !!process.env.CI,
  retries: 0,
  reporter: [['list']],
  use: {
    baseURL: 'http://127.0.0.1:8088',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    locale: 'fa-IR',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  webServer: {
    command: 'php -S 127.0.0.1:8088',
    env: {
      HEKMAT_DB_PATH: testDbPath,
    },
    url: 'http://127.0.0.1:8088/health.php',
    reuseExistingServer: !process.env.CI,
    timeout: 60000,
  },
});
