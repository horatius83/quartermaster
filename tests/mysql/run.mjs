// npm run test:mysql
// Runs tests/mysql/check.php against throwaway MySQL and MariaDB containers, removing them afterwards.
// Needs only Docker. The first run downloads the images.
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const NETWORK = 'qm-mysql-check';
const DB = 'qm-mysql-check-db'; // the host name in tests/mysql/config.php
const PHP_IMAGE = 'php:8.3-cli';
const DATABASES = ['mysql:8.4', 'mariadb:11.4'];

const docker = (args, stdio = 'pipe') => spawnSync('docker', args, { cwd: root, stdio, encoding: 'utf8' });
const cleanUp = () => {
  docker(['rm', '-f', DB]);
  docker(['network', 'rm', NETWORK]);
};

function check(image) {
  console.log(`\n=== ${image} ===`);
  cleanUp();
  docker(['network', 'create', NETWORK]);
  const started = docker(['run', '-d', '--name', DB, '--network', NETWORK,
    '-e', 'MYSQL_RANDOM_ROOT_PASSWORD=1', '-e', 'MARIADB_RANDOM_ROOT_PASSWORD=1',
    '-e', 'MYSQL_DATABASE=quartermaster', '-e', 'MYSQL_USER=quartermaster', '-e', 'MYSQL_PASSWORD=throwaway',
    image]);
  if (started.status !== 0) {
    console.error(started.stderr);
    return false;
  }
  // Install the PDO driver, wait for the database to take connections, then run the checks.
  const script = `
    docker-php-ext-install pdo_mysql > /dev/null 2>&1 || exit 1
    tries=0
    until php -r 'new PDO("mysql:host=${DB};dbname=quartermaster", "quartermaster", "throwaway");' > /dev/null 2>&1; do
      tries=$((tries + 1))
      [ $tries -ge 90 ] && { echo "The database never came up."; exit 1; }
      sleep 2
    done
    php tests/mysql/check.php`;
  const run = docker(['run', '--rm', '--network', NETWORK, '-v', `${root}:/app`, '-w', '/app', PHP_IMAGE, 'sh', '-c', script], 'inherit');
  return run.status === 0;
}

let failed = false;
try {
  for (const image of DATABASES) {
    if (!check(image)) failed = true;
  }
} finally {
  cleanUp();
}
console.log(failed ? '\nFailed.' : '\nAll passed.');
process.exit(failed ? 1 : 0);
