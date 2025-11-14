<?php

namespace Statistics;

/**
 * View class for rendering statistics HTML output
 */
class StatisticsView
{
    private string $moduleName;
    private string $themeSel;
    private ?array $imageSizes = null;

    public function __construct(string $moduleName, string $themeSel)
    {
        $this->moduleName = $moduleName;
        $this->themeSel = $themeSel;
    }

    /**
     * Load theme bar image sizes (lazy-loaded on first use)
     * 
     * @return array Image dimensions
     */
    private function getImageSizes(): array
    {
        if ($this->imageSizes === null) {
            $this->imageSizes = [
                'left' => @getimagesize("themes/{$this->themeSel}/images/leftbar.gif") ?: [0, 0],
                'main' => @getimagesize("themes/{$this->themeSel}/images/mainbar.gif") ?: [0, 0],
                'right' => @getimagesize("themes/{$this->themeSel}/images/rightbar.gif") ?: [0, 0]
            ];
        }
        return $this->imageSizes;
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
        
        ob_start();
        ?>
<div style="text-align: center;"><span class="option"><b><?= htmlspecialchars($sitename) ?> <?= _STATS ?></b></span><br><br>
<?= _WERECEIVED ?> <b><?= htmlspecialchars((string)$totalHits) ?></b> <?= _PAGESVIEWS ?> <?= htmlspecialchars($startDate) ?><br><br>
[ <a href="modules.php?name=<?= htmlspecialchars($this->moduleName) ?>&op=Stats"><?= _VIEWDETAILED ?></a> ]</div>
        <?php
        echo ob_get_clean();
        
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
        
        ob_start();
        ?>
<table style="border-spacing: 0; padding: 2px; border: 0;" style="text-align: center;"><tr><td colspan="2">
<div style="text-align: center;"><span style="color: <?= htmlspecialchars($textcolor2) ?>;"><b><?= _BROWSERS ?></b></span></div><br></td></tr>
        <?php
        echo ob_get_clean();
        
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
        
        ob_start();
        ?>
<tr><td><img src="modules/<?= htmlspecialchars($this->moduleName) ?>/images/<?= htmlspecialchars($icon) ?>" style="border: 0;" alt="">&nbsp;<?= htmlspecialchars($label) ?>: </td>
<td>
        <?php
        $this->renderBar($altText, $width);
        ?>
 <?= htmlspecialchars((string)$data['percentage']) ?>% (<?= htmlspecialchars((string)$data['count']) ?>)</td></tr>
        <?php
        echo ob_get_clean();
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
        
        ob_start();
        ?>
<table style="border-spacing: 0; padding: 2px; border: 0;" style="text-align: center;"><tr><td colspan="2">
<div style="text-align: center;"><span style="color: <?= htmlspecialchars($textcolor2) ?>;"><b><?= _OPERATINGSYS ?></b></span></div><br></td></tr>
        <?php
        echo ob_get_clean();
        
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
        
        ob_start();
        ?>
<tr><td><img src="modules/<?= htmlspecialchars($this->moduleName) ?>/images/<?= htmlspecialchars($icon) ?>" style="border: 0;" alt="">&nbsp;<?= htmlspecialchars($label) ?>:</td>
<td>
        <?php
        $this->renderBar($label, $width);
        ?>
 <?= htmlspecialchars((string)$data['percentage']) ?>% (<?= htmlspecialchars((string)$data['count']) ?>)</td></tr>
        <?php
        echo ob_get_clean();
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
        
        ob_start();
        ?>
<table style="border-spacing: 0; padding: 2px; border: 0;" style="text-align: center;"><tr><td colspan="2">
<div style="text-align: center;"><span style="color: <?= htmlspecialchars($textcolor2) ?>;"><b><?= _MISCSTATS ?></b></span></div><br></td></tr>
        
<tr><td><img src="modules/<?= htmlspecialchars($this->moduleName) ?>/images/users.gif" style="border: 0;" alt="">&nbsp;<?= _REGUSERS ?></td><td><b><?= htmlspecialchars((string)$counts['users']) ?></b></td></tr>
<tr><td><img src="modules/<?= htmlspecialchars($this->moduleName) ?>/images/authors.gif" style="border: 0;" alt="">&nbsp;<?= _ACTIVEAUTHORS ?></td><td><b><?= htmlspecialchars((string)$counts['authors']) ?></b></td></tr>
<tr><td><img src="modules/<?= htmlspecialchars($this->moduleName) ?>/images/news.gif" style="border: 0;" alt="">&nbsp;<?= _STORIESPUBLISHED ?></td><td><b><?= htmlspecialchars((string)$counts['stories']) ?></b></td></tr>
        <?php if ($counts['topics'] > 0): ?>
<tr><td><img src="modules/<?= htmlspecialchars($this->moduleName) ?>/images/topics.gif" style="border: 0;" alt="">&nbsp;<?= _SACTIVETOPICS ?></td><td><b><?= htmlspecialchars((string)$counts['topics']) ?></b></td></tr>
        <?php endif; ?>
<tr><td><img src="modules/<?= htmlspecialchars($this->moduleName) ?>/images/comments.gif" style="border: 0;" alt="">&nbsp;<?= _COMMENTSPOSTED ?></td><td><b><?= htmlspecialchars((string)$counts['comments']) ?></b></td></tr>
        <?php if ($counts['links'] > 0): ?>
<tr><td><img src="modules/<?= htmlspecialchars($this->moduleName) ?>/images/topics.gif" style="border: 0;" alt="">&nbsp;<?= _LINKSINLINKS ?></td><td><b><?= htmlspecialchars((string)$counts['links']) ?></b></td></tr>
<tr><td><img src="modules/<?= htmlspecialchars($this->moduleName) ?>/images/sections.gif" style="border: 0;" alt="">&nbsp;<?= _LINKSCAT ?></td><td><b><?= htmlspecialchars((string)$counts['linkCategories']) ?></b></td></tr>
        <?php endif; ?>
</table>
        <?php
        echo ob_get_clean();
        
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
        
        $monthName = $processor->getMonthName($highestMonth['month']);
        $dayMonthName = $processor->getMonthName($highestDay['month']);
        $hourMonthName = $processor->getMonthName($highestHour['month']);
        $hourRange = $processor->formatHourRange($highestHour['hour']);
        
        ob_start();
        ?>
<div style="text-align: center;"><span class="option"><b><?= htmlspecialchars($sitename) ?> <?= _STATS ?></b></span><br><br>
<?= _WERECEIVED ?> <b><?= htmlspecialchars((string)$total) ?></b> <?= _PAGESVIEWS ?> <?= htmlspecialchars($startDate) ?><br>
<?= _TODAYIS ?>: <?= htmlspecialchars($currentDate[0]) ?>/<?= htmlspecialchars($currentDate[1]) ?>/<?= htmlspecialchars($currentDate[2]) ?><br><br>
<?= _MOSTMONTH ?>: <?= htmlspecialchars($monthName) ?> <?= htmlspecialchars((string)$highestMonth['year']) ?> (<?= htmlspecialchars((string)$highestMonth['hits']) ?> <?= _HITS ?>)<br>
<?= _MOSTDAY ?>: <?= htmlspecialchars((string)$highestDay['date']) ?> <?= htmlspecialchars($dayMonthName) ?> <?= htmlspecialchars((string)$highestDay['year']) ?> (<?= htmlspecialchars((string)$highestDay['hits']) ?> <?= _HITS ?>)<br>
<?= _MOSTHOUR ?>: <?= htmlspecialchars($hourRange) ?> <?= _ON ?> <?= htmlspecialchars($hourMonthName) ?> <?= htmlspecialchars((string)$highestHour['date']) ?>, <?= htmlspecialchars((string)$highestHour['year']) ?> (<?= htmlspecialchars((string)$highestHour['hits']) ?> <?= _HITS ?>)<br><br>
[ <a href="modules.php?name=<?= htmlspecialchars($this->moduleName) ?>"><?= _RETURNBASICSTATS ?></a> ]</div>
        <?php
        echo ob_get_clean();
        
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
        
        ob_start();
        ?>
<br><br>
<div style="text-align: center;"><b><?= _YEARLYSTATS ?></b></div><br>
<table style="text-align: center; background-color: #000000; border-spacing: 1px; padding: 3px; border: 0;">
<tr><td style="width: 25%; background-color: <?= htmlspecialchars($bgcolor2) ?>;"><?= _YEAR ?></td><td style="background-color: <?= htmlspecialchars($bgcolor2) ?>;"><?= _SPAGESVIEWS ?></td></tr>
        <?php foreach ($yearlyStats as $stat):
            $year = $stat['year'];
            $hits = $stat['hits'];
            $width = $processor->calculateBarWidth($hits, $totalHits);
        ?>
<tr style="background-color: <?= htmlspecialchars($bgcolor1) ?>;"><td>
            <?php if ($year != $currentYear): ?>
<a href="modules.php?name=<?= htmlspecialchars($this->moduleName) ?>&amp;op=YearlyStats&amp;year=<?= htmlspecialchars((string)$year) ?>"><?= htmlspecialchars((string)$year) ?></a>
            <?php else: ?>
<?= htmlspecialchars((string)$year) ?>
            <?php endif; ?>
</td><td>
            <?php $this->renderBar('', $width); ?>
 (<?= htmlspecialchars((string)$hits) ?>)</td></tr>
        <?php endforeach; ?>
</table>
        <?php
        echo ob_get_clean();
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
        
        ob_start();
        ?>
<br><br>
<div style="text-align: center;"><b><?= _MONTLYSTATS ?> <?= htmlspecialchars((string)$year) ?></b></div><br>
<table style="text-align: center; background-color: #000000; border-spacing: 1px; padding: 3px; border: 0;">
<tr><td style="width: 25%; background-color: <?= htmlspecialchars($bgcolor2) ?>;"><?= _UMONTH ?></td><td style="background-color: <?= htmlspecialchars($bgcolor2) ?>;"><?= _SPAGESVIEWS ?></td></tr>
        <?php foreach ($monthlyStats as $stat):
            $month = $stat['month'];
            $hits = $stat['hits'];
            $width = $processor->calculateBarWidth($hits, $totalHits);
            $monthName = $processor->getMonthName($month);
        ?>
<tr style="background-color: <?= htmlspecialchars($bgcolor1) ?>;"><td>
            <?php if ($month != $currentMonth): ?>
<a href="modules.php?name=<?= htmlspecialchars($this->moduleName) ?>&amp;op=MonthlyStats&amp;year=<?= htmlspecialchars((string)$year) ?>&amp;month=<?= htmlspecialchars((string)$month) ?>" class="hover_orange"><?= htmlspecialchars($monthName) ?></a>
            <?php else: ?>
<?= htmlspecialchars($monthName) ?>
            <?php endif; ?>
</td><td>
            <?php $this->renderBar('', $width); ?>
 (<?= htmlspecialchars((string)$hits) ?>)</td></tr>
        <?php endforeach; ?>
</table>
        <?php
        echo ob_get_clean();
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
        
        ob_start();
        ?>
<br><br>
<div style="text-align: center;"><b><?= _DAILYSTATS ?> <?= htmlspecialchars($monthName) ?>, <?= htmlspecialchars((string)$year) ?></b></div><br>
<table style="text-align: center; background-color: #000000; border-spacing: 1px; padding: 3px; border: 0;">
<tr><td style="width: 25%; background-color: <?= htmlspecialchars($bgcolor2) ?>;"><?= _DATE ?></td><td style="background-color: <?= htmlspecialchars($bgcolor2) ?>;"><?= _SPAGESVIEWS ?></td></tr>
        <?php foreach ($dailyStats as $stat):
            $date = $stat['date'];
            $hits = $stat['hits'];
            $percentage = $processor->calculatePercentage($hits, $totalHits, 3);
            $width = $processor->calculateBarWidth($hits, $totalHits);
        ?>
<tr style="background-color: <?= htmlspecialchars($bgcolor1) ?>;"><td>
            <?php if ($date != $currentDate): ?>
<a href="modules.php?name=<?= htmlspecialchars($this->moduleName) ?>&amp;op=DailyStats&amp;year=<?= htmlspecialchars((string)$year) ?>&amp;month=<?= htmlspecialchars((string)$month) ?>&amp;date=<?= htmlspecialchars((string)$date) ?>" class="hover_orange"><?= htmlspecialchars((string)$date) ?></a>
            <?php else: ?>
<?= htmlspecialchars((string)$date) ?>
            <?php endif; ?>
</td><td>
            <?php $this->renderBar('', $width); ?>
 <?= htmlspecialchars($percentage) ?>% (<?= htmlspecialchars((string)$hits) ?>)</td></tr>
        <?php endforeach; ?>
</table>
        <?php
        echo ob_get_clean();
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
        
        ob_start();
        ?>
<br><br>
<div style="text-align: center;"><b><?= _HOURLYSTATS ?> <?= htmlspecialchars($monthName) ?> <?= htmlspecialchars((string)$date) ?>, <?= htmlspecialchars((string)$year) ?></b></div><br>
<table style="text-align: center; background-color: #000000; border-spacing: 1px; padding: 3px; border: 0;">
<tr><td style="width: 25%; background-color: <?= htmlspecialchars($bgcolor2) ?>;"><?= _HOUR ?></td><td style="width: 70%; background-color: <?= htmlspecialchars($bgcolor2) ?>;"><?= _SPAGESVIEWS ?></td></tr>
        <?php for ($hour = 0; $hour <= 23; $hour++):
            $hits = $hourlyStats[$hour];
            $percentage = $processor->calculatePercentage($hits, $totalHits, 3);
            $width = $processor->calculateBarWidth($hits, $totalHits);
            $hourRange = $processor->formatHourRange($hour);
        ?>
<tr><td style="background-color: <?= htmlspecialchars($bgcolor1) ?>;"><?= htmlspecialchars($hourRange) ?></td>
<td style="background-color: <?= htmlspecialchars($bgcolor1) ?>;">
            <?php $this->renderBar('', $width); ?>
 <?= htmlspecialchars($percentage) ?>% (<?= htmlspecialchars((string)$hits) ?>)</td></tr>
        <?php endfor; ?>
</table>
        <?php
        echo ob_get_clean();
    }

