// Add → edit → delete for every entity type of the example apps, in the
// modal, without full page loads; delete blockers; dblog entries per operation.
import { browser, check, done, login, mark, noReload, settled } from './lib.mjs';

const TYPES = [
  { path: '/apps/crm/companies', fill: {} },
  { path: '/apps/crm/contacts', fill: {} },
  // Deals: the list lives under /list; after a delete the shell returns to
  // the default view, the board.
  { path: '/apps/crm/deals/list', after: '/apps/crm/deals', fill: { select: ['company'] } },
  { path: '/apps/pm/projects', fill: {} },
  { path: '/apps/pm/tasks', fill: { select: ['project'] } },
];

const { b, ctx } = await browser();
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
await login(page);
const stamp = Date.now().toString(36);
const form = 'form#drubricks-entity-form';
const submit = `${form} [type="submit"]:not([data-drubricks-modal-close])`;

for (const type of TYPES) {
  const name = `E2E ${stamp} ${type.path.split('/').pop()}`;
  console.log(type.path);
  await page.goto(type.path);
  await mark(page);

  // Add.
  await page.click('.column [data-drubricks-modal]');
  await page.waitForSelector(form);
  await page.fill(`${form} input[name="name[0][value]"]`, name);
  for (const select of type.fill.select || []) {
    const value = await page.locator(`${form} select[name="${select}"] option:not([value="_none"])`).first().getAttribute('value');
    await page.selectOption(`${form} select[name="${select}"]`, value);
  }
  await page.click(submit);
  await page.waitForSelector(`#drubricks-detail h2:has-text("${name}")`, { timeout: 10000 }).catch(() => {});
  await settled(page);
  check(await page.locator(`.column .row:has-text("${name}")`).count() === 1, 'added: row in the list');
  check(await page.locator('#drubricks-detail h2').innerText().then((t) => t === name), 'added: details shown');
  check(await noReload(page), 'added without a reload');

  // Edit.
  await page.click('#drubricks-detail [data-action-key="edit"]');
  await page.waitForSelector(form);
  await page.fill(`${form} input[name="name[0][value]"]`, `${name} v2`);
  await page.click(submit);
  await page.waitForSelector(`#drubricks-detail h2:has-text("${name} v2")`, { timeout: 10000 }).catch(() => {});
  await settled(page);
  check(await page.locator(`.column .row:has-text("${name} v2")`).count() === 1, 'edited: list updated');
  check(await noReload(page), 'edited without a reload');

  // Delete.
  await page.click('#drubricks-detail [data-action-key="delete"]');
  await page.waitForSelector('form#drubricks-delete-form');
  await page.click('form#drubricks-delete-form [type="submit"]');
  await page.waitForSelector('form#drubricks-delete-form', { state: 'detached', timeout: 10000 }).catch(() => {});
  await settled(page);
  check(await page.locator(`.column .row:has-text("${name}")`).count() === 0, 'deleted: row gone');
  check(new URL(page.url()).pathname === (type.after || type.path), 'deleted: back on the main view');
  check(await noReload(page), 'deleted without a reload');
}

console.log('delete blocker: a company with contacts');
await page.goto('/apps/crm/companies');
await page.locator('.column .row').filter({ hasText: 'Kestrel Freight' }).click();
await page.waitForSelector('#drubricks-detail h2:has-text("Kestrel Freight")');
await settled(page);
// "delete" access is forbidden with the reason: a disabled button says why.
const del = page.locator('#drubricks-detail [data-action-key="delete"]');
check(await del.getAttribute('aria-disabled') === 'true', 'Delete is disabled');
check(/belong to this company/.test(await del.getAttribute('data-tip') || ''), `the reason is shown (${await del.getAttribute('data-tip')})`);
check(await page.locator('.modal').count() === 0 && (await del.evaluate((el) => el.tagName)) === 'BUTTON', 'no delete form to open');
const companyUrl = new URL(page.url()).pathname;
const denied = await page.request.get(`${companyUrl}/delete`);
check(denied.status() === 403, `the delete form itself is refused (${denied.status()})`);

console.log('dblog');
await page.goto(`/admin/reports/dblog?type[]=crm_company`);
const log = await page.locator('table').innerText().catch(() => '');
check(log.includes(`Created company`) && log.includes(`Deleted company`), 'create and delete of a company are logged');

check(errors.length === 0, `no JS errors ${errors.join(' | ')}`);
await b.close();
done();
