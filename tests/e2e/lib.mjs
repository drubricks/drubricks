// Small helpers shared by the end-to-end checks (plain Playwright, no runner).
import { execSync } from 'node:child_process';
import { chromium } from 'playwright';

// The site of this checkout: DRUBRICKS_URL, else the DDEV project's URL (a
// project's folder name, e.g. https://acme.ddev.site), else the starter's.
function ddevUrl() {
  try {
    return JSON.parse(execSync('ddev describe -j', { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] })).raw.primary_url;
  }
  catch (e) {
    return '';
  }
}
export const BASE = process.env.DRUBRICKS_URL || ddevUrl() || 'https://drubricks.ddev.site';

let failures = 0;
export function check(cond, msg) {
  if (cond) console.log(`  ok  ${msg}`);
  else { failures++; console.log(`  FAIL ${msg}`); }
}
export function done() {
  if (failures) { console.log(`\n${failures} check(s) failed`); process.exit(1); }
  console.log('\nall checks passed');
}

export async function browser() {
  const b = await chromium.launch();
  const ctx = await b.newContext({ ignoreHTTPSErrors: true, baseURL: BASE });
  return { b, ctx };
}

// Puts a marker on window; if it survives, no full document load happened.
export const mark = (page) => page.evaluate(() => { window.__noReload = true; });
export const noReload = (page) => page.evaluate(() => window.__noReload === true);

export async function login(page, name = 'admin', pass = 'admin') {
  await page.goto('/user/login');
  await page.fill('input[name="name"]', name);
  await page.fill('input[name="pass"]', pass);
  await Promise.all([page.waitForNavigation(), page.click('form#user-login-form [type="submit"]')]);
  if (new URL(page.url()).pathname.startsWith('/user/login')) {
    // Another password (or a demo user exported without one): a one-time
    // login link for that user.
    const link = execSync(`ddev drush uli --name=${JSON.stringify(name)}`, { encoding: 'utf8' }).trim().split('\n').pop();
    await page.goto(new URL(link).pathname);
    // A one-time link lands on the profile form; start where a login does.
    await page.goto('/apps');
  }
}

// Waits until htmx has finished a request and Drupal re-attached behaviors.
export const settled = (page) => page.waitForFunction(() => !document.querySelector('.htmx-request'), null, { timeout: 10000 })
  .then(() => page.waitForTimeout(150));
