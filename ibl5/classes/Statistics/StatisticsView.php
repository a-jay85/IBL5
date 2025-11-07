<?php

namespace Statistics;

/**
 * View class for rendering statistics HTML output
 */
class StatisticsView
{
    private string $moduleName;
    private string $themeSel;
    private array $imageSizes;

    public function __construct(string $moduleName, string $themeSel)
    {
        $this->moduleName = $moduleName;
        $this->themeSel = $themeSel;
        $this->imageSizes = $this->loadImageSizes();
    }

    /**
     * Load theme bar image sizes
     * 
     * @return array Image dimensions
     */
    private function loadImageSizes(): array
    {
        return [
            'left' => getimagesize("themes/{$this->themeSel}/images/leftbar.gif"),
            'main' => getimagesize("themes/{$this->themeSel}/images/mainbar.gif"),
            'right' => getimagesize("themes/{$this->themeSel}/images/rightbar.gif")
        ];
    }

    /**
     * Render main statistics summary page
     * 
     * @param int $totalHits Total page views
     * @param string $startDate Site start date
     * @param array $browserStats Processed browser statistics
     * @param array $osStats Processed OS statistics
     * @param array $miscCounts Miscellaneous counts
     * @return void
     */
    public function renderMainStats(
        int $totalHits,
        string $startDate,
        array $browserStats,
        array $osStats,
        array $miscCounts
    ): void {
        global $sitename, $textcolor2;
        
        \Nuke\Header::header();
        title("$sitename " . _STATS);
        OpenTable();
        OpenTable();
        
        echo "<center><font class=\"option\"><b>$sitename " . _STATS . "</b></font><br><br>" 
            . _WERECEIVED . " <b>$totalHits</b> " . _PAGESVIEWS . " $startDate<br><br>"
            . "[ <a href=\"modules.php?name={$this->moduleName}&op=Stats\">" . _VIEWDETAILED . "</a> ]</center>";
        
        CloseTable();
        echo "<br><br>";
        
        $this->renderBrowserStats($browserStats);
        echo "<br><br>\n";
        
        $this->renderOSStats($osStats);
        echo "<br><br>\n";
        
        $this->renderMiscStats($miscCounts);
        
        CloseTable();
        \Nuke\Footer::footer();
    }

    /**
     * Render browser statistics table
     * 
     * @param array $browserStats Processed browser data
     * @return void
     */
    private function renderBrowserStats(array $browserStats): void
    {
        global $textcolor2;
        
        OpenTable2();
        echo "<table cellspacing=\"0\" cellpadding=\"2\" border=\"0\" align=\"center\"><tr><td colspan=\"2\">\n";
        echo "<center><font color=\"$textcolor2\"><b>" . _BROWSERS . "</b></font></center><br></td></tr>\n";
        
        $this->renderBrowserRow('MSIE', 'explorer.gif', 'Internet Explorer', $browserStats['MSIE'] ?? ['count' => 0, 'percentage' => 0]);
        $this->renderBrowserRow('FireFox', 'firefox.gif', 'FireFox', $browserStats['FireFox'] ?? ['count' => 0, 'percentage' => 0]);
        $this->renderBrowserRow('Netscape', 'netscape.gif', 'Netscape', $browserStats['Netscape'] ?? ['count' => 0, 'percentage' => 0]);
        $this->renderBrowserRow('Opera', 'opera.gif', 'Opera', $browserStats['Opera'] ?? ['count' => 0, 'percentage' => 0]);
        $this->renderBrowserRow('Konqueror', 'konqueror.gif', 'Konqueror (KDE)', $browserStats['Konqueror'] ?? ['count' => 0, 'percentage' => 0]);
        $this->renderBrowserRow('Lynx', 'lynx.gif', 'Lynx', $browserStats['Lynx'] ?? ['count' => 0, 'percentage' => 0]);
        $this->renderBrowserRow(_SEARCHENGINES, 'altavista.gif', _BOTS, $browserStats['Bot'] ?? ['count' => 0, 'percentage' => 0]);
        $this->renderBrowserRow(_UNKNOWN, 'question.gif', _OTHER, $browserStats['Other'] ?? ['count' => 0, 'percentage' => 0]);
        
        echo "</table>";
        CloseTable2();
    }

