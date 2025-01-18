import { test, expect } from '@playwright/test';

test('landing page', async ({ page }) => {
  await page.goto('/');

  await expect(page).toHaveTitle('CFB Marble Game');
  await expect(page.getByRole('heading', { name: 'CFB Marble Game' })).toBeVisible();
});
