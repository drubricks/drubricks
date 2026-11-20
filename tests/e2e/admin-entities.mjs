// Admin views of the app entities (/admin/app → Entities): every example
// module links its views; filters, bulk operations (activate / deactivate,
// delete with delete blockers) and edit / delete operations that come back to
// the view. Works on its own "E2E …" records, created and removed here.
import { execFileSync } from 'node:child_process';
import { browser, check, done, login } from './lib.mjs';

// Runs PHP on the site with drush php:eval (no shell: $variables stay as is).
const php = (code) => execFileSync('ddev', ['drush', 'php:eval', code.replace(/\s*\n\s*/g, ' ')], { encoding: 'utf8' }).trim();
const cleanup = () => php(`
  foreach (["crm_contact", "crm_company"] as $t) {
    $s = \\Drupal::entityTypeManager()->getStorage($t);
    $s->delete($s->loadMultiple($s->getQuery()->accessCheck(FALSE)->condition("name", "E2E ", "STARTS_WITH")->execute()));
  }`);
cleanup();
const ids = JSON.parse(php(`
  $free = \\Drupal\\drubricks_examples_crm\\Entity\\Company::create(["name" => "E2E Free Co", "uid" => 1]); $free->save();
  $busy = \\Drupal\\drubricks_examples_crm\\Entity\\Company::create(["name" => "E2E Busy Co", "uid" => 1]); $busy->save();
  $c = \\Drupal\\drubricks_examples_crm\\Entity\\Contact::create(["name" => "E2E Person", "company" => $busy->id(), "stage" => "lead", "uid" => 1]); $c->save();
  echo json_encode(["free" => $free->id(), "busy" => $busy->id(), "contact" => $c->id()]);`));
const field = (type, id, name) => php(`echo \\Drupal::entityTypeManager()->getStorage("${type}")->load(${id})?->get("${name}")->value ?? "gone";`);

const { b, ctx } = await browser();
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
await login(page);

// /admin/app: "Entity records" (a view per entity type) and "Entity
// structure" (its Field UI admin page) as separate sections.
await page.goto('/admin/app');
const links = ['Companies', 'Contacts', 'Deals', 'Projects', 'Tasks', 'Resources', 'Bookings'];
for (const [section, prefix] of [['Entity records', '/admin/app/entities/'], ['Entity structure', '/admin/app/structure/']]) {
  const panel = page.locator('.panel, .admin-panel').filter({ hasText: section }).first();
  for (const title of links) {
    const link = panel.getByRole('link', { name: new RegExp(`^${title}`) }).first();
    check(((await link.getAttribute('href')) || '').startsWith(prefix), `/admin/app lists ${title} under ${section}`);
  }
}

// Every view renders rows, exposed filters and the bulk form.
const views = ['crm/companies', 'crm/contacts', 'crm/deals', 'pm/projects', 'pm/tasks', 'booking/resources', 'booking/bookings'];
for (const path of views) {
  const res = await page.goto(`/admin/app/entities/${path}`);
  const rows = await page.locator('table tbody tr').count();
  check(res.status() === 200 && rows > 0, `${path}: 200, ${rows} rows`);
  check(await page.locator('form.views-exposed-form input[type="submit"]').count() > 0, `${path}: exposed filters`);
  check(await page.locator('select[name="action"]').count() === 1, `${path}: bulk operations`);
}

// Filters: a list field (select) and the name.
await page.goto('/admin/app/entities/crm/contacts?stage=lead');
const stages = await page.locator('table tbody tr td.views-field-stage').allInnerTexts();
check(stages.length > 0 && stages.every((s) => s.trim() === 'Lead'), `stage filter: only leads (${stages.length})`);
await page.goto('/admin/app/entities/crm/contacts?name=E2E');
check(await page.locator('table tbody tr').count() === 1, 'name filter: one E2E contact');

