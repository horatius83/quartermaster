import { execFileSync, spawnSync } from 'node:child_process';
import path from 'node:path';

export const ROOT = path.resolve(__dirname, '../..');
/** The test server's port; QM_E2E_PORT moves it, e.g. to run beside a server another checkout left running. */
export const PORT = Number(process.env.QM_E2E_PORT) || 8099;
export const BASE_URL = `http://127.0.0.1:${PORT}`;
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

/**
 * Which worker's database PHP scripts use (tests/e2e/test-db.php): Playwright numbers its workers from 0 in
 * TEST_PARALLEL_INDEX, and fixtures.ts sends the same number from the browser.
 */
export const testDb = (): string => process.env.TEST_PARALLEL_INDEX ?? '0';

/** WSLENV carries these variables into WSL (QM_CONFIG as a translated path). */
const WSLENV = [process.env.WSLENV, 'QM_CONFIG/p', 'QM_TEST_DB', 'QM_E2E_PORT', 'PHP_CLI_SERVER_WORKERS'].filter(Boolean).join(':');

/** Points the app at tests/e2e/config.php and this worker's database (test-db.php names it by port and worker). */
export const phpEnv = (which = testDb()): Record<string, string> => ({ QM_CONFIG: CONFIG, QM_TEST_DB: which, QM_E2E_PORT: String(PORT), WSLENV });

/** For the upgrade checks, which pick their own config but still need this worker's database. */
const upgradeEnv = () => ({ ...process.env, QM_TEST_DB: testDb(), QM_E2E_PORT: String(PORT), WSLENV });

/** Seed the template database that resets copy (tests/e2e/router.php). global-setup.ts runs this once per run. */
export function seedTemplate(): void {
  const [cmd, ...args] = phpCommand();
  execFileSync(cmd, [...args, 'tests/e2e/reset.php'], { cwd: ROOT, env: { ...process.env, ...phpEnv('template') }, stdio: 'pipe' });
}

/** Put this worker's database back to the demo seed, or leave it empty for a fresh install. */
export async function resetDatabase({ empty = false } = {}): Promise<void> {
  const response = await fetch(`${BASE_URL}/__test/reset${empty ? '?empty=1' : ''}`, { method: 'POST', headers: { 'X-QM-Test-Db': testDb() } });
  if (!response.ok) throw new Error(`Resetting the test database failed (${response.status}): ${await response.text()}`);
}

/** Make a seeded campaign look like one from before systems, whose system can still be set once. */
export function openCampaignSystem(name: string): void {
  const [cmd, ...args] = phpCommand();
  execFileSync(cmd, [...args, 'tests/e2e/open-campaign-system.php', name], { cwd: ROOT, env: { ...process.env, ...phpEnv() }, stdio: 'pipe' });
}

/** After an empty reset: an upgraded database with a campaign but no DM yet, whose character holds a 5e item. */
export function makeOwnerlessCampaign(): void {
  const [cmd, ...args] = phpCommand();
  execFileSync(cmd, [...args, 'tests/e2e/ownerless-campaign.php'], { cwd: ROOT, env: { ...process.env, ...phpEnv() }, stdio: 'pipe' });
}

/** Run tests/e2e/session-check.php: who current_user() sees for a hand-made DM session, and what's left in it. */
export function checkSession(kind: 'legacy' | 'current' | 'deleted'): { user: string | null; sessionKeys: string[] } {
  const [cmd, ...args] = phpCommand();
  const out = execFileSync(cmd, [...args, 'tests/e2e/session-check.php', kind], { cwd: ROOT, env: { ...process.env, ...phpEnv() }, stdio: 'pipe' });
  return JSON.parse(out.toString());
}

/** Run tests/e2e/srd-data-check.php: whether every bundled SRD row is one the catalog accepts. */
export function checkSrdData(): { problems: string[] } {
  const [cmd, ...args] = phpCommand();
  const out = execFileSync(cmd, [...args, 'tests/e2e/srd-data-check.php'], { cwd: ROOT, env: { ...process.env, ...phpEnv() }, stdio: 'pipe' });
  return JSON.parse(out.toString());
}

/** Run tests/e2e/upgrade-rulesets-check.php and return what upgrade 006 left behind. */
export function upgradeToRulesets(): unknown {
  const [cmd, ...args] = phpCommand();
  const out = execFileSync(cmd, [...args, 'tests/e2e/upgrade-rulesets-check.php'], { cwd: ROOT, env: upgradeEnv(), stdio: 'pipe' });
  return JSON.parse(out.toString());
}

/** Run tests/e2e/upgrade-check.php and return what it reports about the upgraded database. */
export function upgradeOldDatabase(statementsAlreadyRun = 0, { oddRoleCheck = false } = {}): {
  campaigns: { id: number; name: string; owner: number }[];
  admins: number;
  userLog: number;
  sessionVersions: number[];
  characters: number[];
  stores: number[];
  log: number[];
} {
  const [cmd, ...args] = phpCommand();
  const out = execFileSync(cmd, [...args, 'tests/e2e/upgrade-check.php', String(statementsAlreadyRun), ...(oddRoleCheck ? ['--odd-role-check'] : [])], { cwd: ROOT, env: upgradeEnv(), stdio: 'pipe' });
  return JSON.parse(out.toString());
}
