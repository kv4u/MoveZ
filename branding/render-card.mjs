// Renders branding/social-card.html to branding/social-card.png (1280×640) with headless Chromium.
import { createRequire } from 'node:module';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const require = createRequire(resolve('web/package.json'));
const { chromium } = require('playwright-core');

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1280, height: 640 } });
await page.goto(pathToFileURL(resolve('branding/social-card.html')).href);
await page.waitForLoadState('networkidle');
await page.screenshot({ path: 'branding/social-card.png' });
await browser.close();
console.log('branding/social-card.png');
