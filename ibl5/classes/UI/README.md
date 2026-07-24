---
description: Reusable UI utilities and components including alert rendering, team cells, HTMX-enhanced table controls, and player stats table helpers.
last_verified: 2026-07-24
---

# UI

Cross-cutting UI utilities used across multiple view classes. `AlertRenderer` replaces duplicate private methods across views by rendering `ibl-alert` banners from a result code and a caller-supplied banner map. `TeamCellHelper` renders standardized team cells with logo and name link. The `Components/` subdirectory holds HTMX-enhanced reusable controls (`TableViewSwitcher`, `TableViewDropdown`, `TooltipLabel`). The `Tables/` subdirectory holds player statistics table rendering utilities (`PlayerRowTransformer`, ratings and contracts formatters).

| Class | Role |
|---|---|
| `AlertRenderer` | Renders ibl-alert banners from result code + banner map |
| `TeamCellHelper` | Renders team cell HTML with logo and name link |
| `DebugOutput` | Debug output helpers |
| `TableStyles` | Shared CSS class constants for tables |
| `Components\TableViewSwitcher` | HTMX-enhanced table view toggle |
| `Components\TableViewDropdown` | HTMX-enhanced dropdown for table views |
| `Components\TooltipLabel` | Tooltip label component |
| `Tables\PlayerRowTransformer` | Transforms player data rows for table rendering |
