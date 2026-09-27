// @ts-check
const { test, expect } = require('@playwright/test');

test.describe('E2E Regression: Public Website & Landing Pages Health', () => {

  test('Homepage (index.php) loads cleanly with logo, title and navigation', async ({ page }) => {
    const res = await page.goto('/index.php');
    expect(res?.status()).toBe(200);

    const title = await page.title();
    expect(title).toContain('بنیاد');

    const bodyText = await page.innerText('body');
    expect(bodyText).toContain('حکمت');
  });

  test('About page (about.php) displays charity background and licenses', async ({ page }) => {
    const res = await page.goto('/about.php');
    expect(res?.status()).toBe(200);

    const bodyText = await page.innerText('body');
    expect(bodyText).toContain('داستان ما');
  });

  test('Campaigns page (campaign.php) loads active donation initiatives', async ({ page }) => {
    const res = await page.goto('/campaign.php');
    expect(res?.status()).toBe(200);

    const bodyText = await page.innerText('body');
    expect(bodyText).toMatch(/کمپین|حمایت|نیکوکاری/);
  });

  test('Diamond Project (almas.php) renders talent discovery registration form', async ({ page }) => {
    const res = await page.goto('/almas.php');
    expect(res?.status()).toBe(200);

    const bodyText = await page.innerText('body');
    expect(bodyText).toContain('گنج‌های پنهان');
  });

});
