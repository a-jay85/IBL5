<?php

namespace Statistics;

/**
 * Processor for statistics calculations and data transformations
 */
class StatisticsProcessor
{
    /**
     * Calculate percentage with count and total
     * 
     * @param int $count Count value
     * @param int $total Total value
     * @param int $precision Number of decimal places
     * @return float Calculated percentage
     */
    public function calculatePercentage(int $count, int $total, int $precision = 2): float
    {
        if ($total === 0) {
            return 0.0;
        }
        
        return round(100 * $count / $total, $precision);
    }

    /**
     * Process browser statistics with percentages
     * 
     * @param array $browsers Raw browser data
     * @param int $total Total hits
     * @return array Processed browser data with counts and percentages
     */
    public function processBrowserStats(array $browsers, int $total): array
    {
        $processed = [];
        
        $browserList = ['FireFox', 'Netscape', 'MSIE', 'Konqueror', 'Opera', 'Lynx', 'Bot', 'Other'];
        
        foreach ($browserList as $browser) {
            $count = $browsers[$browser] ?? 0;
            $percentage = $this->calculatePercentage($count, $total, 3);
            
            $processed[$browser] = [
                'count' => $count,
                'percentage' => $percentage
            ];
        }
        
        return $processed;
    }

    /**
     * Process operating system statistics with percentages
     * 
     * @param array $osList Raw OS data
     * @param int $total Total hits
     * @return array Processed OS data with counts and percentages
     */
    public function processOSStats(array $osList, int $total): array
    {
        $processed = [];
        
        $systems = ['Windows', 'Mac', 'Linux', 'FreeBSD', 'SunOS', 'IRIX', 'BeOS', 'OS/2', 'AIX', 'Other'];
        
        foreach ($systems as $os) {
            $count = $osList[$os] ?? 0;
            $percentage = $this->calculatePercentage($count, $total, 3);
            
            $processed[$os] = [
                'count' => $count,
                'percentage' => $percentage
            ];
        }
        
        return $processed;
    }

    /**
     * Get month name from month number
     * 
     * @param int $month Month number (1-12)
     * @return string Month name
     */
    public function getMonthName(int $month): string
    {
        $months = [
            1 => _JANUARY,
            2 => _FEBRUARY,
            3 => _MARCH,
            4 => _APRIL,
            5 => _MAY,
            6 => _JUNE,
            7 => _JULY,
            8 => _AUGUST,
            9 => _SEPTEMBER,
            10 => _OCTOBER,
            11 => _NOVEMBER,
            12 => _DECEMBER
        ];
        
        return $months[$month] ?? '';
    }

    /**
     * Format hour range for display
     * 
     * @param int $hour Hour (0-23)
     * @return string Formatted hour range (e.g., "09:00 - 09:59")
     */
    public function formatHourRange(int $hour): string
    {
        $hourStr = $hour < 10 ? "0{$hour}" : (string)$hour;
        return "{$hourStr}:00 - {$hourStr}:59";
    }

    /**
     * Calculate bar width for visual representation
     * 
     * @param int $value Current value
     * @param int $total Total value
     * @param int $multiplier Width multiplier (default 2)
     * @return int Calculated width in pixels
     */
    public function calculateBarWidth(int $value, int $total, int $multiplier = 2): int
    {
        if ($total === 0) {
            return 0;
        }
        
        return (int)round(100 * $value / $total) * $multiplier;
    }
}
