---
description: Always use composer scripts for PHPStan — never call vendor/bin/phpstan directly
last_verified: 2026-05-24
---

# PHPStan Invocation

**Never call `vendor/bin/phpstan` directly.** Always use the composer scripts:

```bash
composer run analyse              # production code
composer run analyse:tests        # test code
composer run analyse:baseline     # regenerate production baseline
composer run analyse:tests:baseline  # regenerate test baseline
```

These scripts set `--memory-limit=1G` and `--autoload-file` for the custom PHPStan rules bootstrap. Direct `vendor/bin/phpstan` calls skip both, causing OOM failures and missing custom rules.

If you need extra flags (e.g. `--no-progress`), append them via composer's `--` separator:

```bash
composer run analyse -- --no-progress
```