// Bulk: deactivate, then activate again.
const bulk = async (url, action, names) => {
  await page.goto(url);
  for (const name of names) {
    await page.locator('table tbody tr').filter({ hasText: name }).locator('input[type="checkbox"]').check();
  }
  await page.selectOption('select[name="action"]', { label: action });
  await Promise.all([page.waitForNavigation(), page.locator('[data-drupal-selector="edit-actions-submit"], .views-bulk-actions input[type="submit"]').first().click()]);
};
await bulk('/admin/app/entities/crm/contacts?name=E2E', 'Deactivate contacts', ['E2E Person']);
check(field('crm_contact', ids.contact, 'status') === '0', 'bulk Deactivate: contact inactive');
check(await page.getByText('was applied to 1 item').count() > 0, 'bulk Deactivate: message');
await bulk('/admin/app/entities/crm/contacts?name=E2E', 'Activate contacts', ['E2E Person']);
check(field('crm_contact', ids.contact, 'status') === '1', 'bulk Activate: contact active again');

// Bulk delete: the company with a contact is not deletable ("delete" access
// forbidden), so core's bulk form skips it; the other one is deleted.
await bulk('/admin/app/entities/crm/companies?name=E2E', 'Delete companies', ['E2E Free Co', 'E2E Busy Co']);
check(page.url().includes('/admin/app/entities/crm/companies/delete'), 'bulk Delete: confirmation page');
const confirmText = await page.locator('main, body').first().innerText();
check(/No access to execute Delete companies on the Company E2E Busy Co/.test(confirmText), 'the blocked company is skipped (core message)');
check(confirmText.includes('E2E Free Co'), 'the free company is to be deleted');
await Promise.all([page.waitForNavigation(), page.click('#edit-submit')]);
check(new URL(page.url()).pathname === '/admin/app/entities/crm/companies', `back to the companies view (${new URL(page.url()).pathname})`);
check(field('crm_company', ids.free, 'name') === 'gone', 'free company deleted');
check(field('crm_company', ids.busy, 'name') === 'E2E Busy Co', 'company with a contact kept');
// No Delete operation on the kept company in the admin view.
await page.goto('/admin/app/entities/crm/companies?name=E2E');
check(await page.locator('table tbody tr').filter({ hasText: 'E2E Busy Co' }).locator('a[href*="/delete"]').count() === 0, 'no Delete operation for a company in use');

// Operations: Edit opens the app form and returns to the admin view.
await page.goto('/admin/app/entities/crm/contacts?name=E2E');
await Promise.all([page.waitForNavigation(), page.locator('table tbody tr').filter({ hasText: 'E2E Person' }).locator('a[href*="/edit"]').first().click()]);
check(page.url().includes(`/apps/crm/contacts/${ids.contact}/edit`) && page.url().includes('destination='), 'Edit opens the record form with a destination');
await page.fill('input[name="job_title[0][value]"]', 'E2E Tester');
await Promise.all([page.waitForURL((u) => u.pathname === '/admin/app/entities/crm/contacts'), page.click('#drubricks-entity-form [data-drupal-selector="edit-submit"]')]);
check(field('crm_contact', ids.contact, 'job_title') === 'E2E Tester', 'edit saved');
check(new URL(page.url()).pathname === '/admin/app/entities/crm/contacts', 'after Save: back in the admin view');

// Operations: Delete (the contact) returns to the view too.
await page.goto(`/apps/crm/contacts/${ids.contact}/delete?destination=/admin/app/entities/crm/contacts`);
await Promise.all([page.waitForURL((u) => u.pathname === '/admin/app/entities/crm/contacts'), page.click('#drubricks-delete-form [data-drupal-selector="edit-submit"]')]);
check(field('crm_contact', ids.contact, 'name') === 'gone', 'contact deleted');
check(new URL(page.url()).pathname === '/admin/app/entities/crm/contacts', 'after Delete: back in the admin view');

// Without the app's admin permission: no access.
const worker = php(`
  $u = user_load_by_name("e2e_worker") ?: \\Drupal\\user\\Entity\\User::create(["name" => "e2e_worker", "status" => 1]);
  $u->setPassword("e2e_worker")->save(); echo $u->id();`);
const other = await (await b.newContext({ ignoreHTTPSErrors: true, baseURL: page.url() })).newPage();
await login(other, 'e2e_worker', 'e2e_worker');
const denied = await other.goto('/admin/app/entities/crm/contacts');
check(denied.status() === 403, `a user without "administer crm" gets 403 (${denied.status()})`);
php(`\\Drupal\\user\\Entity\\User::load(${worker})?->delete();`);

check(errors.length === 0, `no JS errors ${errors.join(' | ')}`);
cleanup();
await b.close();
done();
