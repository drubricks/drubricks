// Fields added in the admin UI reach the app: a field added in
// Manage fields shows in the add / edit modal, is saved and shows in the
// record's details; hiding a field in Manage display hides it in the designed
// details, hiding it in Manage form display removes it from the modal.
// Cleans up after itself (and before, when a previous run stopped half way).
import { browser, check, done, login, settled } from './lib.mjs';

const SETTINGS = '/admin/app/structure/crm/contacts';
const FIELD = 'field_e2e_notes';
const NOTE = 'Prefers calls after 3 pm.';

const { b, ctx } = await browser();
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));

async function deleteField() {
  const r = await page.goto(`${SETTINGS}/fields/crm_contact.crm_contact.${FIELD}/delete`);
  if (r.status() === 200) {
    // The admin theme repeats form buttons in its top bar: the visible one.
    await Promise.all([page.waitForNavigation(), page.locator('[type="submit"][value="Delete"]:visible').first().click()]);
  }
}

// Region of a component in Manage (form) display: 'content' or 'hidden'.
// The region selects are hidden by tabledrag (drag and drop); set the value
// like "Show row weights" would let a user do.
async function setRegion(path, field, region) {
  await page.goto(path);
  await page.locator(`select[name="fields[${field}][region]"]`).evaluate((select, value) => {
    select.value = value;
    select.dispatchEvent(new Event('change', { bubbles: true }));
  }, region);
  await Promise.all([page.waitForNavigation(), page.locator('[data-drupal-selector="edit-submit"]:visible').first().click()]);
}

// Opens the contact's edit modal on the app screen.
async function openEdit(url) {
  await page.goto(url);
  await page.waitForSelector('#drubricks-detail h2');
  await page.click('[data-action-key="edit"]');
  await page.waitForSelector('dialog[open] form, .modal form');
  await settled(page);
}

await login(page);
await deleteField();

console.log('Application → Entity structure → Contacts: the tabs of Field UI');
await page.goto('/admin/app/structure');
// The link in the page (the admin sidebar has a hidden one too).
await page.locator(`main a[href="${SETTINGS}"]`).first().click();
await page.waitForURL(`**${SETTINGS}`);
for (const tab of ['Manage fields', 'Manage form display', 'Manage display']) {
  check(await page.locator(`nav a:has-text("${tab}")`).count() > 0, `tab ${tab}`);
}

console.log('admin sidebar: the current page in the Application section');
await page.goto(`${SETTINGS}/fields`);
const current = page.locator(`#admin-toolbar a[href="${SETTINGS}"].is-active`);
await current.waitFor({ state: 'attached', timeout: 5000 }).catch(() => null);
check(await current.count() === 1, 'the entity type link is marked on its Manage fields page');
const section = page.locator('#admin-toolbar [data-toolbar-popover]').filter({ has: page.locator(`a[href="${SETTINGS}"].is-active`) });
await section.locator('[data-toolbar-popover-control]').click();
const trigger = section.locator('[data-toolbar-menu-trigger]').filter({ hasText: 'Entity structure' });
await page.waitForTimeout(300);
check(await trigger.getAttribute('aria-expanded') === 'true', 'opening Application shows Entity structure expanded');
check(await current.isVisible(), 'with the entity type link visible');
await page.keyboard.press('Escape');

console.log('add a long text field in Manage fields');
await page.goto(`${SETTINGS}/fields`);
// The local action, in the admin theme's top bar: a dialog with the types.
await page.locator('a:has-text("Create a new field"):visible').first().click();
await page.locator('.ui-dialog a:has-text("Plain text")').click();
await page.waitForSelector('.ui-dialog input[name="label"]');
await page.fill('.ui-dialog input[name="label"]', 'E2E notes');
await page.click('.ui-dialog label:has-text("Long text")');
await page.click('.ui-dialog-buttonpane button:has-text("Continue")');
await page.waitForSelector('.ui-dialog-title:has-text("E2E notes")');
await page.waitForTimeout(300);
await Promise.all([
  page.waitForURL(`**${SETTINGS}/fields`),
  page.click('.ui-dialog-buttonpane button:has-text("Save")'),
]);
await page.waitForSelector(`text=${FIELD}`, { timeout: 10000 }).catch(() => null);
check(await page.locator(`text=${FIELD}`).count() > 0, 'the field is listed in Manage fields');

console.log('the field in the edit modal; saved; shown in the details');
await page.goto('/apps/crm/contacts');
const first = page.locator('.column .row').first();
await first.click();
await page.waitForSelector('#drubricks-detail h2');
const contactUrl = new URL(page.url()).pathname;
await openEdit(contactUrl);
const textarea = page.locator(`textarea[name="${FIELD}[0][value]"]`);
check(await textarea.count() === 1, 'the new field is in the edit modal');
await textarea.fill(NOTE);
await page.click('dialog[open] [type="submit"]:has-text("Save"), .modal [type="submit"]:has-text("Save")');
await page.waitForSelector(`.drubricks-fields:has-text("${NOTE}")`);
await settled(page);
check(true, 'the value shows in the details');
await page.goto(contactUrl);
check(await page.locator(`.drubricks-fields:has-text("${NOTE}")`).count() === 1, 'and on a direct load');
check(await page.locator('.drubricks-fields:has-text("E2E notes")').count() === 1, 'with its label');

console.log('Manage display: hiding Email hides it in the designed details');
check(await page.locator('#drubricks-detail .group-row:has-text("Email")').count() === 1, 'Email shown before');
await setRegion(`${SETTINGS}/display/default`, 'email', 'hidden');
await page.goto(contactUrl);
check(await page.locator('#drubricks-detail .group-row:has-text("Email")').count() === 0, 'Email fact hidden');
check(await page.locator('#drubricks-detail a:has-text("Email")').count() === 0, 'Email button hidden');
await setRegion(`${SETTINGS}/display/default`, 'email', 'content');
await page.goto(contactUrl);
check(await page.locator('#drubricks-detail .group-row:has-text("Email")').count() === 1, 'Email back after showing it again');

console.log('Manage form display: hiding the field removes it from the modal');
await setRegion(`${SETTINGS}/form-display`, FIELD, 'hidden');
await openEdit(contactUrl);
check(await page.locator(`textarea[name="${FIELD}[0][value]"]`).count() === 0, 'the field left the edit modal');

console.log('clean up: delete the field');
await deleteField();
await page.goto(contactUrl);
check(await page.locator('.drubricks-fields:has-text("E2E notes")').count() === 0, 'the field is gone from the details');

check(errors.length === 0, `no JS errors (${errors.join(' | ')})`);
await b.close();
done();
