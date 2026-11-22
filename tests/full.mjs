// npm run test:full [-- <playwright args>]
// Everything a pull request is checked with: both browsers in Docker (tests/e2e/docker.mjs), then the MySQL and
// MariaDB checks (tests/mysql/run.mjs). Extra arguments go to Playwright. Both parts run even if the first fails,
// so one run shows every failure.
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const here = path.dirname(fileURLToPath(import.meta.url));
const run = (script, args = []) => spawnSync(process.execPath, [path.join(here, script), ...args], { stdio: 'inherit' }).status ?? 1;

const e2e = run('e2e/docker.mjs', process.argv.slice(2));
const mysql = run('mysql/run.mjs');
if (e2e !== 0) console.error('\nThe end-to-end tests failed.');
process.exit(e2e || mysql);
