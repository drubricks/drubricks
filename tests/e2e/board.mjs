// Board views (generic drubricks_core board): the task board takes several
// moves in a row (the sidebar refresh after a move must not tear it down),
// its project filter works; the deal board is the default deals view and a
// move the server refuses (validation) is reverted.
import { browser, check, done, login, mark, noReload } from './lib.mjs';

const { b, ctx } = await browser();
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
const patches = [];
page.on('response', (r) => { if (r.request().method() === 'PATCH') patches.push(r.status()); });
await login(page);
await page.goto('/apps/pm/board');
await mark(page);

const laneOf = (key) => page.locator('[data-drubricks-board-island] .lane').filter({ has: page.locator('.bc-key', { hasText: new RegExp(`^${key}$`) }) }).first().getAttribute('data-lane');
const cards = page.locator('[data-drubricks-board-island] .lane[data-lane="todo"] .bc-key');
const keys = (await cards.allInnerTexts()).slice(0, 3);
check(keys.length === 3, `three cards in "To do" (${keys.join(', ')})`);

for (const key of keys) {
  const card = page.locator('[data-drubricks-board-island] .card, [data-drubricks-board-island] [data-id]').filter({ has: page.locator('.bc-key', { hasText: new RegExp(`^${key}$`) }) }).first();
  await card.focus();
  await page.keyboard.press('Alt+ArrowRight');
  await page.waitForTimeout(1200);
  check(await laneOf(key) === 'doing', `${key} moved to "In progress"`);
}
check(patches.length === 3 && patches.every((s) => s === 200), `three PATCH 200 (${patches.join(', ')})`);
check(await noReload(page), 'no full page load');

await page.reload();
for (const key of keys) check(await laneOf(key) === 'doing', `${key} still in "In progress" after reload`);

// Put them back.
for (const key of keys) {
  const card = page.locator('[data-drubricks-board-island] [data-id]').filter({ has: page.locator('.bc-key', { hasText: new RegExp(`^${key}$`) }) }).first();
  await card.focus();
  await page.keyboard.press('Alt+ArrowLeft');
  await page.waitForTimeout(900);
}
await page.reload();
for (const key of keys) check(await laneOf(key) === 'todo', `${key} back in "To do"`);

console.log('project filter');
const filter = page.locator('[data-board-filter]');
const option = await filter.locator('option').nth(1).getAttribute('value');
await filter.selectOption(option);
await page.waitForTimeout(400);
const projects = await page.locator('[data-drubricks-board-island] .bc-sub').allInnerTexts();
check(projects.length > 0 && new Set(projects).size === 1, `one project on the board (${[...new Set(projects)].join(', ')})`);
check(new URL(page.url()).searchParams.get('project') === option, 'filter kept in the URL');

console.log('deals: board is the default view, refused move reverts');
await page.goto('/apps/crm/deals');
check(await page.locator('[data-drubricks-board-island] .lane').count() === 6, 'deal board with 6 stages');
check(await page.locator('nav.seg a.active').getAttribute('title') === 'Board', 'Board is the active view');
await mark(page);
await page.locator('[data-drubricks-board-island] [data-id]').first().click();
await page.waitForSelector('#drubricks-inspector h3');
await page.waitForFunction(() => new URL(location.href).searchParams.has('open'));
check(new URL(page.url()).pathname === '/apps/crm/deals' && new URL(page.url()).searchParams.has('open'), 'a card opens in the side panel, the board stays');
check(await noReload(page), 'no full load when opening a card');
// A new deal without a close date: moving it to Won must be refused (422).
await page.click('#drubricks-detail [data-drubricks-modal]');
await page.waitForSelector('.modal form#drubricks-entity-form');
const name = `E2E board ${Date.now().toString(36)}`;
await page.fill('.modal input[name="name[0][value]"]', name);
const company = await page.locator('.modal select[name="company"] option:not([value="_none"])').first().getAttribute('value');
await page.selectOption('.modal select[name="company"]', company);
await page.click('.modal form [type="submit"]:not([data-drubricks-modal-close])');
// Saved from the board: the board stays, the new deal opens in the panel.
await page.waitForSelector(`#drubricks-inspector h3:has-text("${name}")`, { timeout: 10000 });
check(new URL(page.url()).pathname === '/apps/crm/deals', 'after adding, still on the board');
const dealId = new URL(page.url()).searchParams.get('open');
const dealUrl = `/apps/crm/deals/${dealId}`;
await page.goto('/apps/crm/deals');
patches.length = 0;
for (let i = 0; i < 4; i++) {
  await page.locator(`[data-drubricks-board-island] [data-id="${dealId}"]`).focus();
  await page.keyboard.press('Alt+ArrowRight');
  await page.waitForTimeout(900);
}
const laneNow = await page.locator(`[data-drubricks-board-island] [data-id="${dealId}"]`).evaluate((el) => el.closest('.lane').dataset.lane);
check(patches.at(-1) === 422 && laneNow === 'negotiation', `move to Won refused (${patches.join(',')}), card back in Negotiation`);
check(await page.locator('.hud, [role="status"]').filter({ hasText: /close date/ }).count() >= 1, 'the rule is shown');
// Clean up the test deal.
await page.goto(`${dealUrl}/delete`);
await page.click('form#drubricks-delete-form [type="submit"]');
await page.waitForTimeout(800);
await page.goto('/apps/crm/deals');
check(await page.locator(`[data-drubricks-board-island] [data-id="${dealId}"]`).count() === 0, 'test deal removed');
await page.click('nav.seg a[title="List"]');
await page.waitForTimeout(1500);
check(new URL(page.url()).pathname === '/apps/crm/deals/list', `List URL (${page.url()})`);
check(await page.locator('.column .row').count() >= 9, 'List view of deals');

check(errors.length === 0, `no JS errors ${errors.join(' | ')}`);
await b.close();
done();
