import { test, expect } from '@playwright/test';

test.describe('Dashboard', () => {
  test('shows MoveZ title and nav links', async ({ page }) => {
    await page.goto('/');

    await expect(page).toHaveTitle(/MoveZ/);
    await expect(page.locator('h1')).toContainText('MoveZ');
    await expect(page.locator('text=Migration Wizard')).toBeVisible();
    await expect(page.locator('text=Projects')).toBeVisible();
  });

  test('shows stats cards with labels', async ({ page }) => {
    await page.goto('/');

    await expect(page.locator('text=Total Sessions')).toBeVisible();
    await expect(page.locator('text=Total Projects')).toBeVisible();
    await expect(page.locator('text=Sync Status')).toBeVisible();
  });

  test('quick action cards are clickable', async ({ page }) => {
    await page.goto('/');

    await page.locator('text=Browse Projects').click();
    await expect(page).toHaveURL(/\/projects/);
  });
});
