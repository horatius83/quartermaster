<?php
// Where the test databases go: /dev/shm (memory-backed) when there is one, else the system temp folder.
// On a disk, SQLite waits for every write to reach it, and on WSL's virtual disk some of those waits take
// a second or more, enough to make tests time out. The test databases are rebuilt for every test anyway.

// Each checkout gets its own folder in there, so two (say, git worktrees) can run their suites at once without
// resetting each other's database mid-test.

$base = is_dir('/dev/shm') && is_writable('/dev/shm') ? '/dev/shm' : sys_get_temp_dir();
$dir = $base . '/quartermaster-e2e-' . substr(md5(dirname(__DIR__, 2)), 0, 10);
if (!is_dir($dir)) {
    @mkdir($dir, 0700, true);
}
return $dir;
