import { execFileSync, spawnSync } from 'node:child_process';
import path from 'node:path';

export const ROOT = path.resolve(__dirname, '../..');
const CONFIG = path.join(__dirname, 'config.php');

let cached: string[] | undefined;

/**
 * How to run PHP: the PHP env var if set (e.g. PHP="C:\php\php.exe"), else `php` on the PATH,
 * else, on Windows, the PHP inside WSL.
 */
export function phpCommand(): string[] {
  if (cached) return cached;
  if (process.env.PHP) return (cached = [process.env.PHP]);
  if (spawnSync('php', ['-v']).status === 0) return (cached = ['php']);
  if (process.platform === 'win32' && spawnSync('wsl', ['php', '-v']).status === 0) return (cached = ['wsl', 'php']);
  throw new Error('PHP 8.1+ not found. Install it, or set PHP to the path of the php executable.');
}

/** Points the app at tests/e2e/config.php. WSLENV carries the variable (as a translated path) into WSL. */
export const phpEnv: Record<string, string> = {
  QM_CONFIG: CONFIG,
  WSLENV: [process.env.WSLENV, 'QM_CONFIG/p'].filter(Boolean).join(':'),
};

/** Wipe the test database and reload the demo seed. */
export function resetDatabase(): void {
  const [cmd, ...args] = phpCommand();
  execFileSync(cmd, [...args, 'tests/e2e/reset.php'], { cwd: ROOT, env: { ...process.env, ...phpEnv }, stdio: 'pipe' });
}