    /**
     * Render navigation links for detailed stats pages
     * 
     * @return void
     */
    public function renderBackLinks(): void
    {
        ob_start();
        ?>
<br><br><div style="text-align: center;"><?= _GOBACK ?></div><br><br>
        <?php
        echo ob_get_clean();
    }

    /**
     * Render page navigation for year/month/day views
     * 
     * @return void
     */
    public function renderDetailNavigation(): void
    {
        ob_start();
        ?>
<br>
<div style="text-align: center;">[ <a href="modules.php?name=<?= htmlspecialchars($this->moduleName) ?>"><?= _BACKTOMAIN ?></a> | <a href="modules.php?name=<?= htmlspecialchars($this->moduleName) ?>&amp;op=Stats"><?= _BACKTODETSTATS ?></a> ]</div>
        <?php
        echo ob_get_clean();
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
        $imageSizes = $this->getImageSizes();
        $l = $imageSizes['left'];
        $m = $imageSizes['main'];
        $r = $imageSizes['right'];
        
        ob_start();
        ?>
<img src="themes/<?= htmlspecialchars($this->themeSel) ?>/images/leftbar.gif" alt="<?= htmlspecialchars($alt) ?>" style="width: <?= $l[0] ?>px; height: <?= $l[1] ?>px;"><img src="themes/<?= htmlspecialchars($this->themeSel) ?>/images/mainbar.gif" alt="<?= htmlspecialchars($alt) ?>" style="height: <?= $m[1] ?>px; width: <?= $width ?>px;"><img src="themes/<?= htmlspecialchars($this->themeSel) ?>/images/rightbar.gif" alt="<?= htmlspecialchars($alt) ?>" style="width: <?= $r[0] ?>px; height: <?= $r[1] ?>px;">
        <?php
        echo ob_get_clean();
    }
}
