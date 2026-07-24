---
description: Utility classes for formatting Lighthouse performance audit results into CI/PR reports.
last_verified: 2026-07-24
---

# Cli

Provides utilities for formatting Lighthouse performance audit results in CI. `LighthouseThresholds` defines pass/warn/error score thresholds per category (performance, accessibility, best-practices). `LighthouseAuditReportFormatter` formats results into GitHub PR comment titles and bodies. `LighthouseCommentFormatter` and `LighthouseUrls` support URL selection and comment structure. These classes have no module entry point and are used by CI scripts only.
