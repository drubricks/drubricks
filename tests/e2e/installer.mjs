// Walks the browser installer. DESTRUCTIVE: drops the local database first.
// Runs only with DRUBRICKS_INSTALL_TEST=1:
//   DRUBRICKS_INSTALL_TEST=1 APPS=all node tests/e2e/installer.mjs
//   APPS=none | all | comma list of recipe names (each app brings its demo data)
import { execSync } from 'node:child_process';
import { browser, check, done } from './lib.mjs';

if (process.env.DRUBRICKS_INSTALL_TEST !== '1') {
  console.log('skipped (set DRUBRICKS_INSTALL_TEST=1; it drops the local database)');
  process.exit(0);
}
const APPS = process.env.APPS || 'all';

execSync('ddev drush sql:drop -y', { stdio: 'inherit' });
const { b, ctx } = await browser();
const page = await ctx.newPage();
page.setDefaultTimeout(180000);

await page.goto('/core/install.php');
// Language (if asked), then requirements / database are handled by DDEV.
if (await page.locator('select[name="langcode"]').count()) {
  await page.click('[type="submit"]');
}
await page.waitForSelector('input[name="site_name"]');
check(true, 'reached "Configure site" (distribution skips the profile choice)');
await page.fill('input[name="site_name"]', 'Drubricks');
await page.fill('input[name="site_mail"]', 'admin@example.com');
await page.fill('input[name="account[name]"]', 'admin');
await page.fill('input[name="account[pass][pass1]"]', 'admin');
await page.fill('input[name="account[pass][pass2]"]', 'admin');
await page.fill('input[name="account[mail]"]', 'admin@example.com');
await page.click('[type="submit"]');

await page.waitForSelector('form#drubricks-installer-apps');
const boxes = page.locator('input[name^="apps["]');
const names = await boxes.evaluateAll((els) => els.map((e) => e.value));
check(names.length >= 3, `app recipes listed: ${names.join(', ')}`);
const wanted = APPS === 'all' ? names : APPS === 'none' ? [] : APPS.split(',');
for (const name of names) {
  const box = page.locator(`input[name="apps[${name}]"]`);
  if (wanted.includes(name)) await box.check(); else await box.uncheck();
}
check(await page.locator('input[name="demo"]').count() === 0, 'no separate demo data switch');
await page.click('form#drubricks-installer-apps [type="submit"]');

await page.waitForURL(/\/apps(\?|$)/, { timeout: 600000 });
check(true, `installer finished on ${page.url()}`);
const sidebar = await page.locator('.sidebar').innerText();
check(wanted.includes('drubricks_examples_crm') === sidebar.includes('Contacts'), 'CRM in the sidebar exactly when chosen');
check(wanted.includes('drubricks_examples_project_management') === sidebar.includes('Task board'), 'Project management in the sidebar exactly when chosen');
if (wanted.includes('drubricks_examples_crm')) {
  const count = Number((await page.locator('.sb-item:has-text("Contacts") .count').innerText()) || 0);
  check(count === 10, `the CRM came with its demo contacts: ${count}`);
}
await b.close();
const profile = execSync('ddev drush php:eval \'echo var_export(\\Drupal::config("core.extension")->get("profile"), TRUE);\'').toString().trim();
check(profile === 'NULL', `installer profile removed (${profile})`);
done();
