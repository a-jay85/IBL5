---
description: Commissioner runbook for replacing IBL5.plr and IBL5.plb with the site export, plus the JSB format findings behind it.
last_verified: 2026-11-16
---

# JSB Export Runbook

The site export writes `IBL5_export.plr` and `IBL5_export.plb`. Both files carry the GMs' depth charts and the trades the site recorded. The commissioner copies them over `IBL5.plr` and `IBL5.plb` in the JSB league folder. JSB then loads the charts directly, so nobody enters trades in JSB or runs Game Plan > Import All.

Format background lives in `ibl5/docs/JSB_FILE_FORMATS.md` and `ibl5/classes/JsbParser/README.md`.

## Research findings (2026-11-16)

I measured these on the real saves `08-09_19_reg-sim08.zip` (before the Grizzlies-Lakers trade) and `08-09_20_reg-sim09.zip` (after JSB applied it). The VM checks for F7 and F8 did not run in this pass. Each of those keeps the plan's default and says so.

- **F1.** The .plr file is CRLF-separated. Line 0 is a 607-char header, and the record for ordinal N sits at line index N. Ordinals 1-1440 are player slots, followed by 164 other lines and a trailing empty segment. Every empty slot is a full 607-char line whose pid field (offset 38, width 6) trims to 0. Empty lines are not all identical: sim08 has 21 distinct empty-line bodies after the ordinal field, though neighbours in one team block match (583 and 584 are identical). The relocator finds lines by reading the ordinal field and treats pid 0 as empty.
- **F2.** JSB does not leave a hole when a player leaves. It re-packs the whole team block. After the trade Marcus Camby moved 579 to 580, Ricky Davis 610 to 605, Bruno Fernando 611 to 609, and Gani Lawal 613 to 612. No block in either save has an empty slot before a filled slot. So sim09 has no vacated slot to compare against, and the slots 582, 605, 608 hold shifted players. The export keeps the plan's swap rule: a vacated slot takes the target empty line's bytes with the vacated ordinal. This leaves holes JSB itself never writes, and the VM acceptance run must show JSB accepts them.
- **F3.** For Turner, Martin, Kagami and Pittman, JSB set teamid, contractOwnedBy and currentTeamIndex (new tid minus 1) for the new team. previousTeamIndex kept its sim08 value (Turner 20, Kagami 19). Each mover's sim08 previousTeamIndex already equalled old tid minus 1, so keeping the old value and writing old tid minus 1 give the same bytes. The sample cannot separate the two rules (undetermined, default kept: `PRESERVE_PREVIOUS_TEAM_INDEX = true`).
- **F4.** The .plb file is exactly 160,000 bytes. It has 32 team lines of 360 chars, then 33 empty CRLF lines, then a final segment of 148,350 spaces with no trailing CRLF. Line index t holds team t+1, and slot k maps to ordinal t*30+k+1. Values are space-padded (`%2d`), so a 1 reads as a space and a 1. Slot values travel with the player: Turner 40 minutes at Lakers slot 7 then Grizzlies slot 8, Kagami 20, Pittman 1. In sim09 team-line 19 slot 11 (ordinal 582) holds Tremont Waters' value of 1, carried from his old slot 581. Team-line 20 slots 4 and 7 (605, 608) hold the shifted Davis and Biedrins values. The writer touches only line indices 0-31.
- **F5.** The rule "ascending source ordinal, first pid-0 slot of the target block, vacate at once" puts Martin at 575 (matches JSB), Turner at 582 (JSB: 579), Kagami at 614 (JSB: 611), and Pittman at 615 (JSB: 613). JSB re-sorts the block, so no rule that moves only the traded players can match sim09 exactly. The default stays. The real-save test asserts each block's pid set, the movers' team fields, and the unmoved players' ordinals.
- **F6.** No repo code writes `ibl5/IBL5.plr` (example) or `ibl5/IBL5.plb` (example) per sim. `ibl5/classes/Updater/JsbSourceResolver.php` reads `IBL5.<ext>` from the newest `backups/<season>/` archive and falls back to the disk file. The export page sources both inputs through it.
- **F7.** The VM trade run did not happen in this pass (undetermined, default kept). Between the sim08 and sim09 archives these files changed: IBL5.bud, .bxs, .log, .own, .plb, .plr, .rcb, .sch, .sco, .trn. That list mixes the trade with a full sim, so it is context only. The export writes only .plr and .plb, and JSB keeps every other file.
- **F8.** The VM check of whether JSB reads IBL5.trn on load did not happen in this pass (undetermined). The runbook keeps JSB's own IBL5.trn either way, and the site's `IBL5_export.trn` stays a site-side log.
