// The design system page (drubricks_design_system): every section renders,
// every theme component appears in an example, JS components work — on a
// direct load and after reaching the page from the sidebar (HTMX swap of
// #drubricks-app, no full document load). No console errors.
import { browser, check, done, login, mark, noReload, settled } from './lib.mjs';

const { b, ctx } = await browser();
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });

await login(page);

async function sections(label) {
  console.log(`sections (${label})`);
  const ids = await page.$$eval('[data-ds-section]', (as) => as.map((a) => a.dataset.dsSection));
  check(ids.length >= 20, `table of contents lists the sections (${ids.length})`);
  const empty = await page.evaluate((list) => list.filter((id) => {
    const s = document.getElementById(id);
    if (!s) return true;
    const previews = s.querySelectorAll('.demo-preview');
    const codes = s.querySelectorAll('.demo-code code');
    return !previews.length && !codes.length;
  }), ids);
  check(empty.length === 0, `every section has examples${empty.length ? ` (empty: ${empty.join(', ')})` : ''}`);
  const blank = await page.$$eval('.demo-preview', (ps) => ps.filter((p) => !p.innerHTML.trim()).length);
  check(blank === 0, 'no empty preview');
}

async function components() {
  console.log('components');
  const ids = await page.$$eval('[data-ds-component]', (rows) => rows.map((r) => r.dataset.dsComponent));
  check(ids.length >= 70, `reference lists the theme components (${ids.length})`);
  const missing = await page.evaluate((list) => list.filter((id) => !document.querySelector(`#ds-docs .demo [data-component-id="${id}"], #ds-docs .demo-preview [data-component-id="${id}"]`)
    && !document.querySelector(`.app [data-component-id="${id}"]`)), ids);
  // drubricks:pane can't be shown inside a demo (it is a pane of .app): its example is code only.
  const shown = missing.filter((id) => id !== 'drubricks:pane');
  check(shown.length === 0, `every component is rendered in an example${shown.length ? ` (missing: ${shown.join(', ')})` : ''}`);
}

async function behaviour(label) {
  // Drupal behaviors bound once: one click = one toggle (also after a swap).
  const toggled = await page.evaluate(() => {
    const b = document.querySelectorAll('button.check[data-drubricks-check]')[1];
    const before = b.classList.contains('on');
    b.click();
    const seg = [...document.querySelectorAll('[data-drubricks-seg]')]
      .find((s) => [...s.querySelectorAll('button:not([data-set-theme])')].some((x) => !x.classList.contains('active')));
    const t = [...seg.querySelectorAll('button:not([data-set-theme])')].find((x) => !x.classList.contains('active'));
    t.click();
    return { check: b.classList.contains('on') !== before, seg: t.classList.contains('active') && seg.querySelectorAll('button.active').length === 1 };
  });
  check(toggled.check && toggled.seg, `${label}: check and seg toggle once per click`);
  console.log(`JS components (${label})`);
  // Tabs (kit behaviour on SDC markup).
  await page.locator('#ds-tabs .tab[data-tab="tasks"]').click();
  check(await page.locator('#ds-tabs [data-tab-panel="tasks"]').isVisible(), 'tabs: the Tasks panel shows');
  check(await page.locator('#ds-tabs [data-tab-panel="overview"]').isHidden(), 'tabs: the Overview panel hides');

  // Menu button → UI.menu → drubricks:menu.
  await page.locator('#menus [data-drubricks-menu]').first().click();
  await page.waitForSelector('.menu [role="menuitem"]');
  await page.locator('.menu [role="menuitem"]', { hasText: 'Duplicate' }).click();
  check(/duplicate/.test(await page.locator('[data-ds-out="menu"]').innerText()), 'menu: choosing an item fires drubricks:menu');
  check(await page.locator('.menu').count() === 0, 'menu: closes after a choice');

  // Date picker → hidden input + drubricks:date.
  const picker = page.locator('#calendar [data-drubricks-date-picker]');
  await picker.locator('.cal-day:not(.out):not([disabled])').nth(3).click();
  const value = await picker.locator('input[type=hidden]').inputValue();
  check(/^\d{4}-\d{2}-\d{2}$/.test(value), `date picker: the hidden input has the day (${value})`);
  check((await page.locator('[data-ds-out="date"]').innerText()).includes(value), 'date picker: drubricks:date fired');

  // Modal with actions.
  await page.locator('#popups [data-drubricks-modal-button]').first().click();
  await page.waitForSelector('.modal');
  check(await page.locator('.modal [name="name"]').count() === 1, 'modal: the slot body is inside the popup');
  await page.keyboard.press('Escape');
  await page.waitForSelector('.modal', { state: 'detached' });
  check((await page.locator('[data-ds-out="modal"]').innerText()).includes('drubricks:modal'), 'modal: closing fires drubricks:modal');

  // Kit widgets mounted by behaviours.
  check(await page.locator('#board .board-card').count() === 4, 'board: four cards');
  check(await page.locator('#week-calendar .calendar').count() === 1, 'week calendar: rendered');
  check(await page.locator('#tooltip [data-tip]').count() >= 5, 'tooltips: data-tip on the examples');

  // Twig snippet + generated HTML.
  check(await page.locator('#buttons .demo-code[data-lang="twig"] .tk-tag').count() > 0, 'snippets: Twig is highlighted');
  const html = page.locator('#buttons .demo').first().locator('.demo-code[data-lang="html"] details');
  await html.locator('summary').click();
  await page.waitForFunction(() => document.querySelector('#buttons .demo .demo-code[data-lang="html"] code').textContent.length > 0);
  check(/class="btn primary"/.test(await html.locator('code').innerText()), 'snippets: rendered HTML shows the kit markup');
}

