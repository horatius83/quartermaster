// npm run test:docker [-- <playwright args>]
// Builds tests/e2e/Dockerfile for the installed Playwright version, then runs the suite inside it
// with the project mounted at /app. Extra arguments go to `playwright test`.
import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const { version } = JSON.parse(readFileSync(path.join(root, 'node_modules/@playwright/test/package.json'), 'utf8'));
const image = `quartermaster-e2e:${version}`;

function docker(args) {
  const result = spawnSync('docker', args, { cwd: root, stdio: 'inherit' });
  if (result.error) {
    console.error(`Could not run docker: ${result.error.message}`);
    process.exit(1);
  }
  if (result.status !== 0) process.exit(result.status ?? 1);
}

docker(['build', '--quiet', '--build-arg', `PLAYWRIGHT_VERSION=${version}`, '-t', image, 'tests/e2e']);

// On Linux, write test-results/ as the calling user rather than root.
const user = typeof process.getuid === 'function' ? ['--user', `${process.getuid()}:${process.getgid()}`] : [];
docker([
  'run', '--rm', '--init', '--ipc=host', ...user,
  '-e', 'CI', '-e', 'HOME=/tmp',
  '-v', `${root}:/app`, '-w', '/app',
  image, 'node', 'node_modules/@playwright/test/cli.js', 'test', ...process.argv.slice(2),
]);
