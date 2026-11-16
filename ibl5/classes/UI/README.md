---
description: Reusable UI utilities and components including alert rendering, team cells, HTMX-enhanced table controls, and player stats table helpers.
last_verified: 2026-11-16
---

# UI

Cross-cutting UI utilities used across multiple view classes. `AlertRenderer` replaces duplicate private methods across views by rendering `ibl-alert` banners from a result code and a caller-supplied banner map. `TeamCellHelper` renders standardized team cells with logo and name link. The `Components/` subdirectory holds HTMX-enhanced reusable controls (`TableViewSwitcher`, `TableViewDropdown`, `TooltipLabel`) and the `PageTabs` page tab bar. The `Tables/` subdirectory holds player statistics table rendering utilities (`PlayerRowTransformer`, `Ratings`, `Contracts`).

`bin/check-design-system` fails when a class or trait in this directory has no row in the table below. `Contracts/` interfaces are exempt.

| Class | Role |
|---|---|
| `AlertRenderer` | Renders ibl-alert banners from result code + banner map |
| `TeamCellHelper` | Renders team cell HTML with logo and name link |
| `DebugOutput` | Debug output helpers |
| `TableStyles` | Builds the per-team `--team-color-*` inline variables and sanitizes hex colors |
| `RendersTableEnd` | Trait that renders the closing `</tbody></table>` markup |
| `Components\TableViewSwitcher` | HTMX-enhanced table view toggle |
| `Components\TableViewDropdown` | HTMX-enhanced dropdown for table views |
| `Components\TooltipLabel` | Tooltip label component |
| `Components\PageTabs` | Page-level tab bar and panel wrapper for multi-view module pages |
| `Tables\PlayerRowTransformer` | Transforms player data rows for table rendering |
| `Tables\Ratings` | Renders the player ratings table |
| `Tables\Contracts` | Renders the team contracts table |
