import { test as setup, expect } from '@playwright/test';

// Signs in once and saves the session for all other specs.
// Uses the demo user created by `php artisan db:seed` in local environments.
const authFile = 'e2e/.auth/user.json';

setup('sign in', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email').fill(process.env.E2E_EMAIL ?? 'demo@movez.test');
  await page.getByLabel('Password').fill(process.env.E2E_PASSWORD ?? 'password');
  await page.getByRole('button', { name: 'Sign in' }).click();

  await expect(page).toHaveURL(/\/$/);
  await page.context().storageState({ path: authFile });
});
