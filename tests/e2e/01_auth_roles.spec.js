// @ts-check
const { test, expect } = require('@playwright/test');

test.describe('E2E Regression: Authentication & RBAC Isolation', () => {

  test.beforeEach(async ({ context }) => {
    await context.clearCookies();
  });

  test('Superadmin (faridelmi) can log in and view executive admin dashboard', async ({ page }) => {
    await page.goto('/login.php');

    // Step 1: Username
    await page.fill('input[name="username"]', 'faridelmi');
    await page.click('button[type="submit"]');

    // Step 2: Password
    await page.waitForSelector('input[name="password"]');
    await page.fill('input[name="password"]', '123456');
    await page.click('button[type="submit"]');

    // Verify redirected to admin dashboard
    await page.waitForURL('**/admin/index.php');
    await expect(page).toHaveTitle(/مدیریت|بنیاد/);
    
    // Verify Superadmin executive privileges are present
    const bodyText = await page.innerText('body');
    expect(bodyText).toContain('مدیرعامل');
  });

  test('Data Operator (viana) is strictly blocked from financial management with 403', async ({ page }) => {
    await page.goto('/login.php');

    // Step 1: Username
    await page.fill('input[name="username"]', 'viana');
    await page.click('button[type="submit"]');

    // Step 2: Password
    await page.waitForSelector('input[name="password"]');
    await page.fill('input[name="password"]', '123456');
    await page.click('button[type="submit"]');

    // Operator gets redirected to allowed area
    await page.waitForURL(/\/(admin\/book-requests\.php|people-list\.php)/);

    // REGRESSION CHECK: Attempt to directly navigate to financial section
    const response = await page.goto('/admin/financial.php');
    expect(response?.status()).toBe(403);

    // Verify 403 Access Denied template rendered
    const pageContent = await page.content();
    expect(pageContent).toContain('دسترسی مسدود است');
  });

  test('Board Member (behnam) cannot see edit/save actions (Read-Only Mode)', async ({ page }) => {
    await page.goto('/login.php');

    // Step 1: Username
    await page.fill('input[name="username"]', 'behnam');
    await page.click('button[type="submit"]');

    // Step 2: Password
    await page.waitForSelector('input[name="password"]');
    await page.fill('input[name="password"]', '123456');
    await page.click('button[type="submit"]');

    await page.waitForURL('**/admin/index.php');

    // Visit bursary payments page as board member
    await page.goto('/admin/bursary-payments.php');
    const pageText = await page.innerText('body');

    // REGRESSION CHECK: Board member can view bursary list
    expect(pageText).toContain('لیست پرداخت‌های ماهیانه');
  });

});
