<?php

declare(strict_types=1);

namespace SiteStatistics;

/**
 * Repository for site statistics data access
 * Handles database operations for traffic counters and stats tables
 * 
 * @extends \BaseMysqliRepository
 */
class StatisticsRepository extends \BaseMysqliRepository
{
    private string $prefix;
    private string $userPrefix;

    public function __construct(\mysqli $db)
    {
        parent::__construct($db);
        
        global $prefix, $user_prefix;
        $this->prefix = $prefix;
        $this->userPrefix = $user_prefix;
    }

    /**
     * Get all counter statistics grouped by type
     * 
     * @return array Associative array of counter data by type and variable
     */
    public function getAllCounters(): array
    {
        $counters = [
            'total' => 0,
            'browsers' => [],
            'os' => []
        ];

        $rows = $this->fetchAll(
            "SELECT type, var, count FROM {$this->prefix}_counter ORDER BY type DESC"
        );

        foreach ($rows as $row) {
            $type = $this->sanitizeString($row['type']);
            $var = $this->sanitizeString($row['var']);
            $count = intval($row['count']);

            if ($type === 'total' && $var === 'hits') {
                $counters['total'] = $count;
            } elseif ($type === 'browser') {
                $counters['browsers'][$var] = $count;
            } elseif ($type === 'os') {
                $counters['os'][$var] = $count;
            }
        }

        return $counters;
    }

    /**
     * Sanitize string value
     * 
     * @param string $value Value to sanitize
     * @return string Sanitized value
     */
    private function sanitizeString(string $value): string
    {
        // Use check_html if available, otherwise just stripslashes
        if (function_exists('check_html')) {
            return stripslashes(check_html($value, "nohtml"));
        }
        return stripslashes($value);
    }

    /**
     * Get total hit count from counter
     * 
     * @return int Total hits
     */
    public function getTotalHits(): int
    {
        $row = $this->fetchOne(
            "SELECT count FROM {$this->prefix}_counter WHERE type = ? AND var = ?",
            "ss",
            "total",
            "hits"
        );
        
        if ($row) {
            return intval($row['count']);
        }
        
        return 0;
    }

    /**
     * Get the month with highest traffic
     * 
     * @return array|null Month data with year, month, and hits
     */
    public function getHighestTrafficMonth(): ?array
    {
        $row = $this->fetchOne(
            "SELECT year, month, hits FROM {$this->prefix}_stats_month 
             ORDER BY hits DESC LIMIT 1"
        );
        
        if ($row) {
            return [
                'year' => intval($row['year']),
                'month' => intval($row['month']),
                'hits' => intval($row['hits'])
            ];
        }
        
        return null;
    }

    /**
     * Get the day with highest traffic
     * 
     * @return array|null Day data with year, month, date, and hits
     */
    public function getHighestTrafficDay(): ?array
    {
        $row = $this->fetchOne(
            "SELECT year, month, date, hits FROM {$this->prefix}_stats_date 
             ORDER BY hits DESC LIMIT 1"
        );
        
        if ($row) {
            return [
                'year' => intval($row['year']),
                'month' => intval($row['month']),
                'date' => intval($row['date']),
                'hits' => intval($row['hits'])
            ];
        }
        
        return null;
    }

    /**
     * Get the hour with highest traffic
     * 
     * @return array|null Hour data with year, month, date, hour, and hits
     */
    public function getHighestTrafficHour(): ?array
    {
        $row = $this->fetchOne(
            "SELECT year, month, date, hour, hits FROM {$this->prefix}_stats_hour 
             ORDER BY hits DESC LIMIT 1"
        );
        
        if ($row) {
            return [
                'year' => intval($row['year']),
                'month' => intval($row['month']),
                'date' => intval($row['date']),
                'hour' => intval($row['hour']),
                'hits' => intval($row['hits'])
            ];
        }
        
        return null;
    }

