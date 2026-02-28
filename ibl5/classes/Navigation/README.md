# Navigation Module

Renders the main site navigation bar (desktop + mobile) using the Repository/Service/View pattern.

## Architecture

```
Navigation/
├── Contracts/
│   ├── NavigationRepositoryInterface.php   # DB operations interface
│   └── NavigationMenuBuilderInterface.php  # Menu data interface
├── Views/
│   ├── DesktopNavView.php                  # Desktop dropdowns, dev switch
│   ├── MobileNavView.php                   # Mobile sliding panel
│   ├── LoginFormView.php                   # Shared login form (desktop/mobile)
│   └── TeamsDropdownView.php               # Teams mega-menu (desktop/mobile)
├── NavigationConfig.php                    # Value object (DTO) for config
├── NavigationRepository.php                # DB queries (extends BaseMysqliRepository)
├── NavigationMenuBuilder.php               # Menu structure logic (no HTML)
└── NavigationView.php                      # Thin orchestrator (~80 lines)
```

## Key Classes

| Class | Responsibility |
|-------|----------------|
| `NavigationConfig` | Readonly DTO replacing 10 constructor params |
| `NavigationRepository` | `resolveTeamId()`, `getTeamsData()` |
| `NavigationMenuBuilder` | Menu structure, Draft/FA/Waivers conditionals, Olympics variant |
| `DesktopNavView` | Desktop dropdowns with staggered animations |
| `MobileNavView` | Mobile accordion with sliding panel |
| `LoginFormView` | Login form with desktop/mobile sizing variants |
| `TeamsDropdownView` | Teams 2x2 grid (desktop) / hierarchical list (mobile) |
| `NavigationView` | Composes sub-views into final `<nav>` output |

## Usage

```php
$navRepo = new NavigationRepository($mysqli_db);
$config = new NavigationConfig(
    isLoggedIn: $isLoggedIn,
    username: $username,
    currentLeague: $currentLeague,
    teamId: $navRepo->resolveTeamId($username),
    teamsData: $navRepo->getTeamsData(),
    seasonPhase: $season->phase,
    // ...
);
$nav = new NavigationView($config);
echo $nav->render();
```

## Test Coverage

50 tests across 8 test files covering menu conditionals, view rendering, repository queries, and orchestration.
