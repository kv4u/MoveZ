import { test, expect } from '@playwright/test';

test.describe('Projects', () => {
  test('projects page renders', async ({ page }) => {
    await page.goto('/projects');

    await expect(page).toHaveTitle(/MoveZ/);
    await expect(page.locator('h1')).toContainText('Projects');
  });

  test('shows back link to home', async ({ page }) => {
    await page.goto('/projects');

    await expect(page.locator('text=← Home')).toBeVisible();
  });

  test('navigates back to home', async ({ page }) => {
    await page.goto('/projects');

    await page.locator('text=← Home').click();
    await expect(page).toHaveURL('/');
  });
});
