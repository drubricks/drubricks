// Table view (ScreenHandler::table(), a core View in the app): the switch
// links it, exposed filters, sorting and the pager reload only the table
// (HTMX, clean links), the bulk bar counts the ticked rows and a bulk action
// applies, the row arrow opens the kit menu, Edit opens a modal that comes
// back to the table, View opens the side panel;
// a user who edits only their own records gets checkboxes only on those;
// the modal is a window: dragged by its title bar, closed with the X.
import { browser, check, done, login, mark, noReload, settled } from './lib.mjs';

const { b, ctx } = await browser();
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
await login(page);

const names = () => page.locator('.drubricks-table-view .data-table tbody td.views-field-name').allInnerTexts();

console.log('table page + view switch');
const r = await page.goto('/apps/crm/deals/table');
check(r.status() === 200, 'table page 200');
check(await page.locator('.seg a[aria-current="page"][href$="/deals/table"]').count() === 1, 'Table is the active view in the switch');
check(await page.locator('.pane-toolbar .drubricks-filters input, .pane-toolbar .drubricks-filters select').count() >= 6, 'exposed filters in the header toolbar row');
check(await page.locator('.pane-body form.views-exposed-form').count() === 0, 'the View does not repeat the filters');

console.log('one header for every view');
// Same layout and height on the board, table and list of deals: title +
// count, the view switch as tall as the "add" group, filters in the
// toolbar row; switching views never moves the header.
const header = async () => page.locator('.pane-header:has(.seg)').first().evaluate((h) => ({
  height: h.getBoundingClientRect().height,
  subtitle: h.querySelector('.title p')?.textContent.trim(),
  seg: h.querySelector('.seg')?.getBoundingClientRect().height,
  add: h.querySelector('.tb-group')?.getBoundingClientRect().height,
  plus: !!h.querySelector('.tb-group use[href$="#i-plus"]'),
}));
const shapes = {};
for (const path of ['/apps/crm/deals', '/apps/crm/deals/table', '/apps/crm/deals/list']) {
  await page.goto(path);
  shapes[path] = await header();
  const s = shapes[path];
  check(/^\d+ items?$/.test(s.subtitle) && s.seg === s.add && s.plus, `${path}: count, switch as tall as add, + icon (${JSON.stringify(s)})`);
}
check(new Set(Object.values(shapes).map((s) => s.height)).size === 1, 'same header height on board, table and list');
await page.goto('/apps/crm/deals/table');
check(await page.locator('.data-table-wrap table.data-table').count() === 1, 'kit data table');
check(await page.locator('.app > .inspector').count() === 0, 'no side panel without ?open');

console.log('filter + sort + pager without a page load');
// Filters, sort links and the pager reload only the table (HTMX), the URL
// keeps the state, and the links carry no HTMX internals.
await mark(page);
const stage = page.locator('.pane-toolbar select[name="stage"]');
await stage.selectOption('lead');
await page.waitForURL(/stage=lead/);
await settled(page);
const leads = await page.locator('.data-table tbody td.views-field-stage').allInnerTexts();
check(leads.length > 0 && leads.every((s) => /Lead/.test(s)), `stage filter on change (${leads.join(', ')})`);
check(await stage.evaluate((el) => el === document.querySelector('.pane-toolbar select[name="stage"]')), 'the filter form stays (not swapped)');
const sortHref = await page.locator('th a[href*="order=name"]').getAttribute('href');
check(!/_wrapper_format|ajax_page_state|_triggering_element/.test(sortHref) && /stage=lead/.test(sortHref), `clean sort link keeps the filter (${sortHref})`);
await stage.selectOption('All');
await page.waitForURL((u) => !/stage=lead/.test(u.search));
await settled(page);
await page.click('th a[href*="order=name"]');
await page.waitForURL(/order=name/);
await settled(page);
const sorted = await names();
check(JSON.stringify(sorted) === JSON.stringify([...sorted].sort((a, c) => a.localeCompare(c))), 'sorted by name');
check(await page.locator('th[aria-sort="ascending"]').count() === 1, 'aria-sort on the sorted column');
const firstPage = await names();
await page.click('.pager a[href*="page=1"]');
await page.waitForURL(/page=1/);
await settled(page);
const secondPage = await names();
check(secondPage.length > 0 && secondPage[0] !== firstPage[0], `pager: page 2 (${secondPage.join(', ')})`);
await page.goBack();
await settled(page);
check(JSON.stringify(await names()) === JSON.stringify(firstPage), 'Back shows page 1 again');
check(await noReload(page), 'filter, sort, pager and Back without a page load');
await page.goto('/apps/crm/deals/table?order=name&sort=asc');