    /**
     * Render a single browser statistics row
     * 
     * @param string $label Display label
     * @param string $icon Icon filename
     * @param string $altText Alt text for images
     * @param array $data Browser data with count and percentage
     * @return void
     */
    private function renderBrowserRow(string $label, string $icon, string $altText, array $data): void
    {
        $width = (int)($data['percentage'] * 2);
        
        echo "<tr><td><img src=\"modules/{$this->moduleName}/images/{$icon}\" border=\"0\" alt=\"\">&nbsp;{$label}: </td>";
        echo "<td>";
        $this->renderBar($altText, $width);
        echo " {$data['percentage']}% ({$data['count']})</td></tr>\n";
    }

    /**
     * Render operating system statistics table
     * 
     * @param array $osStats Processed OS data
     * @return void
     */
    private function renderOSStats(array $osStats): void
    {
        global $textcolor2;
        
        OpenTable2();
        echo "<table cellspacing=\"0\" cellpadding=\"2\" border=\"0\" align=\"center\"><tr><td colspan=\"2\">\n";
        echo "<center><font color=\"$textcolor2\"><b>" . _OPERATINGSYS . "</b></font></center><br></td></tr>\n";
        
        $this->renderOSRow('Windows', 'windows.gif', $osStats['Windows'] ?? ['count' => 0, 'percentage' => 0]);
        $this->renderOSRow('Linux', 'linux.gif', $osStats['Linux'] ?? ['count' => 0, 'percentage' => 0]);
        $this->renderOSRow('Mac/PPC', 'mac.gif', $osStats['Mac'] ?? ['count' => 0, 'percentage' => 0]);
        $this->renderOSRow('FreeBSD', 'bsd.gif', $osStats['FreeBSD'] ?? ['count' => 0, 'percentage' => 0]);
        $this->renderOSRow('SunOS', 'sun.gif', $osStats['SunOS'] ?? ['count' => 0, 'percentage' => 0]);
        $this->renderOSRow('IRIX', 'irix.gif', $osStats['IRIX'] ?? ['count' => 0, 'percentage' => 0]);
        $this->renderOSRow('BeOS', 'be.gif', $osStats['BeOS'] ?? ['count' => 0, 'percentage' => 0]);
        $this->renderOSRow('OS/2', 'os2.gif', $osStats['OS/2'] ?? ['count' => 0, 'percentage' => 0]);
        $this->renderOSRow('AIX', 'aix.gif', $osStats['AIX'] ?? ['count' => 0, 'percentage' => 0]);
        $this->renderOSRow(_UNKNOWN, 'question.gif', $osStats['Other'] ?? ['count' => 0, 'percentage' => 0]);
        
        echo "</table>\n";
        CloseTable2();
    }

    /**
     * Render a single OS statistics row
     * 
     * @param string $label Display label
     * @param string $icon Icon filename
     * @param array $data OS data with count and percentage
     * @return void
     */
    private function renderOSRow(string $label, string $icon, array $data): void
    {
        $width = (int)($data['percentage'] * 2);
        
        echo "<tr><td><img src=\"modules/{$this->moduleName}/images/{$icon}\" border=\"0\" alt=\"\">&nbsp;{$label}:</td>";
        echo "<td>";
        $this->renderBar($label, $width);
        echo " {$data['percentage']}% ({$data['count']})</td></tr>\n";
    }

