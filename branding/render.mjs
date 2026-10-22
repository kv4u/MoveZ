// Renders branding/*.svg to PNGs with headless Chromium (Playwright from web/).
// Usage: node branding/render.mjs <svg> <outDir> <size> [<size> ...]
import { readFileSync, mkdirSync } from 'node:fs';
import { createRequire } from 'node:module';
import { basename, join, resolve } from 'node:path';

const require = createRequire(resolve('web/package.json'));
const { chromium } = require('playwright-core');

const [svgPath, outDir, ...sizes] = process.argv.slice(2);
const svg = readFileSync(svgPath, 'utf8');
const name = basename(svgPath, '.svg');
mkdirSync(outDir, { recursive: true });

const browser = await chromium.launch();
const page = await browser.newPage();

for (const size of sizes.map(Number)) {
  await page.setViewportSize({ width: size, height: size });
  await page.setContent(
    `<html><body style="margin:0;background:transparent">
       <img src="data:image/svg+xml;base64,${Buffer.from(svg).toString('base64')}" width="${size}" height="${size}" style="display:block">
     </body></html>`,
  );
  await page.waitForLoadState('load');
  await page.screenshot({ path: join(outDir, `${name}-${size}.png`), omitBackground: true });
  console.log(`${name}-${size}.png`);
}

await browser.close();
