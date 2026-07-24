---
description: Admin page for viewing and updating league configuration, including JSB .lge file import.
last_verified: 2026-07-24
---

# LeagueConfig

Admin module for viewing and updating league-wide configuration settings. `LgeFileParser` parses JSB `.lge` configuration files and maps their values into league settings stored in the database. Follows the Repository/Service/View pattern with the parser as an additional file-ingestion layer.
