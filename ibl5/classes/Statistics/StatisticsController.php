<?php

namespace Statistics;

/**
 * Controller for site statistics module
 * Orchestrates data retrieval, processing, and view rendering
 */
class StatisticsController
{
    private $db;
    private StatisticsRepository $repository;
    private StatisticsProcessor $processor;
    private StatisticsView $view;
    private string $moduleName;

    public function __construct($db, string $moduleName, string $themeSel)
    {
        $this->db = $db;
        $this->moduleName = $moduleName;
        $this->repository = new StatisticsRepository($db);
        $this->processor = new StatisticsProcessor();
        $this->view = new StatisticsView($moduleName, $themeSel);
    }

    /**
     * Display main statistics summary
     * 
     * @return void
     */
    public function showMainStats(): void
    {
        global $startdate;
        
        $counters = $this->repository->getAllCounters();
        $total = $counters['total'];
        
        $browserStats = $this->processor->processBrowserStats($counters['browsers'], $total);
        $osStats = $this->processor->processOSStats($counters['os'], $total);
        $miscCounts = $this->repository->getMiscCounts();
        
        $this->view->renderMainStats(
            $total,
            $startdate,
            $browserStats,
            $osStats,
            $miscCounts
        );
    }

    /**
     * Display detailed statistics with yearly, monthly, daily, and hourly breakdowns
     * 
     * @return void
     */
    public function showDetailedStats(): void
    {
        global $startdate;
        
        $now = date("d-m-Y");
        $parts = explode("-", $now);
        $currentDate = [(int)$parts[0], (int)$parts[1], (int)$parts[2]];
        
        $total = $this->repository->getTotalHits();
        $highestMonth = $this->repository->getHighestTrafficMonth();
        $highestDay = $this->repository->getHighestTrafficDay();
        $highestHour = $this->repository->getHighestTrafficHour();
        
        // Render header with peak stats
        $this->view->renderDetailedStats(
            $total,
            $startdate,
            $currentDate,
            $highestMonth,
            $highestDay,
            $highestHour,
            $this->processor
        );
        
        // Show yearly stats
        echo "<br><br>";
        $yearlyStats = $this->repository->getYearlyStats();
        $totalYearlyHits = $this->repository->getTotalYearlyHits();
        $this->view->renderYearlyStats(
            $yearlyStats,
            $totalYearlyHits,
            $currentDate[2],
            $this->processor
        );
        
        // Show monthly stats for current year
        echo "<BR><BR>";
        $monthlyStats = $this->repository->getMonthlyStats($currentDate[2]);
        $totalMonthlyHits = $this->repository->getTotalMonthlyHits($currentDate[2]);
        $this->view->renderMonthlyStats(
            $monthlyStats,
            $totalMonthlyHits,
            $currentDate[2],
            $currentDate[1],
            $this->processor
        );
        
        // Show daily stats for current month
        echo "<BR><BR>";
        $dailyStats = $this->repository->getDailyStats($currentDate[2], $currentDate[1]);
        $totalDailyHits = $this->repository->getTotalDailyHits($currentDate[2], $currentDate[1]);
        $this->view->renderDailyStats(
            $dailyStats,
            $totalDailyHits,
            $currentDate[2],
            $currentDate[1],
            $currentDate[0],
            $this->processor
        );
        
        // Show hourly stats for current day
        echo "<BR><BR>";
        $hourlyStats = $this->repository->getHourlyStats($currentDate[2], $currentDate[1], $currentDate[0]);
        $totalHourlyHits = $this->repository->getTotalHourlyHits($currentDate[2], $currentDate[1], $currentDate[0]);
        $this->view->renderHourlyStats(
            $hourlyStats,
            $totalHourlyHits,
            $currentDate[2],
            $currentDate[1],
            $currentDate[0],
            $this->processor
        );
        
        $this->view->renderBackLinks();
        
        CloseTable();
        \Nuke\Footer::footer();
    }

    /**
     * Display yearly statistics for a specific year
     * 
     * @param int $year Year to display
     * @return void
     */
    public function showYearlyStats(int $year): void
    {
        global $sitename;
        
        $now = date("d-m-Y");
        $parts = explode("-", $now);
        $currentMonth = (int)$parts[1];
        
        \Nuke\Header::header();
        title("$sitename " . _STATS);
        opentable();
        
        $monthlyStats = $this->repository->getMonthlyStats($year);
        $totalMonthlyHits = $this->repository->getTotalMonthlyHits($year);
        
        $this->view->renderMonthlyStats(
            $monthlyStats,
            $totalMonthlyHits,
            $year,
            $currentMonth,
            $this->processor
        );
        
        $this->view->renderDetailNavigation();
        
        closetable();
        \Nuke\Footer::footer();
    }

    /**
     * Display monthly statistics for a specific year and month
     * 
     * @param int $year Year to display
     * @param int $month Month to display
     * @return void
     */
    public function showMonthlyStats(int $year, int $month): void
    {
        global $sitename;
        
        $now = date("d-m-Y");
        $parts = explode("-", $now);
        $currentDate = (int)$parts[0];
        
        \Nuke\Header::header();
        title("$sitename " . _STATS);
        opentable();
        
        $dailyStats = $this->repository->getDailyStats($year, $month);
        $totalDailyHits = $this->repository->getTotalDailyHits($year, $month);
        
        $this->view->renderDailyStats(
            $dailyStats,
            $totalDailyHits,
            $year,
            $month,
            $currentDate,
            $this->processor
        );
        
        $this->view->renderDetailNavigation();
        
        closetable();
        \Nuke\Footer::footer();
    }

    /**
     * Display daily statistics for a specific date
     * 
     * @param int $year Year to display
     * @param int $month Month to display
     * @param int $date Day to display
     * @return void
     */
    public function showDailyStats(int $year, int $month, int $date): void
    {
        global $sitename;
        
        \Nuke\Header::header();
        title("$sitename " . _STATS);
        opentable();
        
        $hourlyStats = $this->repository->getHourlyStats($year, $month, $date);
        $totalHourlyHits = $this->repository->getTotalHourlyHits($year, $month, $date);
        
        $this->view->renderHourlyStats(
            $hourlyStats,
            $totalHourlyHits,
            $year,
            $month,
            $date,
            $this->processor
        );
        
        $this->view->renderDetailNavigation();
        
        closetable();
        \Nuke\Footer::footer();
    }
}
