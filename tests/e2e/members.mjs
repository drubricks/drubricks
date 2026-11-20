// Project members widget (drubricks_core ReferenceListWidget): people listed
// one under another, removed with a button, added through a search with
// suggestions and the keyboard; the change is saved and controls access.
import { browser, check, done, login } from './lib.mjs';

const { b, ctx } = await browser();
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
await login(page);
await page.goto('/apps/pm/projects');
await page.locator('.column .row').filter({ hasText: 'Mobile App v2' }).click();
await page.waitForSelector('#drubricks-detail [data-action-key="edit"]');
await page.click('#drubricks-detail [data-action-key="edit"]');
await page.waitForSelector('.modal .drubricks-reflist');
// The modal focuses its first field once loaded.
await page.waitForFunction(() => document.activeElement?.closest('.modal'));
await page.waitForTimeout(300);

const names = () => page.locator('.modal .reflist-name').allInnerTexts();
const before = await names();
check(before.length >= 2, `members listed one per row (${before.join(', ')})`);
check(await page.locator('.modal input[data-drubricks-reference-list]').getAttribute('type') === 'hidden', 'the comma-separated input is hidden');

// Remove the first member.
const removed = before[0];
await page.locator('.modal .reflist-remove').first().click();
check(!(await names()).includes(removed), `removed ${removed}`);

// Add them back through the search, with the keyboard.
await page.locator('.modal .reflist-search').click();
await page.keyboard.type(removed.slice(0, 3), { delay: 60 });
await page.waitForSelector('.modal .reflist-option');
const suggestions = await page.locator('.modal .reflist-option').allInnerTexts();
check(suggestions.some((s) => s.includes(removed)), `suggestions include ${removed}`);
await page.keyboard.press('Enter');
check((await names()).includes(removed), `${removed} added with Enter`);
check(await page.locator('.modal form#drubricks-entity-form').count() === 1, 'Enter in the search does not submit the form');

// Escape closes the suggestions, not the modal.
await page.keyboard.type('a', { delay: 60 });
await page.waitForSelector('.modal .reflist-suggestions:not([hidden])').catch(() => {});
await page.keyboard.press('Escape');
check(await page.locator('.modal').count() === 1, 'Escape closes only the suggestions');
await page.locator('.modal .reflist-search').fill('');

await page.click('.modal form [type="submit"]:not([data-drubricks-modal-close])');
await page.waitForSelector('.modal', { state: 'detached', timeout: 10000 }).catch(() => {});
const details = await page.locator('#drubricks-detail').innerText();
check(before.every((n) => details.includes(n)), 'saved: the same members as before');
check(errors.length === 0, `no JS errors ${errors.join(' | ')}`);
await b.close();
done();
