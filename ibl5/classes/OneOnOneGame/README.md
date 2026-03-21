# One-on-One Module

> **Note:** This is a fan-created mini-game. It is **not** a representation of how the Jump Shot Basketball (JSB) simulation engine works. The game mechanics here are original and should not be used to understand JSB logic.

The One-on-One module allows users to simulate a one-on-one basketball game between any two players in the league. Games are played to 21 points.

## Architecture

This module follows the **Interface-Driven Architecture Pattern** established in the IBL5 codebase.

### Directory Structure

```
classes/OneOnOneGame/
├── Contracts/
│   ├── OneOnOneGameRepositoryInterface.php   # Database operations contract
│   ├── OneOnOneGameEngineInterface.php   # Game simulation contract
│   ├── OneOnOneGameServiceInterface.php      # Business logic contract
│   └── OneOnOneGameViewInterface.php         # View rendering contract
├── OneOnOneGameRepository.php                # Database operations
├── OneOnOneGameEngine.php                # Game simulation logic
├── OneOnOneGameService.php                   # Business logic coordinator
├── OneOnOneGameView.php                      # HTML rendering
├── OneOnOneGameResult.php                # Game result DTO
├── OneOnOneGamePlayerStats.php               # Player stats DTO
├── OneOnOneGameTextGenerator.php             # Play-by-play text generation
└── README.md                             # This file
```

### Classes

#### Data Transfer Objects (DTOs)

- **OneOnOneGamePlayerStats**: Tracks player statistics during a game (FGM, FGA, 3PM, 3PA, ORB, REB, STL, BLK, TO, FOUL)
- **OneOnOneGameResult**: Complete game result including scores, stats, play-by-play, and winner/loser info

#### Core Classes

- **OneOnOneGameRepository**: Extends `BaseMysqliRepository` for database operations on `ibl_one_on_one` table
- **OneOnOneGameEngine**: Simulates game mechanics including shot selection, shooting, blocking, stealing, fouls, and rebounds
- **OneOnOneGameService**: Orchestrates game flow - validates input, loads players, runs game, saves result, posts to Discord
- **OneOnOneGameView**: Renders all HTML output including forms, game results, and replays
- **OneOnOneGameTextGenerator**: Provides randomized play-by-play text for various game events

## Game Mechanics

### Shot Types
- **Three-pointer**: Outside shot beyond the arc (3 points)
- **Outside two**: Perimeter shot inside the arc (2 points)
- **Drive**: Driving to the basket (2 points)
- **Post**: Low-post move (2 points)

### Events
- **Blocks**: Defender can block shot attempts
- **Steals**: Defender can steal the ball
- **Fouls**: Defender fouls, offensive player shoots free throws
- **Rebounds**: After missed shots, either player can get the rebound

### Winning
Game ends when one player reaches 21 points.

## Database Schema

```sql
CREATE TABLE `ibl_one_on_one` (
  `gameid` int(11) NOT NULL DEFAULT 0,
  `playbyplay` mediumtext NOT NULL,
  `winner` varchar(32) NOT NULL DEFAULT '',
  `loser` varchar(32) NOT NULL DEFAULT '',
  `winscore` int(11) NOT NULL DEFAULT 0,
  `lossscore` int(11) NOT NULL DEFAULT 0,
  `owner` varchar(25) NOT NULL DEFAULT '',
  PRIMARY KEY (`gameid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Testing

Tests are located in `tests/OneOnOneGame/`:

- `OneOnOneGamePlayerStatsTest.php` - DTO tests
- `OneOnOneGameResultTest.php` - DTO tests
- `OneOnOneGameTextGeneratorTest.php` - Text generation tests
- `OneOnOneGameEngineTest.php` - Game mechanics tests
- `OneOnOneGameServiceTest.php` - Service layer tests
- `OneOnOneGameViewTest.php` - View rendering tests

Run tests:
```bash
cd ibl5 && vendor/bin/phpunit tests/OneOnOneGame/
```

## Discord Integration

Game results are automatically posted to the `#1v1-games` Discord channel via the `Discord::postToChannel()` method. Close games (margin ≤ 3 points) get special "BANG! BANG!" highlighting.

## Usage

The module is accessed via `modules.php?name=OneOnOneGame`. Users can:

1. Select two players from dropdowns and start a new game
2. Enter a game ID to replay a previously played game
3. View play-by-play action and final statistics

## Security

- All database queries use prepared statements via `BaseMysqliRepository`
- HTML output is escaped using `Utilities\HtmlSanitizer::safeHtmlOutput()`
- Input validation for player selection
