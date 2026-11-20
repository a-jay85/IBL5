---
description: Per-mutant DB test setup for the Infection mutation workflows: config copy, DB_* keys, per-thread databases, the mutation-unsafe group.
last_verified: 2026-11-20
paths: ".github/workflows/mutation*.yml"
no-adr: lazy companion holding text moved from ci-gotchas.md; no new decision surface
---

# CI gotchas: mutation testing

## Per-mutant DB tests

Infection builds each per-mutant config from `<phpUnit.configDir>/phpunit.xml` and ignores `--configuration` in `testFrameworkOptions`. Both Infection steps therefore run `cp phpunit-mutation.xml phpunit.xml` first, so mutant runs execute the `database` group and skip `discord-live` and `mutation-unsafe`; `ibl5/bin/check-xml-class-refs` fails if those excludes go missing or if `database` gets excluded. The steps also set the four `DB_*` keys to the `setup-php-env` defaults. Without them every DB test fails on a missing env var and Infection counts that failure as a kill. Infection runs 4 threads and exports `TEST_TOKEN=1..4`; the `Clone per-thread test databases` step copies the seeded `ibl5` into `ibl5_t1` to `ibl5_t4`, and `INFECTION_PER_THREAD_DATABASE: '1'` makes `ibl5/tests/DatabaseIntegration/DatabaseNameResolver.php` pick `ibl5_t<token>`. The step's `DB_NAME` stays `ibl5` so D1 passes. One thread reruns each test once per mutant in one database, so a test that commits must carry `#[Group('mutation-unsafe')]`: `DatabaseTestCase` fails any untagged test that ends with no open transaction, and `MutationUnsafeScannerTest` fails any untagged test that commits or opens its own connection. Check M6 in `bin/check-composite-contracts` pins the copy, flag, clone step and no `--threads`.
