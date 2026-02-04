<?php

declare(strict_types=1);

namespace Updater;

use Utilities\UuidGenerator;
use Utilities\ScheduleParser;
use Utilities\DateParser;

class ScheduleUpdater extends \BaseMysqliRepository {
    private \Services\CommonMysqliRepository $commonRepository;
    private \Season $season;

    public function __construct(object $db, \Services\CommonMysqliRepository $commonRepository, \Season $season) {
        parent::__construct($db);
        $this->commonRepository = $commonRepository;
        $this->season = $season;
    }

    /**
     * @return array{date: string, year: int, month: int}|null
     */
    protected function extractDate(string $rawDate): ?array {
        global $leagueContext;
        /** @var \League\LeagueContext|null $leagueContext */
        $currentLeague = $leagueContext !== null ? $leagueContext->getCurrentLeague() : 'IBL';

        if ($rawDate === '') {
            return null;
        }

        // Handle Preseason year adjustments
        if ($this->season->phase === "Preseason") {
            $this->season->beginningYear = \Season::IBL_PRESEASON_YEAR;
            $this->season->endingYear = \Season::IBL_PRESEASON_YEAR + 1;
        }

        return DateParser::extractDate(
            $rawDate,
            $this->season->phase,
            $this->season->beginningYear,
            $this->season->endingYear,
            $currentLeague
        );
    }

    protected function extractBoxID(string $boxHREF): string {
        return ScheduleParser::extractBoxID($boxHREF);
    }

    public function update(): void {
        echo 'Updating the ibl_schedule database table...<p>';

        $log = '';

        $this->execute('TRUNCATE TABLE ibl_schedule', '');
        $log .= 'TRUNCATE TABLE ibl_schedule<p>';

        $documentRootRaw = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $documentRoot = is_string($documentRootRaw) ? $documentRootRaw : '';
        $scheduleFilePath = $documentRoot . '/ibl5/ibl/IBL/Schedule.htm';
        $schedule = new \DOMDocument();
        $schedule->loadHTMLFile($scheduleFilePath);
        $schedule->preserveWhiteSpace = false;
        $rows = $schedule->getElementsByTagName('tr');

        $date = null;
        $year = null;

        foreach ($rows as $row) {
            $thirdCellNode = $row->childNodes->item(2);
            $secondCellNode = $row->childNodes->item(1);
            $firstCellNode = $row->childNodes->item(0);
            $checkThirdCell = $thirdCellNode !== null ? $thirdCellNode->nodeValue : null;
            $checkSecondCell = $secondCellNode !== null ? $secondCellNode->nodeValue : null;
            $checkFirstCell = $firstCellNode !== null ? $firstCellNode->nodeValue : null;

            if ($checkSecondCell === null) {
                $fullDate = $this->extractDate($row->textContent);
                $date = $fullDate['date'] ?? null;
                $year = $fullDate['year'] ?? null;
            }

            if ($this->season->phase === "HEAT" && isset($fullDate['month']) && $fullDate['month'] !== \Season::IBL_HEAT_MONTH) {
                continue;
            }

            if ($checkThirdCell !== null && $checkThirdCell !== "" && $checkFirstCell !== "visitor") {
                $boxID = null;
                $firstCell = $row->childNodes->item(1);

                if ($firstCell instanceof \DOMElement) {
                    $links = $firstCell->getElementsByTagName('a');
                    if ($links->length > 0) {
                        $linkItem = $links->item(0);
                        if ($linkItem !== null) {
                            $boxLink = $linkItem->getAttribute('href');
                            $boxID = (int) $this->extractBoxID($boxLink);
                        }
                    }
                }

                $visitorCellNode = $row->childNodes->item(0);
                $vScoreCellNode = $row->childNodes->item(1);
                $homeCellNode = $row->childNodes->item(2);
                $hScoreCellNode = $row->childNodes->item(3);
                $visitorName = rtrim($visitorCellNode !== null ? $visitorCellNode->textContent : '');
                $vScore = $vScoreCellNode !== null ? $vScoreCellNode->textContent : '';
                $homeName = rtrim($homeCellNode !== null ? $homeCellNode->textContent : '');
                $hScore = $hScoreCellNode !== null ? $hScoreCellNode->textContent : '';

                $scoreNode = $row->childNodes->item(1);
                if ($scoreNode === null || $scoreNode->nodeValue === null || $scoreNode->nodeValue === "") {
                    $vScore = 0;
                    $hScore = 0;
                    if ($boxID !== null && $boxID > 99999) {
                        $boxID = $boxID + 1;
                    } else {
                        $boxID = 100000;
                    }
                }

                $visitorTID = $this->commonRepository->getTidFromTeamname($visitorName);
                $homeTID = $this->commonRepository->getTidFromTeamname($homeName);

                if ($vScore !== 0 && $hScore !== 0 && $boxID === null) {
                    $errorMessage = "Script Error: box scores for games haven't been generated. Please delete and reupload the JSB HTML export with the box scores, then try again.";
                    error_log("[ScheduleUpdater] Box scores missing for game between {$visitorName} and {$homeName}");
                    echo "<b><font color=red>{$errorMessage}</font></b>";
                    throw new \RuntimeException($errorMessage, 1003);
                }

                if ($visitorTID !== null && $homeTID !== null) {
                    $uuid = UuidGenerator::generateUuid();
                    
                    try {
                        $this->execute(
                            "INSERT INTO ibl_schedule (
                                Year,
                                BoxID,
                                Date,
                                Visitor,
                                Vscore,
                                Home,
                                Hscore,
                                uuid
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                            "iisiiiis",
                            $year,
                            $boxID,
                            $date,
                            $visitorTID,
                            $vScore,
                            $homeTID,
                            $hScore,
                            $uuid
                        );
                        $log .= "Inserted game: {$visitorName} @ {$homeName} on {$date}<br>";
                    } catch (\Exception $e) {
                        $errorMessage = "Failed to insert schedule data for game between {$visitorName} and {$homeName}: " . $e->getMessage();
                        error_log("[ScheduleUpdater] Database insert error: {$errorMessage}");
                        echo "<b><font color=red>Script Error: Failed to insert schedule data for game between {$visitorName} and {$homeName}.</font></b>";
                        throw new \RuntimeException($errorMessage, 1002);
                    }
                }
            }
        }
        \UI::displayDebugOutput($log, 'ibl_schedule SQL Queries');

        echo 'The ibl_schedule database table has been updated.<p>';
    }
}
