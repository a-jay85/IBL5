---
description: Abstracts the current timestamp for testable time-dependent logic.
last_verified: 2026-10-09
---

# Clock

Provides a time abstraction used throughout the codebase to make time-dependent logic testable (ADR-0014). `ClockInterface` defines the contract for obtaining the current time. `SystemClock` is the production implementation wrapping PHP's native time functions. Tests substitute a controlled implementation in place of `SystemClock`.

## Interface

- `now(): int` returns wall-clock Unix seconds.
- `microtime(): float` returns Unix time with sub-second precision. Use it for durations such as page-render timing.

`SystemClock::now()` returns `time()` and `SystemClock::microtime()` returns `microtime(true)`.

## Test double

`Tests\Clock\FixedClock` (`ibl5/tests/Clock/FixedClock.php`) takes the starting seconds as its first constructor argument. The optional second argument `?float $micro` pins the value `microtime()` returns. When it is omitted, `microtime()` returns `(float) now()`, so it follows `setNow()` and `advance()`.

## Injection

- Instance classes take an optional trailing constructor parameter of type `ClockInterface` and fall back to `SystemClock`.
- Static classes expose `setTestClock(?ClockInterface $clock)`, for example `PageLayout::setTestClock()`. `CsrfGuard` uses the same pattern. Passing `null` restores the default clock.
