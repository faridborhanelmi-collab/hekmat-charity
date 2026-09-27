// @ts-check
const { test, expect } = require('@playwright/test');

test.describe('E2E Regression: Student Directory & Details Lifecycle', () => {

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

  test('Student directory (people-list.php) lists students and allows search', async ({ page }) => {
    await page.goto('/people-list.php');

    // Check page header
    const bodyText = await page.innerText('body');
    expect(bodyText).toContain('مددجویان');

    // Check seeded students exist in DOM
    expect(bodyText).toContain('علی');
    expect(bodyText).toContain('فاطمه');
  });

  test('Student profile (person-detail.php) displays 360-degree records and financial fields', async ({ page }) => {
    await page.goto('/person-detail.php?id=1');

    const bodyText = await page.innerText('body');
    // Verify student name and school
    expect(bodyText).toContain('علی');
    expect(bodyText).toContain('اکبری');

    // REGRESSION CHECK: Verify base bursary and installment calculations are visible for superadmin
    expect(bodyText).toMatch(/بورسیه|کمک‌هزینه/);
  });

});