console.log('direct load, light');
await page.emulateMedia({ colorScheme: 'light' });
let r = await page.goto('/apps/design-system');
check(r.status() === 200, 'GET /apps/design-system → 200');
await page.waitForTimeout(500);
check(await page.locator('.app > .column.ds-toc-pane').count() === 1 && await page.locator('.app > main.content#drubricks-detail').count() === 1, 'panes are direct children of .app');
check(await page.locator('.sidebar .sb-item.active .label').innerText() === 'Components', 'sidebar: Components is the active item');
await sections('direct');
await components();
await behaviour('direct');

console.log('deep link + dark mode');
await page.emulateMedia({ colorScheme: 'dark' });
r = await page.goto('/apps/design-system#table');
await page.waitForTimeout(800);
check(await page.evaluate(() => document.documentElement.dataset.scheme) === 'dark', 'dark scheme follows the system');
const top = await page.evaluate(() => document.getElementById('table').getBoundingClientRect().top - document.getElementById('ds-docs').getBoundingClientRect().top);
check(Math.abs(top) < 60, `#table is scrolled into view (${Math.round(top)}px)`);
check(await page.locator('[data-ds-section="table"].active').count() === 1, 'table of contents marks the section');

console.log('drupal form: inline error');
await page.emulateMedia({ colorScheme: 'light' });
await page.goto('/apps/design-system#drupal-forms');
await page.click('#drubricks-design-system-demo-form [type="submit"].primary');
await page.waitForLoadState('load');
check(await page.locator('#drubricks-design-system-demo-form .form-item--error-message').count() >= 1, 'empty title → inline error under the field');
check(await page.locator('#drubricks-design-system-demo-form input.field[aria-invalid="true"]').count() >= 1, 'the field is marked invalid');

console.log('via the sidebar (HTMX swap)');
await page.goto('/apps');
await page.waitForTimeout(500);
await mark(page);
await page.click('.sidebar .sb-item:has-text("Components")');
await page.waitForURL('**/apps/design-system');
await page.waitForSelector('#ds-docs .doc-section');
await settled(page);
check(await noReload(page), 'no full load after the sidebar click');
await page.waitForFunction(() => document.title.startsWith('Design system'), null, { timeout: 5000 }).catch(() => {});
check((await page.title()).startsWith('Design system'), `title is Design system (${await page.title()})`);
await sections('after swap');
await behaviour('after swap');

console.log('away and back: components are torn down and rebuilt');
await page.click('.sidebar .sb-item:has-text("Home")');
await page.waitForURL('**/apps');
await settled(page);
check(await page.locator('.board, .calendar, .menu').count() === 0, 'no board / calendar / menu left after leaving');
await page.click('.sidebar .sb-item:has-text("Components")');
await page.waitForSelector('#board .board-card');
await settled(page);
check(await page.locator('#board .board-card').count() === 4, 'board rebuilt once (four cards)');
check(await noReload(page), 'still no full load');

check(errors.length === 0, `no console errors${errors.length ? `: ${errors.slice(0, 5).join(' | ')}` : ''}`);
await b.close();
done();
