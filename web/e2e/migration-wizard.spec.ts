import { test, expect } from '@playwright/test';

test.describe('Migration Wizard', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/migration/wizard');
  });

  test('shows wizard page with title', async ({ page }) => {
    await expect(page.locator('h1')).toContainText('Migration Wizard');
  });

  test('step 1 shows source tool selection', async ({ page }) => {
    await expect(page.locator('[data-testid="step-source"]')).toBeVisible();
    await expect(page.locator('text=Select Source Tool')).toBeVisible();
  });

  test('can navigate to step 2 after selecting source tool', async ({ page }) => {
    // Click on cursor as source tool
    await page.locator('button:has-text("cursor")').first().click();
    await page.locator('button:has-text("Next")').click();

    await expect(page.locator('[data-testid="step-target"]')).toBeVisible();
  });

  test('completes all wizard steps to confirmation', async ({ page }) => {
    // Step 1: source
    await page.locator('button:has-text("cursor")').first().click();
    await page.locator('button:has-text("Next")').click();

    // Step 2: target
    await page.locator('[data-testid="step-target"] button:has-text("claude-code")').click();
    await page.locator('[data-testid="step-target"] button:has-text("Next")').click();

    // Step 3: project (skip)
    await page.locator('[data-testid="step-project"] button:has-text("Next")').click();

    // Step 4: paths (skip)
    await page.locator('[data-testid="step-paths"] button:has-text("Next")').click();

    // Step 5: confirm
    await expect(page.locator('[data-testid="step-confirm"]')).toBeVisible();
    await expect(page.locator('[data-testid="confirm-button"]')).toBeVisible();
    await expect(page.locator('text=cursor')).toBeVisible();
    await expect(page.locator('text=claude-code')).toBeVisible();
  });

  test('back button returns to previous step', async ({ page }) => {
    await page.locator('button:has-text("cursor")').first().click();
    await page.locator('button:has-text("Next")').click();
    await page.locator('button:has-text("← Back")').click();

    await expect(page.locator('[data-testid="step-source"]')).toBeVisible();
  });
});
