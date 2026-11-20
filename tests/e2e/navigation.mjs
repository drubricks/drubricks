// Phase 3: list → details → edit (modal) → save without any full page load;
// every intermediate URL opened directly gives the same screen.
import { browser, check, done, login, mark, noReload, settled } from './lib.mjs';

const { b, ctx } = await browser();
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });

await login(page);
check(new URL(page.url()).pathname === '/apps', `login lands on /apps (${page.url()})`);
await mark(page);

console.log('sidebar → board → list (content level)');
await page.click('.sidebar .sb-item:has-text("Deals")');
await page.waitForURL('**/apps/crm/deals');
await page.waitForSelector('[data-drubricks-board-island] .lane');
await settled(page);
check(await noReload(page), 'no full load after a sidebar click');
check(await page.locator('.sidebar .sb-item.active .label').innerText() === 'Deals', 'Deals is the active sidebar item');
check((await page.title()).startsWith('Deals'), `title is Deals (${await page.title()})`);
await page.click('nav.seg a[title="List"]');
await page.waitForURL('**/apps/crm/deals/list');
await page.waitForSelector('.column h1:has-text("Deals")');
await settled(page);
check(await noReload(page), 'no full load when switching Board → List');
check(await page.locator('.sidebar .sb-item.active .label').innerText() === 'Deals', 'Deals still the active sidebar item');

console.log('row → details (detail level)');
const firstRow = page.locator('.column .row').first();
const rowTitle = (await firstRow.locator('.row-title').innerText()).trim();
await firstRow.click();
await page.waitForSelector(`#drubricks-detail h2:has-text("${rowTitle}")`);
await settled(page);
const dealUrl = new URL(page.url()).pathname;
check(/^\/apps\/crm\/deals\/\d+$/.test(dealUrl), `URL is the deal (${dealUrl})`);
check(await noReload(page), 'no full load after a row click');
check(await page.locator('.column .row.selected').count() === 1, 'one selected row');

console.log('edit in a modal: invalid → inline error, valid → saved');
await page.click('#drubricks-detail [data-action-key="edit"]');
await page.waitForSelector('.modal form#drubricks-entity-form, [role="dialog"] form#drubricks-entity-form');
await page.selectOption('form#drubricks-entity-form select[name="stage"]', 'won');
await page.fill('form#drubricks-entity-form input[name="close_date[0][value][date]"]', '');
await page.click('form#drubricks-entity-form [type="submit"]:not([data-drubricks-modal-close])');
await page.waitForSelector('form#drubricks-entity-form .form-item--error-message, form#drubricks-entity-form [aria-invalid="true"]', { timeout: 10000 }).catch(() => {});
await settled(page);
check(/needs a close date/.test(await page.locator('form#drubricks-entity-form').innerText()), 'constraint error shown in the form');
check(await noReload(page), 'no full load after an invalid submit');

await page.fill('form#drubricks-entity-form input[name="close_date[0][value][date]"]', '2026-12-20');
await page.fill('form#drubricks-entity-form input[name="value[0][value]"]', '51000');
await page.click('form#drubricks-entity-form [type="submit"]:not([data-drubricks-modal-close])');
await page.waitForSelector('form#drubricks-entity-form', { state: 'detached', timeout: 10000 }).catch(() => {});
await settled(page);
check(await page.locator('form#drubricks-entity-form').count() === 0, 'modal closed after save');
check(new URL(page.url()).pathname === dealUrl, 'URL back on the deal');
check(/Won/.test(await page.locator('#drubricks-detail').innerText()), 'details show the new stage');
check(await noReload(page), 'no full load after a valid submit');
check(await page.locator('.hud, [role="status"]').filter({ hasText: /Saved/ }).count() >= 1, 'HUD shows the save message');

console.log('search (list level)');
await page.fill('.column input[name="q"]', rowTitle.slice(0, 5));
await page.waitForTimeout(900);
await settled(page);
check(await page.locator('.column .row').count() >= 1, 'search keeps matching rows');
check(await noReload(page), 'no full load while searching');

console.log('inspector');
await page.click('#drubricks-detail [data-action="toggle-inspector"]');
await page.waitForSelector('#drubricks-inspector .group-row');
await settled(page);
check(await page.locator('#drubricks-inspector').innerText().then((t) => /Owner/.test(t)), 'inspector shows record facts');
// Another row: the inspector follows the record (swapped with the details).
const otherRow = page.locator('.column .row:not(.selected)').first();
const otherTitle = (await otherRow.locator('.title, .row-title, strong').first().innerText().catch(() => '')).trim();
await otherRow.click();
await page.waitForTimeout(800);
await settled(page);
const inspectorTitle = (await page.locator('#drubricks-inspector h3').innerText().catch(() => '')).trim();
const detailTitle = (await page.locator('#drubricks-detail h2, #drubricks-detail h1').first().innerText().catch(() => '')).trim();
check(inspectorTitle !== '' && inspectorTitle === detailTitle, `inspector follows the selected record (${inspectorTitle} / ${detailTitle || otherTitle})`);
check(await noReload(page), 'no full load when the inspector follows');
// Undo that click in the history, for the Back check below.
await page.goBack();
await page.waitForTimeout(1200);

