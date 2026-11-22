// Charts (contrib Charts, Chart.js, built with Views): the dashboard charts
// are drawn, CRM → Revenue opens the chart view of deals without a page load
// and its filters reload only the chart, the charts use the kit colours and
// follow light / dark without a reload, the design system shows the chart
// examples.
import { browser, check, done, login, mark, noReload, settled } from './lib.mjs';

const { b, ctx } = await browser();
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
await login(page);

/** Chart.js charts on the page: series labels and the x-axis tick colour. */
const charts = () => page.evaluate(() => Object.values(window.Chart?.instances ?? {})
  .filter((c) => c.canvas.isConnected)
  .map((c) => ({
    series: c.data.datasets.map((d) => d.label),
    points: c.data.datasets.reduce((n, d) => n + d.data.filter((v) => v).length, 0),
    colours: c.data.datasets.map((d) => d.backgroundColor),
    tick: c.scales?.x?.options?.ticks?.color,
  })));

console.log('dashboard charts');
await page.goto('/apps');
await page.waitForFunction(() => Object.keys(window.Chart?.instances ?? {}).length >= 4, null, { timeout: 10000 });
const home = await charts();
check(home.length === 4 && home.every((c) => c.points > 0), `four charts with data (${home.map((c) => c.points).join(', ')})`);
check(home.every((c) => c.colours.every((colour) => /^(#|rgb)/.test(String(colour)))), 'series colours resolved from kit tokens');
check(await page.locator('.charts-figure .visually-hidden table').count() === 4, 'a data table for screen readers per chart');

console.log('CRM → Revenue: the chart view, without a page load');
await mark(page);
await page.locator('.sidebar a', { hasText: 'Revenue' }).click();
await page.waitForURL(/\/apps\/crm\/deals\/chart/);
await settled(page);
await page.waitForFunction(() => Object.values(window.Chart?.instances ?? {}).some((c) => c.canvas.isConnected && c.canvas.closest('.drubricks-chart-view')));
const all = (await charts()).at(-1);
check(all.series.includes('Won') && all.series.length > 1, `stacked by stage (${all.series.join(', ')})`);
check(await page.locator('.seg a[aria-current="page"][href$="/deals/chart"]').count() === 1, 'Chart is the active view in the switch');
check(await page.locator('.pane-toolbar select[name="stage"]').count() === 1, 'exposed filters in the header toolbar row');

await page.locator('.pane-toolbar select[name="stage"]').selectOption('won');
await page.waitForURL(/stage=won/);
await settled(page);
await page.waitForFunction(() => Object.values(window.Chart.instances).some((c) => c.canvas.isConnected && c.data.datasets.length === 1));
const won = (await charts()).find((c) => c.series.length === 1);
check(won?.series[0] === 'Won', `the stage filter reloads the chart (${won?.series.join(', ')})`);
check(await noReload(page), 'sidebar link and filter without a page load');

console.log('light / dark');
const light = (await charts()).at(-1).tick;
await page.evaluate(() => { document.documentElement.dataset.scheme = document.documentElement.dataset.scheme === 'dark' ? 'light' : 'dark'; });
await page.waitForTimeout(600);
const dark = (await charts()).at(-1);
check(dark.tick !== light, `axis colours follow the scheme (${light} → ${dark.tick})`);
// The bars change colour with Chart.js's animation.
check(await page.waitForFunction(() => {
  const c = Object.values(window.Chart.instances).find((x) => x.canvas.isConnected);
  return c.getDatasetMeta(0).data[0]?.options.backgroundColor === c.data.datasets[0].backgroundColor;
}, null, { timeout: 3000 }).then(() => true, () => false), 'bars repainted in the new colours');

console.log('design system');
await page.goto('/apps/design-system');
await page.waitForFunction(() => Object.keys(window.Chart?.instances ?? {}).length >= 4, null, { timeout: 10000 });
check(await page.locator('#charts canvas, [id^="charts-"] canvas').count() >= 4, 'chart examples drawn');

check(errors.length === 0, `no page errors (${errors.join(' | ')})`);
await b.close();
done();
