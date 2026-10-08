---
description: Utility classes for formatting Lighthouse performance audit results into CI/PR reports.
last_verified: 2026-10-06
---

# Cli

Provides utilities for formatting Lighthouse performance audit results in CI. `LighthouseThresholds` defines pass/warn/error score thresholds per category (performance, accessibility, best-practices). `LighthouseAuditReportFormatter` formats results into GitHub PR comment titles and bodies. `LighthouseUrls` supports URL selection. These classes have no module entry point and are used by CI scripts only.
