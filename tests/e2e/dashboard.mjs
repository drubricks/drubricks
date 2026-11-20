// Home dashboard: blocks of the example apps render (stat tiles, panels),
// a heading link and a record link swap the app without a full page load,
// and Home comes back the same way.
import { browser, check, done, login, mark, noReload, settled } from './lib.mjs';

const { b, ctx } = await browser();
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));

await login(page);
await page.goto('/apps');
await page.waitForSelector('.drubricks-dashboard');

console.log('widgets render');
check(await page.locator('.drubricks-dashboard .dash-hello h2').innerText().then((t) => /^Hello, /.test(t)), 'greeting kept');
const stats = await page.locator('.drubricks-dashboard .stats .stat').count();
check(stats >= 3, `stat tiles in the strip (${stats})`);
const widgets = await page.locator('[data-drubricks-dashboard]').evaluateAll((els) => els.map((e) => e.dataset.drubricksDashboard));
// Each example app brings its own widgets; this check expects all three.
for (const id of [
  'drubricks_examples_crm_pipeline_by_stage',
  'drubricks_examples_crm_closing_soon',
  'drubricks_examples_crm_recent_contacts',
  'drubricks_examples_project_management_my_tasks',
  'drubricks_examples_project_management_projects',
  'drubricks_examples_project_management_tasks_by_status',
  'drubricks_examples_booking_next',
  'drubricks_examples_booking_busiest',
]) {
  check(widgets.includes(id), `widget ${id}`);
}
check(await page.locator('.drubricks-dashboard .insp-empty').count() === 0, 'no empty state when apps have widgets');
check(widgets.indexOf('drubricks_examples_crm_closing_soon') < widgets.indexOf('drubricks_examples_booking_next'), 'widgets ordered by weight (CRM before Booking)');
check(await page.locator('[data-drubricks-dashboard="drubricks_examples_crm_pipeline_by_stage"] .bar-row').count() === 4, 'pipeline: a bar per open stage');
check(await page.locator('[data-drubricks-dashboard="drubricks_examples_crm_recent_contacts"] .p-row .avatar').count() >= 1, 'recently contacted: avatars');
check(await page.locator('[data-drubricks-dashboard="drubricks_examples_project_management_projects"] [role="progressbar"][aria-label]').count() >= 1, 'projects: labelled progress bars');

console.log('a heading link swaps the app');
await mark(page);
await page.click('[data-drubricks-dashboard="drubricks_examples_crm_recent_contacts"] h3 a');
await page.waitForURL('**/apps/crm/contacts');
await page.waitForSelector('.column h1:has-text("Contacts")');
await settled(page);
check(await noReload(page), 'no full load after the heading link');
check(await page.locator('.sidebar .sb-item.active .label').innerText() === 'Contacts', 'Contacts is the active sidebar item');

console.log('back to Home, then a record link');
await page.click('.sidebar .sb-item:has-text("Home")');
await page.waitForSelector('[data-drubricks-dashboard="drubricks_examples_crm_closing_soon"]');
await settled(page);
check(await noReload(page), 'no full load back to Home');
const row = page.locator('[data-drubricks-dashboard="drubricks_examples_crm_closing_soon"] a.p-row').first();
const deal = (await row.locator('.t').innerText()).trim();
await row.click();
await page.waitForURL(/\/apps\/crm\/deals\/\d+$/);
await page.waitForSelector(`#drubricks-detail h2:has-text("${deal}")`);
await settled(page);
check(await noReload(page), 'no full load after a record link');
check(await page.locator('.column .row.selected').count() === 1, 'the deal is selected in the list');

console.log('a stat tile links too');
await page.goBack();
await page.waitForURL('**/apps');
await page.waitForSelector('.drubricks-dashboard .stats a.stat');
await settled(page);
await page.locator('.drubricks-dashboard .stats a.stat').first().click();
await page.waitForURL('**/apps/crm/deals');
await page.waitForSelector('[data-drubricks-board-island] .lane');
await settled(page);
check(await noReload(page), 'no full load after a stat tile');

check(errors.length === 0, `no JS errors (${errors.join(' | ')})`);
await b.close();
done();
