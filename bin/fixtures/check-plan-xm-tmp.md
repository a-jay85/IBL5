---
impl_model: sonnet
auto_merge: true
---
# Fixture: gate XM tmp lint

## Verification Matrix

| # | What to verify | Test type | Timing | Test file / location |
|---|----------------|-----------|--------|----------------------|
| 1 | docs clean | CLI-executable | post-impl | `bin/check-docs` |
| 2 | docs clean | CLI-executable | post-impl | `bin/check-docs` |
| 3 | docs clean | CLI-executable | post-impl | `bin/check-docs` |

## Executable Mutations

```mutation
row: 1
file: bin/check-docs
check: bash /tmp/x
match: set -u
replace: set +u
```

```mutation
row: 2
file: bin/check-docs
check: bash bin/check-docs
match: set -u
replace: set +u
```

```mutation
row: 3
file: bin/check-docs
check: bash ibl5/tmp/x
match: set -u
replace: set +u
```
