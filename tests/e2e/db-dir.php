<?php
// Where the test databases go: /dev/shm (memory-backed) when there is one, else the system temp folder.
// On a disk, SQLite waits for every write to reach it, and on WSL's virtual disk some of those waits take
// a second or more, enough to make tests time out. The test databases are rebuilt for every test anyway.

return is_dir('/dev/shm') && is_writable('/dev/shm') ? '/dev/shm' : sys_get_temp_dir();
