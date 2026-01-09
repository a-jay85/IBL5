# SiteStatistics Module - Website Analytics and Tracking

## Overview

The SiteStatistics module provides comprehensive website analytics and tracking for the IBL5 site. It tracks visitor behavior, browser/OS usage, page views, and traffic patterns.

**Important:** This module is for **website/site statistics only**. For basketball statistics formatting, see [`BasketballStats\StatsFormatter`](../BasketballStats/README.md).

## Architecture

The module follows the Repository-Processor-View pattern with clear separation of concerns:

```
SiteStatistics/
├── StatisticsController.php    # Orchestration and routing
├── StatisticsRepository.php    # Database queries for counter data
├── StatisticsProcessor.php     # Calculations and transformations
└── StatisticsView.php          # HTML rendering
```

### Classes

#### StatisticsController
- **Purpose:** Orchestrates data retrieval, processing, and view rendering
- **Responsibilities:** 
  - Route requests to appropriate methods
  - Coordinate between repository, processor, and view
  - Handle main stats, yearly/monthly/daily breakdowns

#### StatisticsRepository
- **Purpose:** Database operations for traffic counters and stats tables
- **Responsibilities:**
  - Query `nuke_counter` table for traffic data
  - Retrieve browser and OS statistics
  - Fetch time-based traffic patterns (yearly, monthly, daily, hourly)

#### StatisticsProcessor
- **Purpose:** Calculations and data transformations
- **Responsibilities:**
  - Calculate percentages for browser/OS usage
  - Format month names and hour ranges
  - Calculate bar widths for visual displays

#### StatisticsView
- **Purpose:** HTML rendering for statistics display
- **Responsibilities:**
  - Render main statistics summary
  - Display detailed browser/OS statistics
  - Create yearly/monthly/daily/hourly breakdowns

## Database Tables

The module queries these PhpNuke tables:

- `nuke_counter` - Traffic counters (hits, browsers, OS)
- `nuke_counter_year` - Yearly traffic totals
- `nuke_counter_month` - Monthly traffic by year
- `nuke_counter_date` - Daily traffic by month
- `nuke_counter_hour` - Hourly traffic distribution

## Usage

The module is accessed via `/modules/SiteStatistics/` and provides:

1. **Main Statistics Page** - Overview of total hits, top browser/OS, misc counts
2. **Detailed Statistics** - Full browser and OS breakdowns with percentages
3. **Yearly Statistics** - Traffic by year with trend analysis
4. **Monthly Statistics** - Traffic by month for a specific year
5. **Daily Statistics** - Traffic by day for a specific month

## Features

### Traffic Tracking
- Total site hits since launch
- Highest traffic month/day/hour
- Trend analysis over time

### Browser Statistics
- Tracks: Internet Explorer, Firefox, Chrome, Safari, Opera, Netscape, WebTV, Konqueror, Lynx, and others
- Displays usage percentages
- Visual bar graphs

### Operating System Statistics
- Tracks: Windows, Mac, Linux, FreeBSD, SunOS, IRIX, BeOS, OS/2, AIX, and others
- Displays usage percentages
- Visual bar graphs

### Miscellaneous Counts
- Total stories published
- Total comments
- Total users
- Total active users

## Testing

Tests are located in `/ibl5/tests/SiteStatistics/`:

| Test File | Purpose |
|-----------|---------|
| `StatisticsControllerTest.php` | Controller instantiation and method existence |
| `StatisticsProcessorTest.php` | Percentage calculations, formatting, data transformations |
| `StatisticsRepositoryTest.php` | Database queries, data retrieval, edge cases |

Run tests:
```bash
cd ibl5
vendor/bin/phpunit tests/SiteStatistics/
```

## Related Modules

- **BasketballStats** - For basketball statistics formatting ([README](../BasketballStats/README.md))
- **Player Module** - For player statistics and data
- **LeagueStats Module** - For team statistics

## Migration Notes

This module was previously located at `modules/Statistics/` and used the `Statistics\` namespace. It has been renamed to:
- **Location:** `modules/SiteStatistics/`
- **Namespace:** `SiteStatistics\`

This change disambiguates website statistics from basketball statistics.

## Technical Details

### Security
- All database queries use prepared statements via `BaseMysqliRepository`
- HTML output is sanitized

### Performance
- Efficient queries minimize database load
- Counter updates are batched

### Standards
- Follows PSR-4 autoloading
- Type hints on all methods
- Comprehensive PHPDoc comments

## Future Enhancements

Potential improvements:
- Real-time analytics dashboard
- Geographic location tracking
- Page-level analytics
- Conversion tracking
- Custom event tracking