    /**
     * Get statistics counts for miscellaneous data
     * 
     * @return array Counts for users, authors, stories, comments, etc.
     */
    public function getMiscCounts(): array
    {
        $topicsActive = function_exists('is_active') ? is_active("Topics") : false;
        $linksActive = function_exists('is_active') ? is_active("Web_Links") : false;

        /** @var array{users: int, authors: int, stories: int, comments: int}|null $row */
        $row = $this->fetchOne(
            "SELECT
                (SELECT COUNT(*) FROM {$this->userPrefix}_users) AS users,
                (SELECT COUNT(*) FROM {$this->prefix}_authors) AS authors,
                (SELECT COUNT(*) FROM {$this->prefix}_stories) AS stories,
                (SELECT COUNT(*) FROM {$this->prefix}_comments) AS comments"
        );

        $counts = [
            'users' => intval($row['users'] ?? 0),
            'authors' => intval($row['authors'] ?? 0),
            'stories' => intval($row['stories'] ?? 0),
            'comments' => intval($row['comments'] ?? 0),
            'topics' => 0,
            'links' => 0,
            'linkCategories' => 0,
        ];

        if ($topicsActive) {
            /** @var array{cnt: int}|null $topicRow */
            $topicRow = $this->fetchOne(
                "SELECT COUNT(*) AS cnt FROM {$this->prefix}_topics"
            );
            $counts['topics'] = intval($topicRow['cnt'] ?? 0);
        }

        if ($linksActive) {
            /** @var array{links: int, linkCategories: int}|null $linkRow */
            $linkRow = $this->fetchOne(
                "SELECT
                    (SELECT COUNT(*) FROM {$this->prefix}_links_links) AS links,
                    (SELECT COUNT(*) FROM {$this->prefix}_links_categories) AS linkCategories"
            );
            $counts['links'] = intval($linkRow['links'] ?? 0);
            $counts['linkCategories'] = intval($linkRow['linkCategories'] ?? 0);
        }

        return $counts;
    }

    /**
     * Get yearly statistics
     * 
     * @return array Array of year data with hits
     */
    public function getYearlyStats(): array
    {
        $rows = $this->fetchAll(
            "SELECT year, hits FROM {$this->prefix}_stats_year ORDER BY year"
        );
        
        $stats = [];
        foreach ($rows as $row) {
            $stats[] = [
                'year' => intval($row['year']),
                'hits' => intval($row['hits'])
            ];
        }
        
        return $stats;
    }

    /**
     * Get total hits for all years
     * 
     * @return int Total yearly hits
     */
    public function getTotalYearlyHits(): int
    {
        $row = $this->fetchOne(
            "SELECT SUM(hits) as total FROM {$this->prefix}_stats_year"
        );
        
        if ($row) {
            return intval($row['total']);
        }
        
        return 0;
    }

    /**
     * Get monthly statistics for a specific year
     * 
     * @param int $year Year to get stats for
     * @return array Array of month data with hits
     */
    public function getMonthlyStats(int $year): array
    {
        $rows = $this->fetchAll(
            "SELECT month, hits FROM {$this->prefix}_stats_month WHERE year = ?",
            "i",
            $year
        );
        
        $stats = [];
        foreach ($rows as $row) {
            $stats[] = [
                'month' => intval($row['month']),
                'hits' => intval($row['hits'])
            ];
        }
        
        return $stats;
    }

    /**
     * Get total hits for a specific year's months
     * 
     * @param int $year Year to get total for
     * @return int Total monthly hits for year
     */
    public function getTotalMonthlyHits(int $year): int
    {
        $row = $this->fetchOne(
            "SELECT SUM(hits) as total FROM {$this->prefix}_stats_month WHERE year = ?",
            "i",
            $year
        );
        
        if ($row) {
            return intval($row['total']);
        }
        
        return 0;
    }

    /**
     * Get daily statistics for a specific year and month
     * 
     * @param int $year Year to get stats for
     * @param int $month Month to get stats for
     * @return array Array of day data with hits
     */
    public function getDailyStats(int $year, int $month): array
    {
        $rows = $this->fetchAll(
            "SELECT year, month, date, hits FROM {$this->prefix}_stats_date 
             WHERE year = ? AND month = ? 
             ORDER BY date",
            "ii",
            $year,
            $month
        );
        
        $stats = [];
        foreach ($rows as $row) {
            $stats[] = [
                'year' => intval($row['year']),
                'month' => intval($row['month']),
                'date' => intval($row['date']),
                'hits' => intval($row['hits'])
            ];
        }
        
        return $stats;
    }

