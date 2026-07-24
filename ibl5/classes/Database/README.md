---
description: Provides a PDO singleton connection for the delight-im/auth library.
last_verified: 2026-07-24
---

# Database

Contains a single class, `PdoConnection`, which supplies a PDO singleton to the `delight-im/auth` library. It reads database credentials from config.php globals and exposes a `reset()` method for testability.
