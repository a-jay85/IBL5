---
description: Provides a PDO singleton connection for the delight-im/auth library.
last_verified: 2026-10-03
---

# Database

Contains the `PdoConnection` class, which supplies a PDO singleton to the `delight-im/auth` library. It reads database credentials from config.php globals and exposes a `reset()` method for testability. `BaseMysqliRepository.php` in this directory is the shared mysqli repository base class (`Database\BaseMysqliRepository`) and is unrelated to the PDO connection.