    /**
     * Get total hits for a specific year and month
     * 
     * @param int $year Year to get total for
     * @param int $month Month to get total for
     * @return int Total daily hits for month
     */
    public function getTotalDailyHits(int $year, int $month): int
    {
        $row = $this->fetchOne(
            "SELECT SUM(hits) as total FROM {$this->prefix}_stats_date 
             WHERE year = ? AND month = ?",
            "ii",
            $year,
            $month
        );
        
        if ($row) {
            return intval($row['total']);
        }
        
        return 0;
    }

    /**
     * Get hourly statistics for a specific date
     * 
     * @param int $year Year to get stats for
     * @param int $month Month to get stats for
     * @param int $date Day to get stats for
     * @return array Array of hourly data (0-23)
     */
    public function getHourlyStats(int $year, int $month, int $date): array
    {
        $stats = array_fill(0, 24, 0);

        $rows = $this->fetchAll(
            "SELECT hour, hits FROM {$this->prefix}_stats_hour
             WHERE year = ? AND month = ? AND date = ?
             ORDER BY hour ASC",
            "iii",
            $year,
            $month,
            $date
        );

        foreach ($rows as $row) {
            $hour = intval($row['hour']);
            if ($hour >= 0 && $hour <= 23) {
                $stats[$hour] = intval($row['hits']);
            }
        }

        return $stats;
    }

    /**
     * Get total hits for a specific date
     *
     * @param int $year Year to get total for
     * @param int $month Month to get total for
     * @param int $date Day to get total for
     * @return int Total hourly hits for date
     */
    public function getTotalHourlyHits(int $year, int $month, int $date): int
    {
        $row = $this->fetchOne(
            "SELECT SUM(hits) as total FROM {$this->prefix}_stats_hour
             WHERE year = ? AND month = ? AND date = ?",
            "iii",
            $year,
            $month,
            $date
        );

        if ($row) {
            return intval($row['total']);
        }

        return 0;
    }

    /**
     * Record a page hit — replaces legacy includes/counter.php
     *
     * Detects browser/OS from User-Agent, increments counter rows,
     * seeds time-series tables for new years/days, and increments
     * year/month/date/hour hit counters.
     */
    public function recordHit(): void
    {
        $rawUA = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $userAgent = is_string($rawUA) ? $rawUA : '';

        $browser = $this->detectBrowser($userAgent);
        $os = $this->detectOS($userAgent);

        // Increment counter rows (total hits, browser, OS)
        $this->execute(
            "UPDATE {$this->prefix}_counter SET count = count + 1 WHERE type = 'total' AND var = 'hits'"
        );
        $this->execute(
            "UPDATE {$this->prefix}_counter SET count = count + 1 WHERE type = 'browser' AND var = ?",
            "s",
            $browser
        );
        $this->execute(
            "UPDATE {$this->prefix}_counter SET count = count + 1 WHERE type = 'os' AND var = ?",
            "s",
            $os
        );

        // Current date/time parts
        $nowYear = (int) date('Y');
        $nowMonth = (int) date('n');
        $nowDate = (int) date('j');
        $nowHour = (int) date('G');

        // Seed year/month/date rows if this is the first hit of a new year
        $yearRow = $this->fetchOne(
            "SELECT year FROM {$this->prefix}_stats_year WHERE year = ?",
            "i",
            $nowYear
        );

        if ($yearRow === null) {
            $this->execute(
                "INSERT INTO {$this->prefix}_stats_year (year, hits) VALUES (?, 0)",
                "i",
                $nowYear
            );

            for ($month = 1; $month <= 12; $month++) {
                $this->execute(
                    "INSERT INTO {$this->prefix}_stats_month (year, month, hits) VALUES (?, ?, 0)",
                    "ii",
                    $nowYear,
                    $month
                );

                $daysInMonth = (int) date('t', mktime(0, 0, 0, $month, 1, $nowYear));
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $this->execute(
                        "INSERT INTO {$this->prefix}_stats_date (year, month, date, hits) VALUES (?, ?, ?, 0)",
                        "iii",
                        $nowYear,
                        $month,
                        $day
                    );
                }
            }
        }