    /**
     * Render miscellaneous statistics
     * 
     * @param array $counts Miscellaneous count data
     * @return void
     */
    private function renderMiscStats(array $counts): void
    {
        global $textcolor2;
        
        OpenTable2();
        echo "<table cellspacing=\"0\" cellpadding=\"2\" border=\"0\" align=\"center\"><tr><td colspan=\"2\">\n";
        echo "<center><font color=\"$textcolor2\"><b>" . _MISCSTATS . "</b></font></center><br></td></tr>\n";
        
        echo "<tr><td><img src=\"modules/{$this->moduleName}/images/users.gif\" border=\"0\" alt=\"\">&nbsp;" . _REGUSERS . "</td><td><b>{$counts['users']}</b></td></tr>\n";
        echo "<tr><td><img src=\"modules/{$this->moduleName}/images/authors.gif\" border=\"0\" alt=\"\">&nbsp;" . _ACTIVEAUTHORS . "</td><td><b>{$counts['authors']}</b></td></tr>\n";
        echo "<tr><td><img src=\"modules/{$this->moduleName}/images/news.gif\" border=\"0\" alt=\"\">&nbsp;" . _STORIESPUBLISHED . "</td><td><b>{$counts['stories']}</b></td></tr>\n";
        
        if ($counts['topics'] > 0) {
            echo "<tr><td><img src=\"modules/{$this->moduleName}/images/topics.gif\" border=\"0\" alt=\"\">&nbsp;" . _SACTIVETOPICS . "</td><td><b>{$counts['topics']}</b></td></tr>\n";
        }
        
        echo "<tr><td><img src=\"modules/{$this->moduleName}/images/comments.gif\" border=\"0\" alt=\"\">&nbsp;" . _COMMENTSPOSTED . "</td><td><b>{$counts['comments']}</b></td></tr>\n";
        
        if ($counts['links'] > 0) {
            echo "<tr><td><img src=\"modules/{$this->moduleName}/images/topics.gif\" border=\"0\" alt=\"\">&nbsp;" . _LINKSINLINKS . "</td><td><b>{$counts['links']}</b></td></tr>\n";
            echo "<tr><td><img src=\"modules/{$this->moduleName}/images/sections.gif\" border=\"0\" alt=\"\">&nbsp;" . _LINKSCAT . "</td><td><b>{$counts['linkCategories']}</b></td></tr>\n";
        }
        
        echo "</table>\n";
        CloseTable2();
    }

    /**
     * Render detailed statistics page
     * 
     * @param int $total Total hits
     * @param string $startDate Site start date
     * @param array $currentDate Current date parts [date, month, year]
     * @param array $highestMonth Highest traffic month
     * @param array $highestDay Highest traffic day
     * @param array $highestHour Highest traffic hour
     * @param StatisticsProcessor $processor Processor for formatting
     * @return void
     */
    public function renderDetailedStats(
        int $total,
        string $startDate,
        array $currentDate,
        array $highestMonth,
        array $highestDay,
        array $highestHour,
        StatisticsProcessor $processor
    ): void {
        global $sitename;
        
        \Nuke\Header::header();
        title("$sitename " . _STATS);
        
        $total++;
        OpenTable();
        OpenTable();
        
        echo "<center><font class=\"option\"><b>$sitename " . _STATS . "</b></font><br><br>"
            . _WERECEIVED . " <b>$total</b> " . _PAGESVIEWS . " $startDate<br>"
            . _TODAYIS . ": {$currentDate[0]}/{$currentDate[1]}/{$currentDate[2]}<br><br>";
        
        // Highest month
        $monthName = $processor->getMonthName($highestMonth['month']);
        echo _MOSTMONTH . ": {$monthName} {$highestMonth['year']} ({$highestMonth['hits']} " . _HITS . ")<br>";
        
        // Highest day
        $monthName = $processor->getMonthName($highestDay['month']);
        echo _MOSTDAY . ": {$highestDay['date']} {$monthName} {$highestDay['year']} ({$highestDay['hits']} " . _HITS . ")<br>";
        
        // Highest hour
        $monthName = $processor->getMonthName($highestHour['month']);
        $hourRange = $processor->formatHourRange($highestHour['hour']);
        echo _MOSTHOUR . ": {$hourRange} " . _ON . " {$monthName} {$highestHour['date']}, {$highestHour['year']} ({$highestHour['hits']} " . _HITS . ")<br><br>";
        
        echo "[ <a href=\"modules.php?name={$this->moduleName}\">" . _RETURNBASICSTATS . "</a> ]</center>";
        
        CloseTable();
    }

