---
description: Commissioner runbook for replacing IBL5.plr and IBL5.plb with the site export, plus the JSB format findings behind it.
last_verified: 2026-11-16
---

# JSB Export Runbook

The site export writes `IBL5_export.plr` and `IBL5_export.plb`. Both files carry the GMs' depth charts and the trades the site recorded. The commissioner copies them over `IBL5.plr` and `IBL5.plb` in the JSB league folder. JSB then loads the charts directly, so nobody enters trades in JSB or runs Game Plan > Import All.

Format background lives in [JSB_FILE_FORMATS.md](JSB_FILE_FORMATS.md) (`ibl5/docs/JSB_FILE_FORMATS.md`) and [the JsbParser README](../classes/JsbParser/README.md) (`ibl5/classes/JsbParser/README.md`).

## When to use it

Run the export after the site has processed the trades and the GMs have submitted their depth charts. Run it before the next sim.

## Steps

1. Log in to the site as an admin and open `/ibl5/scripts/jsbExport.php`. Click Run Export.
2. Read the PLR and PLB sections. Each lists its source file, its messages, and a change table. A line that starts with WARNING means a traded player found no empty slot on his new team. Fix that roster in JSB by hand before you sim.
3. Download `IBL5_export.plr` and `IBL5_export.plb` from the site's `ibl5/` folder.
4. Close JSB. Back up `C:\JSB\IBL5.plr` and `C:\JSB\IBL5.plb`.
5. Rename the exports to `IBL5.plr` and `IBL5.plb` and copy them into `C:\JSB\`.
6. Empty `C:\JSB\IBL5\imp\`, so Game Plan cannot import an old chart.
7. Open IBL5 in JSB. Check both rosters of every trade, then sim.

## What changes

The commissioner no longer enters trades in JSB. Game Plan > Import All is not needed when the export is used, because the depth and minutes are already in the files. The export writes only `IBL5.plr` and `IBL5.plb`. Every other league file stays JSB's own, including `IBL5.trn`. The 2026-11-16 VM acceptance run passed. A 2026-11-17 VM run settled F7 and F8 below.

## Teams without a site chart

A team counts as charted when any of its players has minutes or a depth slot set on the site. For a team with no chart, the export leaves its depth, active flag, and .plb bytes exactly as JSB last wrote them.

## Fallback

The per-team depth chart .txt files still work as before. `DepthChartProcessor::generateCsvContent` builds them, and Game Plan > Import All reads them. Use them whenever the export page shows an ERROR line or JSB rejects the files. Restore the backups from step 4 first.

## Research findings (2026-11-16)

I measured these on the real saves `08-09_19_reg-sim08.zip` (before the Grizzlies-Lakers trade) and `08-09_20_reg-sim09.zip` (after JSB applied it). The 2026-11-16 VM acceptance run passed every step. On 2026-11-17 I drove the same trade in the JSB GUI on the VM and logged every file JSB opened, read, and wrote. That run settled F7 and F8.

- **F1.** The .plr file is CRLF-separated. Line 0 is a 607-char header, and the record for ordinal N sits at line index N. Ordinals 1-1440 are player slots, followed by 164 other lines and a trailing empty segment. Every empty slot is a full 607-char line whose pid field (offset 38, width 6) trims to 0. Empty lines are not all identical: sim08 has 21 distinct empty-line bodies after the ordinal field, though neighbours in one team block match (583 and 584 are identical). The relocator finds lines by reading the ordinal field and treats pid 0 as empty.
- **F2.** JSB does not leave a hole when a player leaves. It re-packs the whole team block. After the trade Marcus Camby moved 579 to 580, Ricky Davis 610 to 605, Bruno Fernando 611 to 609, and Gani Lawal 613 to 612. No block in either save has an empty slot before a filled slot. So sim09 has no vacated slot to compare against, and the slots 582, 605, 608 hold shifted players. The export keeps the plan's swap rule: a vacated slot takes the target empty line's bytes with the vacated ordinal. This leaves holes JSB itself never writes. The 2026-11-16 VM run showed JSB accepts them: after the Turner and Martin trade it loaded the export, listed Lakers rows 605 and 608 as blank, and simmed a day.
- **F3.** For Turner, Martin, Kagami and Pittman, JSB set teamid, contractOwnedBy and currentTeamIndex (new tid minus 1) for the new team. previousTeamIndex kept its sim08 value (Turner 20, Kagami 19). Each mover's sim08 previousTeamIndex already equalled old tid minus 1, so keeping the old value and writing old tid minus 1 give the same bytes. The sample cannot separate the two rules (undetermined, default kept: `PlrTradeRelocator` restores the old previousTeamIndex).
- **F4.** The .plb file is exactly 160,000 bytes. It has 32 team lines of 360 chars, then 33 empty CRLF lines, then a final segment of 148,350 spaces with no trailing CRLF. Line index t holds team t+1, and slot k maps to ordinal t*30+k+1. Values are space-padded (`%2d`), so a 1 reads as a space and a 1. Slot values travel with the player: Turner 40 minutes at Lakers slot 7 then Grizzlies slot 8, Kagami 20, Pittman 1. In sim09 team-line 19 slot 11 (ordinal 582) holds Tremont Waters' value of 1, carried from his old slot 581. Team-line 20 slots 4 and 7 (605, 608) hold the shifted Davis and Biedrins values. The writer touches only line indices 0-31.
- **F5.** The rule "ascending source ordinal, first pid-0 slot of the target block, vacate at once" puts Martin at 575 (matches JSB), Turner at 582 (JSB: 579), Kagami at 614 (JSB: 611), and Pittman at 615 (JSB: 613). JSB re-sorts the block, so no rule that moves only the traded players can match sim09 exactly. The default stays. The real-save test asserts each block's pid set, the movers' team fields, and the unmoved players' ordinals.
- **F6.** No repo code writes `ibl5/IBL5.plr` (example) or `ibl5/IBL5.plb` (example) per sim. `ibl5/classes/Updater/JsbSourceResolver.php` reads `IBL5.<ext>` from the newest `backups/<season>/` archive and falls back to the disk file. The export page sources both inputs through it.
- **F7.** A trade in the JSB GUI changes three files: IBL5.plr, IBL5.plb, and IBL5.trn. I loaded the stock sim08 save and traded Kagami and Pittman to the Lakers for Turner and Martin. Both teams accepted. JSB wrote nothing to disk at Attempt Trade, at OK, or at its "save the player transactions?" prompt. The trade lived in memory until Setup League > Save IBL5. That save rewrote 12 files (.arf, .bud, .dub, .frn, .fro, .lge, .own, .plb, .plr, .rcb, .sch, .trn), and only .plr, .plb, and .trn changed content. The other nine came back byte-identical. JSB's .plb matched sim09 on every changed byte. Of the 526 .plr lines JSB changed, 471 match sim09 exactly; sim09 also holds a simmed day. The export writes .plr and .plb, so the one file a JSB trade touches that the export leaves alone is .trn (see F8).
- **F8.** JSB reads IBL5.trn on load, but the rosters do not depend on it. IBL5.trn is JSB's transaction log: a 16-byte header with a record count, then 128-byte records. Injuries are type 1 and trades are type 20. At load JSB read 9,872 bytes of it, the header plus all 77 sim08 records. On Save it kept those 77 records byte-identical and appended two type-20 records for the trade, which match sim09's records except the date. I then loaded the league with IBL5.trn deleted, and again with 64,000 bytes of junk in it. Both loaded with no error dialog and built the Grizzlies roster from .plr. With the file deleted, JSB wrote a new blank IBL5.trn. I found no JSB screen that shows these records. So the runbook keeps JSB's own IBL5.trn, and the site's `IBL5_export.trn` stays a site-side log. One effect: trades made on the site no longer reach JSB's own log, because the commissioner no longer enters them in JSB.