        // Seed hour rows if this is the first hit of a new day
        $hourRow = $this->fetchOne(
            "SELECT hour FROM {$this->prefix}_stats_hour WHERE year = ? AND month = ? AND date = ? LIMIT 1",
            "iii",
            $nowYear,
            $nowMonth,
            $nowDate
        );

        if ($hourRow === null) {
            for ($hour = 0; $hour <= 23; $hour++) {
                $this->execute(
                    "INSERT INTO {$this->prefix}_stats_hour (year, month, date, hour, hits) VALUES (?, ?, ?, ?, 0)",
                    "iiii",
                    $nowYear,
                    $nowMonth,
                    $nowDate,
                    $hour
                );
            }
        }

        // Increment time-series counters
        $this->execute(
            "UPDATE {$this->prefix}_stats_year SET hits = hits + 1 WHERE year = ?",
            "i",
            $nowYear
        );
        $this->execute(
            "UPDATE {$this->prefix}_stats_month SET hits = hits + 1 WHERE year = ? AND month = ?",
            "ii",
            $nowYear,
            $nowMonth
        );
        $this->execute(
            "UPDATE {$this->prefix}_stats_date SET hits = hits + 1 WHERE year = ? AND month = ? AND date = ?",
            "iii",
            $nowYear,
            $nowMonth,
            $nowDate
        );
        $this->execute(
            "UPDATE {$this->prefix}_stats_hour SET hits = hits + 1 WHERE year = ? AND month = ? AND date = ? AND hour = ?",
            "iiii",
            $nowYear,
            $nowMonth,
            $nowDate,
            $nowHour
        );
    }

    /**
     * Detect browser category from User-Agent string.
     * Categories match existing nuke_counter rows — do not rename.
     */
    private function detectBrowser(string $userAgent): string
    {
        if (
            (str_contains($userAgent, 'Nav')
                || str_contains($userAgent, 'Gold')
                || str_contains($userAgent, 'X11')
                || str_contains($userAgent, 'Mozilla')
                || str_contains($userAgent, 'Netscape'))
            && !str_contains($userAgent, 'MSIE')
            && !str_contains($userAgent, 'Konqueror')
            && !str_contains($userAgent, 'Yahoo')
            && !str_contains($userAgent, 'Firefox')
        ) {
            return 'Netscape';
        }

        if (str_contains($userAgent, 'Firefox')) {
            return 'FireFox';
        }
        if (str_contains($userAgent, 'MSIE')) {
            return 'MSIE';
        }
        if (str_contains($userAgent, 'Lynx')) {
            return 'Lynx';
        }
        if (str_contains($userAgent, 'Opera')) {
            return 'Opera';
        }
        if (str_contains($userAgent, 'WebTV')) {
            return 'WebTV';
        }
        if (str_contains($userAgent, 'Konqueror')) {
            return 'Konqueror';
        }
        if (
            stripos($userAgent, 'bot') !== false
            || str_contains($userAgent, 'Google')
            || str_contains($userAgent, 'Slurp')
            || str_contains($userAgent, 'Scooter')
            || stripos($userAgent, 'Spider') !== false
            || stripos($userAgent, 'Infoseek') !== false
        ) {
            return 'Bot';
        }

        return 'Other';
    }

    /**
     * Detect OS category from User-Agent string.
     * Categories match existing nuke_counter rows — do not rename.
     */
    private function detectOS(string $userAgent): string
    {
        if (str_contains($userAgent, 'Win')) {
            return 'Windows';
        }
        if (str_contains($userAgent, 'Mac') || str_contains($userAgent, 'PPC')) {
            return 'Mac';
        }
        if (str_contains($userAgent, 'Linux')) {
            return 'Linux';
        }
        if (str_contains($userAgent, 'FreeBSD')) {
            return 'FreeBSD';
        }
        if (str_contains($userAgent, 'SunOS')) {
            return 'SunOS';
        }
        if (str_contains($userAgent, 'IRIX')) {
            return 'IRIX';
        }
        if (str_contains($userAgent, 'BeOS')) {
            return 'BeOS';
        }
        if (str_contains($userAgent, 'OS/2')) {
            return 'OS/2';
        }
        if (str_contains($userAgent, 'AIX')) {
            return 'AIX';
        }

        return 'Other';
    }
}
