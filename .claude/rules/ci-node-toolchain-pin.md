---
description: Any CI step that compiles or bundles TypeScript/JS must be preceded by an explicit actions/setup-node pin; relying on the runner image's default Node is an unpinned dependency that no lockfile covers.
last_verified: 2026-09-04
paths: ".github/workflows/*.yml"
---

# CI Node Toolchain Pin Rule

## Problem

A GitHub Actions runner image ships a default Node.js version that changes on the runner's
own release cadence — not on yours. `package-lock.json` pins every npm package, but it does
not pin the Node version that resolves and executes them. A runner-image update can silently
shift the toolchain version and break a build that was never changed.

## Requirement

Every CI step that runs `npm ci`, `npm run build`, `npx tsc`, or any TypeScript/JS
compilation or bundling command must be immediately preceded by an explicit
`actions/setup-node` step that pins `node-version` to a specific version literal.

Pin the action by SHA (not by tag alone) to prevent a tag re-pointing from silently
changing behavior:

```yaml
    - name: Set up Node.js
      uses: actions/setup-node@820762786026740c76f36085b0efc47a31fe5020 # v7.0.0
      with:
        node-version: '22'
        cache: 'npm'
        cache-dependency-path: ibl5/IBLbot/package-lock.json
```

Include `cache: 'npm'` and a `cache-dependency-path` pointing to the relevant
`package-lock.json` to keep CI fast and the cache keyed to the right lockfile.

When the step is conditional (e.g. guarded by `if: steps.foo.outputs.changed == 'true'`),
carry the same `if:` on the `setup-node` step so the two always run together.

## Anti-pattern

Do not rely on the runner image's ambient Node version. Do not use `node-version: lts/*`
or `node-version: latest` — these are unpinned aliases that follow external release schedules.

## Version source of truth

The current canonical pin is `node-version: '22'`, matching the de facto CI version in
`.github/workflows/tests.yml` and `.github/workflows/npm-audit-fix.yml`. A future
`.nvmrc` committed to the IBLbot source tree could serve as the single source of truth
(via `node-version-file: ibl5/IBLbot/.nvmrc`); until that file exists, use the literal
`'22'`.
