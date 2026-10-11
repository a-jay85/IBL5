---
description: ADR-0191. CI pulls Docker Hub images only through a GHCR mirror (ghcr.io/a-jay85/mirror), kept fresh by mirror-ci-images.yml and enforced by bin/check-ci-image-registry.
last_verified: 2026-10-10
---

# ADR-0191: Mirror CI's Docker Hub images to GHCR

**Status:** Accepted
**Date:** 2026-10-10

## Context

On 2026-10-09 around 14:35 PDT a wave of CI runs failed in "Initialize containers" because pulls of `mariadb:10.11` from `registry-1.docker.io` timed out. No code was at fault. CI pulled three Docker Hub images: `mariadb:10.11` as a service container in six workflows, in `docker-compose.ci.yml` and in `bin/test-phase-snapshot-detect`, plus `golang:1.27` and `php:8.5-apache` as `Dockerfile` base images. Every one of those pulls made a Docker Hub outage or rate limit a red CI run, including on required checks.

## Decision

`.github/ci-image-mirror.txt` lists every Docker Hub ref CI uses. `bin/ci-image-mirror` copies each one to `ghcr.io/a-jay85/mirror/<name>:<tag>` with `docker buildx imagetools create`, which keeps every platform. `.github/workflows/mirror-ci-images.yml` runs it weekly, on manual dispatch, and on master pushes that change the manifest or the tool. It is also a reusable workflow. Each consumer workflow calls it with `only-missing` from a `mirror-ready` job, and its service jobs `need` that job, so a missing or deleted mirror is recreated before the first pull. Only the mirror job holds `packages: write`. Consumers log in with a `packages: read` token, so the design works whether the packages are public or private. `Dockerfile` takes its base images from `ARG`s whose defaults stay on Docker Hub, and CI passes the mirror refs as build-args. `bin/check-ci-image-registry` runs in `tests.yml` and fails a PR that adds a Docker Hub ref to the CI surface, a mirror service job without `mirror-ready` or `credentials:`, or a caller of a mirror-dependent reusable workflow without `packages: write`.

## Alternatives Considered

- Docker Hub authentication with a stored token raises the rate limit but keeps Docker Hub on the critical path, and it would not have prevented the 2026-10-09 timeouts.
- A `bin/ci-image-mirror --only-missing` step inside each step-based consumer would need `packages: write` on every E2E and harness job. A `needs: mirror-ready` dependency keeps write on one job.
- Extending `bin/check-registry-trigger-rows` was rejected because that gate checks the retrospective class registry. It has no Docker surface to extend.

## Consequences

- Adding a Docker Hub image to CI takes one manifest line plus the `ghcr.io/a-jay85/mirror/` ref. The gate's failure message says so.
- A tag bump edits the manifest and every consumer ref in one PR. The PR's own `mirror-ready` jobs create the new tag before its jobs pull it.
- Mirrored tags are refreshed weekly, so a Docker Hub re-push of the same tag (a security rebuild) reaches CI within a week.
- A fork PR that adds a manifest ref fails `mirror-ready`, since its token cannot write packages. A maintainer re-runs it from a branch.
- Local development still pulls from Docker Hub. `docker-compose.yml` and `docker/worktree-compose.yml` are unchanged.

## References

- `.github/ci-image-mirror.txt`
- `bin/ci-image-mirror`
- `bin/test-ci-image-mirror`
- `.github/workflows/mirror-ci-images.yml`
- `bin/check-ci-image-registry`
- `bin/test-check-ci-image-registry`
- `.github/workflows/cache-dependencies.yml` (prior GHCR publish pattern)
