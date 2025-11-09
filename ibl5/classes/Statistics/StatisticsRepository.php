<?php

namespace Statistics;

/**
 * Repository for site statistics data access
 * Handles database operations for traffic counters and stats tables
 */
class StatisticsRepository
{
    private $db;
    private string $prefix;
    private string $userPrefix;

    public function __construct($db)
    {
        global $prefix, $user_prefix;
        
        $this->db = $db;
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

        $result = $this->db->sql_query(
            "SELECT type, var, count FROM {$this->prefix}_counter ORDER BY type DESC"
        );

        while ($row = $this->db->sql_fetchrow($result)) {
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
        $result = $this->db->sql_query(
            "SELECT count FROM {$this->prefix}_counter WHERE type = 'total' AND var = 'hits'"
        );
        
        if ($row = $this->db->sql_fetchrow($result)) {
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
        $result = $this->db->sql_query(
            "SELECT year, month, hits FROM {$this->prefix}_stats_month 
             ORDER BY hits DESC LIMIT 1"
        );
        
        if ($row = $this->db->sql_fetchrow($result)) {
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
        $result = $this->db->sql_query(
            "SELECT year, month, date, hits FROM {$this->prefix}_stats_date 
             ORDER BY hits DESC LIMIT 1"
        );
        
        if ($row = $this->db->sql_fetchrow($result)) {
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
        $result = $this->db->sql_query(
            "SELECT year, month, date, hour, hits FROM {$this->prefix}_stats_hour 
             ORDER BY hits DESC LIMIT 1"
        );
        
        if ($row = $this->db->sql_fetchrow($result)) {
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
        
        return [
            'users' => $this->db->sql_numrows(
                $this->db->sql_query("SELECT user_id FROM {$this->userPrefix}_users")
            ),
            'authors' => $this->db->sql_numrows(
                $this->db->sql_query("SELECT * FROM {$this->prefix}_authors")
            ),
            'stories' => $this->db->sql_numrows(
                $this->db->sql_query("SELECT sid FROM {$this->prefix}_stories")
            ),
            'comments' => $this->db->sql_numrows(
                $this->db->sql_query("SELECT tid FROM {$this->prefix}_comments")
            ),
            'submissions' => $this->db->sql_numrows(
                $this->db->sql_query("SELECT * FROM {$this->prefix}_queue")
            ),
            'topics' => $topicsActive ? $this->db->sql_numrows(
                $this->db->sql_query("SELECT * FROM {$this->prefix}_topics")
            ) : 0,
            'links' => $linksActive ? $this->db->sql_numrows(
                $this->db->sql_query("SELECT * FROM {$this->prefix}_links_links")
            ) : 0,
            'linkCategories' => $linksActive ? $this->db->sql_numrows(
                $this->db->sql_query("SELECT * FROM {$this->prefix}_links_categories")
            ) : 0
        ];
    }

    /**
     * Get yearly statistics
     * 
     * @return array Array of year data with hits
     */
    public function getYearlyStats(): array
    {
        $stats = [];
        $result = $this->db->sql_query(
            "SELECT year, hits FROM {$this->prefix}_stats_year ORDER BY year"
        );
        
        while ($row = $this->db->sql_fetchrow($result)) {
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
        $result = $this->db->sql_query(
            "SELECT SUM(hits) as total FROM {$this->prefix}_stats_year"
        );
        
        if ($row = $this->db->sql_fetchrow($result)) {
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
        $stats = [];
        $result = $this->db->sql_query(
            "SELECT month, hits FROM {$this->prefix}_stats_month 
             WHERE year = " . intval($year)
        );
        
        while ($row = $this->db->sql_fetchrow($result)) {
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
        $result = $this->db->sql_query(
            "SELECT SUM(hits) as total FROM {$this->prefix}_stats_month 
             WHERE year = " . intval($year)
        );
        
        if ($row = $this->db->sql_fetchrow($result)) {
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
        $stats = [];
        $result = $this->db->sql_query(
            "SELECT year, month, date, hits FROM {$this->prefix}_stats_date 
             WHERE year = " . intval($year) . " AND month = " . intval($month) . " 
             ORDER BY date"
        );
        
        while ($row = $this->db->sql_fetchrow($result)) {
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
        $result = $this->db->sql_query(
            "SELECT SUM(hits) as total FROM {$this->prefix}_stats_date 
             WHERE year = " . intval($year) . " AND month = " . intval($month)
        );
        
        if ($row = $this->db->sql_fetchrow($result)) {
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
        
        for ($hour = 0; $hour <= 23; $hour++) {
            $result = $this->db->sql_query(
                "SELECT hour, hits FROM {$this->prefix}_stats_hour 
                 WHERE year = " . intval($year) . " 
                 AND month = " . intval($month) . " 
                 AND date = " . intval($date) . " 
                 AND hour = " . intval($hour)
            );
            
            if ($row = $this->db->sql_fetchrow($result)) {
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
        $result = $this->db->sql_query(
            "SELECT SUM(hits) as total FROM {$this->prefix}_stats_hour 
             WHERE year = " . intval($year) . " 
             AND month = " . intval($month) . " 
             AND date = " . intval($date)
        );
        
        if ($row = $this->db->sql_fetchrow($result)) {
            return intval($row['total']);
        }
        
        return 0;
    }
}
