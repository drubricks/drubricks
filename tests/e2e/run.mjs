// Runs every e2e check file given on the command line (default: all).
import { readdirSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const dir = dirname(fileURLToPath(import.meta.url));
const files = process.argv.slice(2).length ? process.argv.slice(2)
  : readdirSync(dir).filter((f) => f.endsWith('.mjs') && !['lib.mjs', 'run.mjs'].includes(f));
let failed = 0;
for (const f of files) {
  console.log(`\n# ${f}`);
  const r = spawnSync('node', [join(dir, f.replace(/^tests\/e2e\//, ''))], { stdio: 'inherit' });
  if (r.status) failed++;
}
process.exit(failed ? 1 : 0);
