// @ts-check
const { test, expect } = require('@playwright/test');

test.describe('E2E Regression: Bursary Ledger & Financial Management', () => {

  test.beforeEach(async ({ page }) => {
    // Log in as Superadmin
    await page.goto('/login.php');
    await page.fill('input[name="username"]', 'faridelmi');
    await page.click('button[type="submit"]');
    await page.waitForSelector('input[name="password"]');
    await page.fill('input[name="password"]', '123456');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin/index.php');
  });

  test('Bursary payments (admin/bursary-payments.php) loads financial table correctly', async ({ page }) => {
    const res = await page.goto('/admin/bursary-payments.php');
    expect(res?.status()).toBe(200);

    const bodyText = await page.innerText('body');
    // Verify title and essential columns
    expect(bodyText).toContain('بورسیه');
    expect(bodyText).toMatch(/پرداخت|مبلغ|وضعیت/);
  });

  test('Financial ledger (admin/financial.php) displays accounting and expenses', async ({ page }) => {
    const res = await page.goto('/admin/financial.php');
    expect(res?.status()).toBe(200);

    const bodyText = await page.innerText('body');
    expect(bodyText).toContain('مالی');
  });

});