console.log('bulk bar');
const bar = page.locator('[data-drupal-selector="edit-header"] > .form-wrapper');
check(await bar.getAttribute('data-drubricks-bulk') === '0', 'bulk bar inactive without a selection');
const boxes = page.locator('.data-table tbody input[type="checkbox"]');
const first = (await names())[0];
const touched = (await names()).slice(0, 2);
await boxes.nth(0).check();
await boxes.nth(1).check();
check(await bar.getAttribute('data-drubricks-bulk') === '2', 'bulk bar counts 2');
check(/2/.test(await page.locator('.drubricks-bulk-count').innerText()), 'count shown');
await page.selectOption('select[name="action"]', { index: 3 });
const action = await page.locator('select[name="action"] option:checked').innerText();
await page.click('[data-drupal-selector="edit-header"] [type="submit"]');
await page.waitForLoadState('load');
check(await page.locator('.hud, [role="status"], .messages').filter({ hasText: /applied to 2/ }).count() >= 1, `"${action}" applied to 2`);
// Undo: activate the two deals again (only those).
await page.goto('/apps/crm/deals/table?status=2&order=name&sort=asc');
for (const name of touched) {
  await page.locator('.data-table tbody tr').filter({ has: page.locator('td.views-field-name', { hasText: name }) }).locator('input[type="checkbox"]').check();
}
await page.selectOption('select[name="action"]', 'crm_deal_publish_action');
await page.click('[data-drupal-selector="edit-header"] [type="submit"]');
await page.waitForLoadState('load');
await page.goto('/apps/crm/deals/table?status=2');
const still = await names();
check(touched.every((n) => !still.includes(n)), 'both deals active again');

console.log('row actions');
await page.goto('/apps/crm/deals/table?order=name&sort=asc');
await page.locator('.dropbutton-arrow-btn').first().click();
const items = await page.locator('.menu .menu-item').allInnerTexts();
check(items.join(',') === 'View,Delete', `arrow opens the kit menu (${items.join(', ')})`);
await page.keyboard.press('Escape');
await page.locator('.dropbutton [data-action-key="edit"] a').first().click();
await page.waitForSelector('.modal form#drubricks-entity-form, .modal form', { timeout: 10000 });
check(await page.locator('.modal input[name="name[0][value]"]').inputValue() === first, `Edit opens the modal (${first})`);
await page.locator('.modal [type="submit"]').filter({ hasText: /Save/ }).click();
await page.waitForTimeout(1500);
await settled(page);
check(new URL(page.url()).pathname === '/apps/crm/deals/table', `back on the table (${page.url()})`);

console.log('modal window: drag by the title bar, X closes');
await page.locator('.dropbutton [data-action-key="edit"] a').first().click();
const titleBar = page.locator('.modal .modal-bar.draggable');
await titleBar.waitFor({ timeout: 10000 });
// Measure once the form is in (the window grows) and the kit's opening
// animation is over.
await page.locator('.modal input[name="name[0][value]"]').waitFor({ timeout: 10000 });
await page.waitForTimeout(400);
const before = await page.locator('.modal').boundingBox();
const grip = await titleBar.boundingBox();
await page.mouse.move(grip.x + 40, grip.y + grip.height / 2);
await page.mouse.down();
await page.mouse.move(grip.x - 60, grip.y + grip.height / 2, { steps: 8 });
await page.mouse.up();
const after = await page.locator('.modal').boundingBox();
check(Math.round(after.x - before.x) === -100 && Math.round(after.y - before.y) === 0, `the window moved 100px left (${Math.round(after.x - before.x)}, ${Math.round(after.y - before.y)})`);
await page.mouse.move(grip.x - 60, grip.y + grip.height / 2);
await page.mouse.down();
await page.mouse.move(-500, -500, { steps: 8 });
await page.mouse.up();
const edge = await page.locator('.modal').boundingBox();
check(edge.x >= 8 && edge.y >= 8, 'the window stays on screen');
await page.locator('.modal .modal-close').click();
await page.waitForTimeout(400);
check(await page.locator('.modal').count() === 0, 'X closes the window');

console.log('view in the side panel');
await page.goto('/apps/crm/deals/table?order=name&sort=asc');
await page.locator('.dropbutton-arrow-btn').first().click();
await page.locator('.menu .menu-item').filter({ hasText: 'View' }).click();
await page.waitForSelector('.app > .inspector h3', { timeout: 10000 });
check((await page.locator('.app > .inspector h3').innerText()).trim() === first, 'View opens the record in the side panel');
const url = new URL(page.url());
check(url.pathname === '/apps/crm/deals/table' && url.searchParams.get('order') === 'name', 'the table keeps its sorting');

console.log('a user who edits only their own deals');
await ctx.clearCookies();
const user = await ctx.newPage();
await login(user, 'Alex Morgan');
await user.goto('/apps/crm/deals/table?order=name&sort=asc');
const rows = await user.locator('.data-table tbody tr').evaluateAll((trs) => trs.map((tr) => ({
  owner: tr.querySelector('td.views-field-uid')?.innerText.trim(),
  box: !!tr.querySelector('input[type="checkbox"]'),
  edit: !!tr.querySelector('[data-action-key="edit"]'),
})));
check(rows.length > 0 && rows.every((r) => r.box === (r.owner === 'Alex Morgan')), 'checkboxes only on own deals');
check(rows.every((r) => r.edit === (r.owner === 'Alex Morgan')), 'Edit only on own deals');

check(errors.length === 0, `no page errors (${errors.join(' | ')})`);
await b.close();
done();