    /**
     * Render yearly statistics table
     * 
     * @param array $yearlyStats Yearly statistics data
     * @param int $totalHits Total yearly hits
     * @param int $currentYear Current year
     * @param StatisticsProcessor $processor Processor for calculations
     * @return void
     */
    public function renderYearlyStats(
        array $yearlyStats,
        int $totalHits,
        int $currentYear,
        StatisticsProcessor $processor
    ): void {
        global $bgcolor1, $bgcolor2;
        
        echo "<br><br>";
        echo "<center><b>" . _YEARLYSTATS . "</b></center><br>";
        echo "<table align=\"center\" bgcolor=\"#000000\" cellspacing=\"1\" cellpadding=\"3\" border=\"0\">";
        echo "<tr><td width=\"25%\" bgcolor=\"$bgcolor2\">" . _YEAR . "</td><td bgcolor=\"$bgcolor2\">" . _SPAGESVIEWS . "</td></tr>";
        
        foreach ($yearlyStats as $stat) {
            $year = $stat['year'];
            $hits = $stat['hits'];
            $width = $processor->calculateBarWidth($hits, $totalHits);
            
            echo "<tr bgcolor=\"$bgcolor1\"><td>";
            if ($year != $currentYear) {
                echo "<a href=\"modules.php?name={$this->moduleName}&amp;op=YearlyStats&amp;year={$year}\">{$year}</a>";
            } else {
                echo $year;
            }
            echo "</td><td>";
            $this->renderBar('', $width);
            echo " ({$hits})</td></tr>";
        }
        
        echo "</table>";
    }

    /**
     * Render monthly statistics table
     * 
     * @param array $monthlyStats Monthly statistics data
     * @param int $totalHits Total monthly hits
     * @param int $year Year being displayed
     * @param int $currentMonth Current month
     * @param StatisticsProcessor $processor Processor for calculations
     * @return void
     */
    public function renderMonthlyStats(
        array $monthlyStats,
        int $totalHits,
        int $year,
        int $currentMonth,
        StatisticsProcessor $processor
    ): void {
        global $bgcolor1, $bgcolor2;
        
        echo "<br><br>";
        echo "<center><b>" . _MONTLYSTATS . " {$year}</b></center><br>";
        echo "<table align=\"center\" bgcolor=\"#000000\" cellspacing=\"1\" cellpadding=\"3\" border=\"0\">";
        echo "<tr><td width=\"25%\" bgcolor=\"$bgcolor2\">" . _UMONTH . "</td><td bgcolor=\"$bgcolor2\">" . _SPAGESVIEWS . "</td></tr>";
        
        foreach ($monthlyStats as $stat) {
            $month = $stat['month'];
            $hits = $stat['hits'];
            $width = $processor->calculateBarWidth($hits, $totalHits);
            $monthName = $processor->getMonthName($month);
            
            echo "<tr bgcolor=\"$bgcolor1\"><td>";
            if ($month != $currentMonth) {
                echo "<a href=\"modules.php?name={$this->moduleName}&amp;op=MonthlyStats&amp;year={$year}&amp;month={$month}\" class=\"hover_orange\">{$monthName}</a>";
            } else {
                echo $monthName;
            }
            echo "</td><td>";
            $this->renderBar('', $width);
            echo " ({$hits})</td></tr>";
        }
        
        echo "</table>";
    }

    /**
     * Render daily statistics table
     * 
     * @param array $dailyStats Daily statistics data
     * @param int $totalHits Total daily hits
     * @param int $year Year being displayed
     * @param int $month Month being displayed
     * @param int $currentDate Current date
     * @param StatisticsProcessor $processor Processor for calculations
     * @return void
     */
    public function renderDailyStats(
        array $dailyStats,
        int $totalHits,
        int $year,
        int $month,
        int $currentDate,
        StatisticsProcessor $processor
    ): void {
        global $bgcolor1, $bgcolor2;
        
        $monthName = $processor->getMonthName($month);
        
        echo "<br><br>";
        echo "<center><b>" . _DAILYSTATS . " {$monthName}, {$year}</b></center><br>";
        echo "<table align=\"center\" bgcolor=\"#000000\" cellspacing=\"1\" cellpadding=\"3\" border=\"0\">";
        echo "<tr><td width=\"25%\" bgcolor=\"$bgcolor2\">" . _DATE . "</td><td bgcolor=\"$bgcolor2\">" . _SPAGESVIEWS . "</td></tr>";
        
        foreach ($dailyStats as $stat) {
            $date = $stat['date'];
            $hits = $stat['hits'];
            $percentage = $processor->calculatePercentage($hits, $totalHits, 3);
            $width = $processor->calculateBarWidth($hits, $totalHits);
            
            echo "<tr bgcolor=\"$bgcolor1\"><td>";
            if ($date != $currentDate) {
                echo "<a href=\"modules.php?name={$this->moduleName}&amp;op=DailyStats&amp;year={$year}&amp;month={$month}&amp;date={$date}\" class=\"hover_orange\">{$date}</a>";
            } else {
                echo $date;
            }
            echo "</td><td>";
            $this->renderBar('', $width);
            echo " {$percentage}% ({$hits})</td></tr>";
        }
        
        echo "</table>";
    }

