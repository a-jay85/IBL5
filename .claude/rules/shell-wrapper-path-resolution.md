---
description: When authoring a shell script wrapper that invokes a Python module, use PYTHONPATH to grant module access instead of cd-ing to the module root — cd before argument use silently reinterprets any caller-provided relative path.
last_verified: 2026-09-04
paths:
  - "bin/**"
---

# Shell Wrapper Path Resolution

When writing a shell script wrapper that invokes a Python module (or any subcommand requiring a module-root cwd), **do not `cd` to the module root and then pass caller arguments through**. The caller's arguments resolve relative to the caller's cwd; a `cd` before using them silently reinterprets any relative path.

**Instead, grant module access via `PYTHONPATH`** (for Python) and keep the original cwd:

```sh
ROOT=$(git rev-parse --show-toplevel 2>/dev/null) || ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PYTHONPATH="$ROOT/tools/my-module${PYTHONPATH:+:$PYTHONPATH}"
export PYTHONPATH
exec python3 -m my.module "$@"
```

If the subcommand truly must run from a specific directory, resolve each positional argument to an absolute path **before** the `cd`:

```sh
# Rotate the positional list exactly once: each pass appends the resolved
# form of $1 to the end, then shifts it off the front.  Appending without a
# matching shift (or shifting only in one `case` branch) reorders and drops
# arguments once absolute and relative paths are mixed.
n=$#
while [ "$n" -gt 0 ]; do
    case "$1" in
        /*) set -- "$@" "$1" ;;
        *)  set -- "$@" "$(realpath -- "$1")" ;;
    esac
    shift
    n=$((n - 1))
done
cd "$ROOT/tools/my-module"
exec python3 -m my.module "$@"
```

**Why this matters:** relative-path arguments that work from the repo root will silently fail (or hit a different file) when the wrapper cd's first. The error is non-obvious because the script exits with a module-level FileNotFoundError, not a "wrong directory" message.

## Why

See `ibl5/docs/decisions/0118-shell-wrapper-path-resolution-via-pythonpath.md` for the rung ladder that rejected static analysis, a `bin/check-*` gate, and a forced verification row before landing on this authoring norm.
