# LeagueControlPanel Module

Admin control panel for managing league settings, season phases, and system-wide toggles.

## Architecture

| Class | Role |
|-------|------|
| `LeagueControlPanelRepository` | All database operations via `BaseMysqliRepository` |
| `LeagueControlPanelService` | Read-only panel data assembly for GET requests |
| `LeagueControlPanelProcessor` | POST mutation dispatch with input validation |
| `LeagueControlPanelView` | XSS-safe HTML rendering |

## Entry Point

`ibl5/leagueControlPanel.php` — thin bootstrapper with admin auth guard and PRG pattern.

## Actions

| Action Key | Description |
|---|---|
| `set_season_phase` | Set current season phase (Preseason, HEAT, Regular Season, Playoffs, Draft, Free Agency) |
| `set_sim_length` | Set sim length in days (1-180) |
| `set_allow_trades` | Toggle trade window (Yes/No) |
| `set_allow_waivers` | Toggle waiver moves (Yes/No) |
| `set_show_draft_link` | Toggle draft link visibility and module (On/Off) |
| `toggle_fa_notifications` | Toggle free agency Discord notifications (On/Off) |
| `activate_trivia` | Hide Player and Season Leaders modules for trivia |
| `deactivate_trivia` | Restore Player and Season Leaders modules |
| `reset_contract_extensions` | Reset all teams' extension flags |
| `reset_mles_lles` | Reset all teams' MLE and LLE flags |
| `reset_asg_voting` | Clear All-Star voting and re-enable |
| `reset_eoy_voting` | Clear End of Year voting and re-enable |
| `set_waivers_to_free_agents` | Move waived players to Free Agents, reset Bird years |
| `set_fa_factors_pfw` | Update Play For Winner demand factors from standings |

## Tests

56 unit tests in `ibl5/tests/LeagueControlPanel/`.
