---
description: Provides database-backed key-value caching and file-based full-page HTML caching.
last_verified: 2026-07-24
---

# Cache

Provides two complementary caching strategies. `DatabaseCache` is a generic key-value store backed by the `cache` database table, used as a decorator by services like `CachedCareerLeaderboardsRepository`. `PageCache` caches full-page HTML responses for anonymous GET requests as files under `ibl5/cache/page/`, with a default TTL of 900 seconds.

| Class | Purpose |
|-------|---------|
| `DatabaseCache` | Key-value cache backed by the `cache` DB table |
| `PageCache` | File-based full-page HTML cache for anonymous GET requests |