    /**
     * Render hourly statistics table
     * 
     * @param array $hourlyStats Hourly statistics data (array indexed 0-23)
     * @param int $totalHits Total hourly hits
     * @param int $year Year being displayed
     * @param int $month Month being displayed
     * @param int $date Date being displayed
     * @param StatisticsProcessor $processor Processor for calculations
     * @return void
     */
    public function renderHourlyStats(
        array $hourlyStats,
        int $totalHits,
        int $year,
        int $month,
        int $date,
        StatisticsProcessor $processor
    ): void {
        global $bgcolor1, $bgcolor2;
        
        $monthName = $processor->getMonthName($month);
        
        echo "<br><br>";
        echo "<center><b>" . _HOURLYSTATS . " {$monthName} {$date}, {$year}</b></center><br>";
        echo "<table align=\"center\" bgcolor=\"#000000\" cellspacing=\"1\" cellpadding=\"3\" border=\"0\">";
        echo "<tr><td width=\"25%\" bgcolor=\"$bgcolor2\">" . _HOUR . "</td><td bgcolor=\"$bgcolor2\" width=\"70%\">" . _SPAGESVIEWS . "</td></tr>";
        
        for ($hour = 0; $hour <= 23; $hour++) {
            $hits = $hourlyStats[$hour];
            $percentage = $processor->calculatePercentage($hits, $totalHits, 3);
            $width = $processor->calculateBarWidth($hits, $totalHits);
            $hourRange = $processor->formatHourRange($hour);
            
            echo "<tr><td bgcolor=\"$bgcolor1\">{$hourRange}</td>";
            echo "<td bgcolor=\"$bgcolor1\">";
            $this->renderBar('', $width);
            echo " {$percentage}% ({$hits})</td></tr>";
        }
        
        echo "</table>";
    }

    /**
     * Render navigation links for detailed stats pages
     * 
     * @return void
     */
    public function renderBackLinks(): void
    {
        echo "<br><br><center>" . _GOBACK . "</center><br><br>";
    }

    /**
     * Render page navigation for year/month/day views
     * 
     * @return void
     */
    public function renderDetailNavigation(): void
    {
        echo "<BR>";
        echo "<center>[ <a href=\"modules.php?name={$this->moduleName}\">" . _BACKTOMAIN . "</a> | <a href=\"modules.php?name={$this->moduleName}&amp;op=Stats\">" . _BACKTODETSTATS . "</a> ]</center>";
    }

    /**
     * Render a colored bar for visual statistics
     * 
     * @param string $alt Alt text
     * @param int $width Width in pixels
     * @return void
     */
    private function renderBar(string $alt, int $width): void
    {
        $l = $this->imageSizes['left'];
        $m = $this->imageSizes['main'];
        $r = $this->imageSizes['right'];
        
        echo "<img src=\"themes/{$this->themeSel}/images/leftbar.gif\" Alt=\"{$alt}\" width=\"{$l[0]}\" height=\"{$l[1]}\">";
        echo "<img src=\"themes/{$this->themeSel}/images/mainbar.gif\" Alt=\"{$alt}\" height=\"{$m[1]}\" width=\"{$width}\">";
        echo "<img src=\"themes/{$this->themeSel}/images/rightbar.gif\" Alt=\"{$alt}\" width=\"{$r[0]}\" height=\"{$r[1]}\">";
    }
}
