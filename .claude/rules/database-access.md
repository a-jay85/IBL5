---
paths: "**/*Repository.php"
---

# Database Access Reference

## Local MAMP Database Connection

**Connection Details:**
- Host: `localhost` or `127.0.0.1`
- Port: `3306`
- Database: `iblhoops_ibl5`
- Socket: `/Applications/MAMP/tmp/mysql/mysql.sock`
- Credentials: See `ibl5/config.php` (`$dbuname`, `$dbpass`)

**PHP Connection (app standard):**
```php
// Via app bootstrap (standard way)
require_once 'autoloader.php';
include 'config.php';
include 'db/db.php';
// $mysqli_db and $db are now available globally
```

**Command Line Access:**
```bash
# IMPORTANT: Use MAMP's mysql client, NOT Homebrew mysql
/Applications/MAMP/Library/bin/mysql80/bin/mysql \
  --socket=/Applications/MAMP/tmp/mysql/mysql.sock \
  -u root -p'root' \
  iblhoops_ibl5
```

**Why MAMP's client?** Homebrew's mysql client has authentication plugin incompatibility with MAMP's MySQL 8.0 server. Always use `/Applications/MAMP/Library/bin/mysql80/bin/mysql`.

## Claude Code Database Queries (Auto-Approved)

```bash
# Use this wrapper script for database queries - it auto-approves without user confirmation
# Works from BOTH the repo root and ibl5/ — a symlink at bin/db-query points to ibl5/bin/db-query
./bin/db-query "SELECT * FROM ibl_plr LIMIT 5"
./bin/db-query "SELECT COUNT(*) FROM ibl_team_info"
./bin/db-query "DESCRIBE ibl_plr"
```

**db-query pitfalls:**
- **Never use `!=` in SQL queries passed via double quotes.** Bash interprets `!` as history expansion inside double quotes, mangling the query (`sh: : command not found`). Use SQL's `<>` operator instead: `./bin/db-query "SELECT * FROM t WHERE col <> ''"`.

**When to use `db-query`:** Use this script to explore the database schema, verify data after making changes, check record counts, and validate your work. This is the preferred method for Claude to query the local database since it's configured for auto-approval in the user's Claude Code settings.

## Multiple Claude Instances Protocol

Other Claude instances may be working in this directory simultaneously.

1. **Before editing a file:** Run `git status`. If the file has unstaged changes you didn't make, alert the user before proceeding.
2. **Scope discipline:** Only modify files directly related to your task. If you need to change a shared file, confirm with the user first.
3. **Before staging:** Run `git diff --name-only` and only stage files you personally modified. Never use `git add .` or `git add -A`.
4. **Testing:** Always run the full test suite, even if other instances may have partial work in progress. If another instance's in-progress changes cause failures in files you did not touch, note them but do not suppress them.
