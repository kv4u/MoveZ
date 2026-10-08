import { test, expect } from '@playwright/test';

test.describe('Dashboard', () => {
  test('shows MoveZ title and nav links', async ({ page }) => {
    await page.goto('/');

    await expect(page).toHaveTitle(/MoveZ/);
    await expect(page.locator('h1')).toContainText('MoveZ');
    const nav = page.locator('header nav');
    await expect(nav.getByRole('link', { name: 'Migration Wizard' })).toBeVisible();
    await expect(nav.getByRole('link', { name: 'Projects', exact: true })).toBeVisible();
  });

  test('shows stats cards with labels', async ({ page }) => {
    await page.goto('/');

    await expect(page.getByText('Total Sessions', { exact: true })).toBeVisible();
    await expect(page.getByText('Total Projects', { exact: true })).toBeVisible();
    await expect(page.getByText('Sync Status', { exact: true })).toBeVisible();
  });

  test('quick action cards are clickable', async ({ page }) => {
    await page.goto('/');

    await page.getByText('Browse Projects', { exact: true }).click();
    await expect(page).toHaveURL(/\/projects/);
  });
});
