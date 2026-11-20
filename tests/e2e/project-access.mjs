// Project membership as a normal user: the projects list, the task list,
// the task board and the sidebar counters show only the user's projects;
// another project's page is denied; the project field offers only them.
import { execSync } from 'node:child_process';
import { browser, check, done } from './lib.mjs';

const php = (code) => JSON.parse(execSync(`ddev drush php:eval '${code}'`, { encoding: 'utf8' }).trim().split('\n').pop());
// A demo user who is in some projects but not all.
const info = php(`
  $s = \\Drupal::entityTypeManager()->getStorage("pm_project");
  $all = $s->loadMultiple();
  $best = NULL;
  foreach (\\Drupal::entityTypeManager()->getStorage("user")->loadByProperties(["status" => 1]) as $u) {
    if ($u->id() == 1 || $u->hasPermission("administer project management")) continue;
    $mine = array_filter($all, fn ($p) => in_array((int) $u->id(), array_map("intval", array_column($p->get("members")->getValue(), "target_id")), TRUE) || $p->getOwnerId() == $u->id());
    if ($mine && count($mine) < count($all)) { $best = [$u, $mine]; break; }
  }
  [$u, $mine] = $best;
  $other = array_values(array_diff_key($all, $mine))[0];
  $tasks = \\Drupal::entityQuery("pm_task")->accessCheck(FALSE)->condition("project", array_keys($mine), "IN")->count()->execute();
  echo json_encode(["name" => $u->getAccountName(), "projects" => array_values(array_map(fn ($p) => $p->label(), $mine)), "other" => $other->id(), "otherName" => $other->label(), "tasks" => (int) $tasks]);
`);
console.log(`as ${info.name}: projects ${info.projects.join(', ')}; not in ${info.otherName}`);

const { b, ctx } = await browser();
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
const link = execSync(`ddev drush uli --name="${info.name}"`, { encoding: 'utf8' }).trim().split('\n').pop();
await page.goto(new URL(link).pathname);
await page.goto('/apps/pm/projects');

const rows = (await page.locator('.column .row .row-title').allInnerTexts()).sort();
check(JSON.stringify(rows) === JSON.stringify([...info.projects].sort()), `projects list = memberships (${rows.join(', ')})`);
const projectCount = Number(await page.locator('.sb-item:has-text("Projects") .count').innerText());
check(projectCount === info.projects.length, `sidebar counter ${projectCount}`);

await page.goto('/apps/pm/tasks');
const taskRows = await page.locator('.column .row').count();
check(taskRows === info.tasks, `task list shows only their projects' tasks (${taskRows} of ${info.tasks})`);

await page.goto('/apps/pm/board');
const boardProjects = [...new Set(await page.locator('[data-drubricks-board-island] .bc-sub').allInnerTexts())];
check(boardProjects.every((p) => info.projects.includes(p)), `board shows only their projects (${boardProjects.join(', ')})`);

const denied = await page.goto(`/apps/pm/projects/${info.other}`);
check(denied.status() === 403, `another project's page is denied (${denied.status()})`);

await page.goto('/apps/pm/tasks/add');
const options = (await page.locator('select[name="project"] option:not([value="_none"])').allInnerTexts()).sort();
check(JSON.stringify(options) === JSON.stringify([...info.projects].sort()), `project field offers only their projects (${options.join(', ')})`);

check(errors.length === 0, `no JS errors ${errors.join(' | ')}`);
await b.close();
done();