console.log('back button + direct URLs');
// History: … /deals → /deals/N → /deals/N (pushed again by the save) →
// search replaced it with /deals?q=…; Back returns to the deal.
await page.goBack();
await page.waitForTimeout(1200);
check(new URL(page.url()).pathname === dealUrl, `back goes to the deal (${page.url()})`);
check(await page.locator('#drubricks-detail h2').count() === 1, 'back shows the deal details');
check(await noReload(page), 'no full load on Back');
for (const url of ['/apps/crm/deals/list', dealUrl, `${dealUrl}/edit`, '/apps/crm/deals/add']) {
  const r = await page.goto(url);
  check(r.status() === 200 && await page.locator('.app .column').count() === 1, `direct ${url} renders the screen`);
}
// Nothing selected: no inspector at all (the list, a board without ?open=).
await page.goto('/apps/crm/deals/list');
check(await page.locator('.app > .inspector').count() === 0, 'no inspector on a list without a record');
check(await page.locator('#drubricks-detail [data-action="toggle-inspector"]').count() === 0, 'no inspector toggle without a record');
await page.goto('/apps/crm/deals');
check(await page.locator('.app > .inspector').count() === 0, 'no inspector on a board without an open card');
const board = await page.goto(`/apps/crm/deals?open=${dealUrl.split('/').pop()}`);
check(board.status() === 200 && await page.locator('#drubricks-inspector h3').count() === 1, 'direct board URL with ?open= shows the record in the panel');

console.log('sidebar brand: logo + site name, a link to Home');
await page.goto('/apps/crm/contacts');
await page.waitForSelector('.sidebar .pane-header a.brand');
await mark(page);
const brand = page.locator('.sidebar .pane-header a.brand');
check((await brand.locator('img.brand-logo').getAttribute('src') || '').endsWith('/logo.svg'), 'the theme logo in the sidebar header');
check((await brand.innerText()).trim() !== '', 'the site name next to the logo');
await brand.click();
await page.waitForURL('**/apps');
await page.waitForSelector('.drubricks-dashboard');
await settled(page);
check(await noReload(page), 'the brand opens Home without a full load');

console.log('colour scheme: Light / Dark / Auto in the account menu');
await page.emulateMedia({ colorScheme: 'dark' });
const scheme = () => page.evaluate(() => document.documentElement.dataset.scheme);
for (const [value, expected] of [['light', 'light'], ['dark', 'dark'], ['auto', 'dark']]) {
  if (!await page.locator(`[data-set-theme="${value}"]`).isVisible()) {
    await page.click('.sb-account');
  }
  await page.click(`[data-set-theme="${value}"]`);
  check(await scheme() === expected, `${value} → ${expected} scheme`);
  check(await page.locator(`[data-set-theme="${value}"]`).getAttribute('aria-pressed') === 'true', `${value} is pressed`);
}
await page.emulateMedia({ colorScheme: 'light' });
await page.waitForTimeout(200);
check(await scheme() === 'light', 'Auto follows the system setting');
check(await page.evaluate(() => JSON.parse(localStorage.getItem('ui.theme'))) === 'auto', 'the choice is remembered');
check(await page.locator('.menu-account-pop .palettes, .menu-account-pop [data-set-palette]').count() === 0, 'no palette choice for users (a site setting)');
console.log('compact window: the slide-over sidebar beside the admin toolbar');
// At ~1000px the app sidebar slides over the page; it must open next to
// core's Navigation (Drupal.displace() offsets), not under it.
await page.setViewportSize({ width: 1071, height: 758 });
await page.goto('/apps');
await page.click('.show-sidebar-btn:visible');
await page.waitForTimeout(400);
const slide = await page.evaluate(() => {
  const sb = document.querySelector('.app > .sidebar').getBoundingClientRect();
  const tb = document.querySelector('.admin-toolbar')?.getBoundingClientRect();
  const hit = document.elementFromPoint(sb.left + 60, sb.top + 150);
  return { mode: document.querySelector('.app').dataset.mode, left: sb.left, toolbar: tb ? tb.right : 0, hit: !!hit?.closest('.app > .sidebar') };
});
check(slide.mode === 'compact' && slide.left >= slide.toolbar && slide.hit, `sidebar opens beside the admin toolbar and takes clicks (${JSON.stringify(slide)})`);
await page.locator('.app > .sidebar .sb-item:has-text("Contacts")').click();
await page.waitForURL('**/apps/crm/contacts');
check(true, 'a sidebar link works in the slide-over');
await page.setViewportSize({ width: 1280, height: 720 });

console.log('masquerade: switch to another account and back (account menu)');
await page.goto('/user/2');
const masquerade = page.locator('a:has-text("Masquerade as")').first();
if (await masquerade.count()) {
  const other = (await masquerade.innerText()).replace(/^Masquerade as\s*/, '').trim();
  await Promise.all([page.waitForNavigation(), masquerade.click()]);
  await page.goto('/apps');
  check((await page.locator('.sb-account-name').innerText()).trim() === other, `switched to ${other}`);
  await page.click('.sb-account');
  await page.click('.menu-account-pop :text("Unmasquerade")');
  await page.waitForURL((url) => !url.pathname.startsWith('/unmasquerade'));
  await page.goto('/apps');
  check((await page.locator('.sb-account-name').innerText()).trim() === 'admin', 'Unmasquerade switches back (CSRF token in the account menu link)');
}
else {
  check(false, 'Masquerade link on a user page');
}
check(errors.length === 0, `no JS errors ${errors.join(' | ')}`);
await b.close();
done();
