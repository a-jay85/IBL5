---
paths:
  - "**/*Repository.php"
  - "**/schema.sql"
  - "**/migrations/**"
  - "**/db/**"
  - "**/seed*.php"
  - "**/seed*.sql"
---

# Database Access Reference

## Local Docker MariaDB Connection

**Connection Details:**
- Host: `127.0.0.1`
- Port: `3306`
- Database: `iblhoops_ibl5`
- Credentials: See `ibl5/config.php` (`$dbuname`, `$dbpass`)

**Start the database:**
```bash
docker compose up -d   # from repo root
```

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
mariadb -h 127.0.0.1 --skip-ssl -u root -proot iblhoops_ibl5
```

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

## MariaDB Strict Mode & Triggers

- **NOT NULL columns without DEFAULT reject INSERTs before BEFORE INSERT triggers fire.** If a column is `NOT NULL` with no `DEFAULT`, MariaDB strict mode (enabled by default since 10.2) throws `Field 'x' doesn't have a default value` *before* any BEFORE INSERT trigger can auto-populate it. Always provide explicit values for NOT NULL columns in INSERT statements — don't rely on triggers to save you. Example: `ibl_plr.uuid` and `ibl_team_info.uuid` must be included in INSERTs even though BEFORE INSERT triggers exist.
- **DEFINER clauses in schema.sql break CI imports.** The production `schema.sql` dump contains `DEFINER=\`iblhoops_chibul\`@\`71.145.211.164\`` on triggers and views. Strip them with `sed 's/DEFINER=\`[^\`]*\`@\`[^\`]*\`//g'` before importing into non-production databases.

## Multiple Claude Instances Protocol

Other Claude instances may be working in this directory simultaneously.

1. **Before editing a file:** Run `git status`. If the file has unstaged changes you didn't make, alert the user before proceeding.
2. **Scope discipline:** Only modify files directly related to your task. If you need to change a shared file, confirm with the user first.
3. **Before staging:** Run `git diff --name-only` and only stage files you personally modified. Never use `git add .` or `git add -A`.
4. **Testing:** Always run the full test suite, even if other instances may have partial work in progress. If another instance's in-progress changes cause failures in files you did not touch, note them but do not suppress them.
