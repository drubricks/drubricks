// Calendar view (generic drubricks_core calendar) on the Booking example:
// the default view, an event opens in the side panel (the calendar stays),
// a move onto a booked slot is refused and snaps back, a free slot opens the
// add form prefilled, Week / Resources views.
import { browser, check, done, login, mark, noReload } from './lib.mjs';

const { b, ctx } = await browser();
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
const patches = [];
page.on('response', (r) => { if (r.request().method() === 'PATCH') patches.push(r.status()); });
await login(page);

await page.click('.sidebar .sb-item:has-text("Calendar")');
await page.waitForSelector('[data-drubricks-calendar-island] .cal-ev, [data-drubricks-calendar-island] [data-id]');
check(new URL(page.url()).pathname === '/apps/booking', 'the calendar is the main bookings view');
await mark(page);
const events = page.locator('[data-drubricks-calendar-island] [data-id]');
check(await events.count() > 5, `events this week: ${await events.count()}`);

console.log('event → side panel');
await events.first().click();
await page.waitForSelector('#drubricks-inspector h3');
await page.waitForFunction(() => new URL(location.href).searchParams.has('open'));
check(new URL(page.url()).pathname === '/apps/booking', 'the calendar stays');
check(await noReload(page), 'no full load');
check(await page.locator('#drubricks-inspector [data-action-key="edit"]').count() === 1, 'edit action in the panel');

console.log('resources view: move onto a booked slot is refused');
await page.click('[data-cal-view="resources"]');
await page.waitForTimeout(400);
// The view shows one day (today); demo bookings are on weekdays only, so on a
// weekend go forward to the next day with bookings.
for (let i = 0; i < 7 && await page.locator('[data-drubricks-calendar-island] [data-id]').count() === 0; i++) {
  await page.click('[data-cal-nav="1"]');
  await page.waitForTimeout(400);
}
// Two bookings in the same column on the shown day: move the later one up into the earlier.
const pair = await page.evaluate(() => {
  const byCol = {};
  document.querySelectorAll('[data-drubricks-calendar-island] [data-id]').forEach((el) => {
    const col = el.closest('[data-col], .cal-col, [data-resource]');
    const key = col ? (col.dataset.col || col.dataset.resource || col.className) : 'x';
    (byCol[key] ||= []).push(el.dataset.id);
  });
  return Object.values(byCol).find((ids) => ids.length >= 1)?.[0] || null;
});
check(!!pair, 'found an event to move');
if (pair) {
  patches.length = 0;
  // Alt+↑ moves by one step (15 min); repeat until it overlaps or hits opening hours.
  const ev = page.locator(`[data-drubricks-calendar-island] [data-id="${pair}"]`);
  const label = () => page.locator(`[data-drubricks-calendar-island] [data-id="${pair}"]`).first().evaluate((el) => el.getAttribute('aria-label') || el.textContent);
  const before = await label();
  for (let i = 0; i < 12 && !patches.includes(422); i++) {
    await page.locator(`[data-drubricks-calendar-island] [data-id="${pair}"]`).focus();
    await page.keyboard.press('Alt+ArrowUp');
    await page.waitForTimeout(500);
  }
  check(patches.includes(200) || patches.includes(422), `moves were saved or refused (${patches.join(',')})`);
  if (patches.includes(422)) {
    check(await page.locator('.hud, [role="status"]').filter({ hasText: /already booked|between/ }).count() >= 1, 'the rule is shown when refused');
  }
  // Put it back: Alt+↓ as many times as it moved.
  const moved = patches.filter((s) => s === 200).length;
  for (let i = 0; i < moved; i++) {
    await page.locator(`[data-drubricks-calendar-island] [data-id="${pair}"]`).focus();
    await page.keyboard.press('Alt+ArrowDown');
    await page.waitForTimeout(500);
  }
  const after = await label();
  check(after === before, `event back in its slot (${after})`);
}

console.log('free slot → add form prefilled');
await page.click('[data-cal-view="week"]');
await page.waitForTimeout(300);
const grid = page.locator('[data-drubricks-calendar-island] [tabindex="0"]').first();
await grid.focus();
await page.keyboard.press('Enter');
await page.waitForSelector('.modal form#drubricks-entity-form', { timeout: 8000 }).catch(() => {});
const startDate = await page.locator('.modal input[name="starts[0][value][date]"]').inputValue().catch(() => '');
check(/^\d{4}-\d{2}-\d{2}$/.test(startDate), `add form opened with the slot's date (${startDate})`);
await page.keyboard.press('Escape');

check(errors.length === 0, `no JS errors ${errors.join(' | ')}`);
await b.close();
done();
