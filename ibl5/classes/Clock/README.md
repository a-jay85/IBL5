---
description: Abstracts the current timestamp for testable time-dependent logic.
last_verified: 2026-07-24
---

# Clock

Provides a time abstraction used throughout the codebase to make time-dependent logic testable (ADR-0014). `ClockInterface` defines the contract for obtaining the current timestamp. `SystemClock` is the production implementation wrapping PHP's native time functions. Tests substitute a controlled implementation in place of `SystemClock`.
