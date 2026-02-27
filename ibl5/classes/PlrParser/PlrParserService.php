<?php

declare(strict_types=1);

namespace PlrParser;

use PlrParser\Contracts\PlrParserRepositoryInterface;
use PlrParser\Contracts\PlrParserServiceInterface;

/**
 * Service for processing .plr files from the JSB simulation engine.
 *
 * Implements a two-pass approach:
 * 1. Calculate foul baseline (max foul-per-minute ratio across all players)
 * 2. Parse each player line, compute derived fields, and upsert into database
 */
class PlrParserService implements PlrParserServiceInterface
{
    private PlrParserRepositoryInterface $repository;
    private \Services\CommonMysqliRepository $commonRepository;
    private \Season $season;

    public function __construct(
        PlrParserRepositoryInterface $repository,
        \Services\CommonMysqliRepository $commonRepository,
        \Season $season,
    ) {
        $this->repository = $repository;
        $this->commonRepository = $commonRepository;
        $this->season = $season;
    }

    /**
     * @see PlrParserServiceInterface::processPlrFile()
     */
    public function processPlrFile(string $filePath): PlrParseResult
    {
        $result = new PlrParseResult();

        // Pass 1: Calculate foul baseline
        $maxFoulRatio = $this->calculateFoulBaseline($filePath);
        $result->addMessage('Foul baseline calculated (max ratio: ' . \BasketballStats\StatsFormatter::formatWithDecimals($maxFoulRatio, 6) . ')');

        // Pass 2: Parse players and upsert
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            $result->addMessage('ERROR: Could not open PLR file');
            return $result;
        }

        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line === false) {
                break;
            }

            $parsed = $this->parsePlrLine($line);
            if ($parsed === null) {
                continue;
            }

            $derived = $this->computeDerivedFields($parsed, $maxFoulRatio);

            $this->repository->upsertPlayer($derived);
            $result->playersUpserted++;

            $histData = $this->buildHistoricalData($derived);
            $this->repository->upsertHistoricalStats($histData);
            $result->historyRowsUpserted++;
        }

        fclose($handle);

        // Assign team names
        $teamData = $this->repository->getAllTeamData();
        $result->teamsAssigned = $this->repository->assignTeamNames($teamData);

        return $result;
    }

    /**
     * Pass 1: Calculate the maximum foul-per-minute ratio across all players.
     *
     * @param string $filePath Path to the .plr file
     * @return float Maximum foul ratio (0.0 if no valid data)
     */
    public function calculateFoulBaseline(string $filePath): float
    {
        if (!is_file($filePath)) {
            return 0.0;
        }

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return 0.0;
        }

        $maxRatio = 0.0;

        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line === false) {
                break;
            }

            $realLifeMIN = (int) substr($line, 56, 4);
            $realLifePF = (int) substr($line, 108, 4);

            if ($realLifePF > 0 && $realLifeMIN > 0) {
                $ratio = round($realLifePF / $realLifeMIN, 6);
                if ($ratio > $maxRatio) {
                    $maxRatio = $ratio;
                }
            }
        }

        fclose($handle);
        return $maxRatio;
    }

    /**
     * Parse a single line from the .plr file into raw field values.
     *
     * Returns null if the line should be skipped (pid=0 or ordinal>1440).
     *
     * @param string $line Raw line from the .plr file
     * @return array<string, int|string>|null Parsed fields, or null to skip
     */
    public function parsePlrLine(string $line): ?array
    {
        $ordinal = (int) substr($line, 0, 4);
        $pid = (int) substr($line, 38, 6);

        if ($pid === 0 || $ordinal > 1440) {
            return null;
        }

        // Extract name and convert from Windows 1252 to UTF-8 to preserve accent marks
        $nameRaw = trim(substr($line, 4, 32));
        $nameConverted = iconv('CP1252', 'UTF-8//IGNORE', $nameRaw);
        $name = $nameConverted !== false ? $nameConverted : $nameRaw;

        return [
            'ordinal' => $ordinal,
            'name' => $name,
            'age' => (int) substr($line, 36, 2),
            'pid' => $pid,
            'tid' => (int) substr($line, 44, 2),
            'peak' => (int) substr($line, 46, 4),
            'pos' => trim(substr($line, 50, 2)),
            'realLifeGP' => (int) substr($line, 52, 4),
            'realLifeMIN' => (int) substr($line, 56, 4),
            'realLifeFGM' => (int) substr($line, 60, 4),
            'realLifeFGA' => (int) substr($line, 64, 4),
            'realLifeFTM' => (int) substr($line, 68, 4),
            'realLifeFTA' => (int) substr($line, 72, 4),
            'realLife3GM' => (int) substr($line, 76, 4),
            'realLife3GA' => (int) substr($line, 80, 4),
            'realLifeORB' => (int) substr($line, 84, 4),
            'realLifeDRB' => (int) substr($line, 88, 4),
            'realLifeAST' => (int) substr($line, 92, 4),
            'realLifeSTL' => (int) substr($line, 96, 4),
            'realLifeTVR' => (int) substr($line, 100, 4),
            'realLifeBLK' => (int) substr($line, 104, 4),
            'realLifePF' => (int) substr($line, 108, 4),
            'clutch' => (int) substr($line, 128, 2),
            'consistency' => (int) substr($line, 130, 2),
            'PGDepth' => (int) substr($line, 132, 1),
            'SGDepth' => (int) substr($line, 133, 1),
            'SFDepth' => (int) substr($line, 134, 1),
            'PFDepth' => (int) substr($line, 135, 1),
            'CDepth' => (int) substr($line, 136, 1),
            'active' => (int) substr($line, 137, 1),
            'injuryDaysLeft' => (int) substr($line, 140, 4),
            'seasonGamesStarted' => (int) substr($line, 144, 4),
            'seasonGamesPlayed' => (int) substr($line, 148, 4),
            'seasonMIN' => (int) substr($line, 152, 4),
            'season2GM' => (int) substr($line, 156, 4),
            'season2GA' => (int) substr($line, 160, 4),
            'seasonFTM' => (int) substr($line, 164, 4),
            'seasonFTA' => (int) substr($line, 168, 4),
            'season3GM' => (int) substr($line, 172, 4),
            'season3GA' => (int) substr($line, 176, 4),
            'seasonORB' => (int) substr($line, 180, 4),
            'seasonDRB' => (int) substr($line, 184, 4),
            'seasonAST' => (int) substr($line, 188, 4),
            'seasonSTL' => (int) substr($line, 192, 4),
            'seasonTVR' => (int) substr($line, 196, 4),
            'seasonBLK' => (int) substr($line, 200, 4),
            'seasonPF' => (int) substr($line, 204, 4),
            'talent' => (int) substr($line, 268, 2),
            'skill' => (int) substr($line, 270, 2),
            'intangibles' => (int) substr($line, 272, 2),
            'coach' => (int) substr($line, 274, 2),
            'loyalty' => (int) substr($line, 276, 2),
            'playingTime' => (int) substr($line, 278, 2),
            'playForWinner' => (int) substr($line, 280, 2),
            'tradition' => (int) substr($line, 282, 2),
            'security' => (int) substr($line, 284, 2),
            'exp' => (int) substr($line, 286, 2),
            'bird' => (int) substr($line, 288, 2),
            'currentContractYear' => (int) substr($line, 290, 2),
            'totalContractYears' => (int) substr($line, 292, 2),
            'contractYear1' => (int) substr($line, 298, 4),
            'contractYear2' => (int) substr($line, 302, 4),
            'contractYear3' => (int) substr($line, 306, 4),
            'contractYear4' => (int) substr($line, 310, 4),
            'contractYear5' => (int) substr($line, 314, 4),
            'contractYear6' => (int) substr($line, 318, 4),
            'draftRound' => (int) substr($line, 326, 2),
            'draftPickNumber' => (int) substr($line, 328, 2),
            'freeAgentSigningFlag' => (int) substr($line, 330, 1),
            'seasonHighPTS' => (int) substr($line, 341, 2),
            'seasonHighREB' => (int) substr($line, 343, 2),
            'seasonHighAST' => (int) substr($line, 345, 2),
            'seasonHighSTL' => (int) substr($line, 347, 2),
            'seasonHighBLK' => (int) substr($line, 349, 2),
            'seasonHighDoubleDoubles' => (int) substr($line, 351, 2),
            'seasonHighTripleDoubles' => (int) substr($line, 353, 2),
            'seasonPlayoffHighPTS' => (int) substr($line, 355, 2),
            'seasonPlayoffHighREB' => (int) substr($line, 357, 2),
            'seasonPlayoffHighAST' => (int) substr($line, 359, 2),
            'seasonPlayoffHighSTL' => (int) substr($line, 361, 2),
            'seasonPlayoffHighBLK' => (int) substr($line, 363, 2),
            'careerSeasonHighPTS' => (int) substr($line, 365, 6),
            'careerSeasonHighREB' => (int) substr($line, 371, 6),
            'careerSeasonHighAST' => (int) substr($line, 377, 6),
            'careerSeasonHighSTL' => (int) substr($line, 383, 6),
            'careerSeasonHighBLK' => (int) substr($line, 389, 6),
            'careerSeasonHighDoubleDoubles' => (int) substr($line, 395, 6),
            'careerSeasonHighTripleDoubles' => (int) substr($line, 401, 6),
            'careerPlayoffHighPTS' => (int) substr($line, 407, 6),
            'careerPlayoffHighREB' => (int) substr($line, 413, 6),
            'careerPlayoffHighAST' => (int) substr($line, 419, 6),
            'careerPlayoffHighSTL' => (int) substr($line, 425, 6),
            'careerPlayoffHighBLK' => (int) substr($line, 431, 6),
            'careerGP' => (int) substr($line, 437, 5),
            'careerMIN' => (int) substr($line, 442, 5),
            'career2GM' => (int) substr($line, 447, 5),
            'career2GA' => (int) substr($line, 452, 5),
            'careerFTM' => (int) substr($line, 457, 5),
            'careerFTA' => (int) substr($line, 462, 5),
            'career3GM' => (int) substr($line, 467, 5),
            'career3GA' => (int) substr($line, 472, 5),
            'careerORB' => (int) substr($line, 477, 5),
            'careerDRB' => (int) substr($line, 482, 5),
            'careerAST' => (int) substr($line, 487, 5),
            'careerSTL' => (int) substr($line, 492, 5),
            'careerTVR' => (int) substr($line, 497, 5),
            'careerBLK' => (int) substr($line, 502, 5),
            'careerPF' => (int) substr($line, 507, 5),
            'heightInches' => (int) substr($line, 550, 2),
            'weight' => (int) substr($line, 552, 3),
            'rating2GA' => (int) substr($line, 555, 3),
            'rating2GP' => (int) substr($line, 558, 3),
            'ratingFTA' => (int) substr($line, 561, 3),
            'ratingFTP' => (int) substr($line, 564, 3),
            'rating3GA' => (int) substr($line, 567, 3),
            'rating3GP' => (int) substr($line, 570, 3),
            'ratingORB' => (int) substr($line, 573, 3),
            'ratingDRB' => (int) substr($line, 576, 3),
            'ratingAST' => (int) substr($line, 579, 3),
            'ratingSTL' => (int) substr($line, 582, 3),
            'ratingTVR' => (int) substr($line, 585, 3),
            'ratingBLK' => (int) substr($line, 588, 3),
            'ratingOO' => (int) substr($line, 591, 2),
            'ratingDO' => (int) substr($line, 593, 2),
            'ratingPO' => (int) substr($line, 595, 2),
            'ratingTO' => (int) substr($line, 597, 2),
            'ratingOD' => (int) substr($line, 599, 2),
            'ratingDD' => (int) substr($line, 601, 2),
            'ratingPD' => (int) substr($line, 603, 2),
            'ratingTD' => (int) substr($line, 605, 2),
        ];
    }

    /**
     * Compute derived fields from raw parsed data.
     *
     * @param array<string, int|string> $raw Raw parsed fields
     * @param float $maxFoulRatio Max foul ratio from pass 1
     * @return array<string, int|string|float> Complete data with derived fields
     */
    public function computeDerivedFields(array $raw, float $maxFoulRatio): array
    {
        $season2GM = (int) $raw['season2GM'];
        $season3GM = (int) $raw['season3GM'];
        $season2GA = (int) $raw['season2GA'];
        $season3GA = (int) $raw['season3GA'];
        $seasonFTM = (int) $raw['seasonFTM'];
        $seasonORB = (int) $raw['seasonORB'];
        $seasonDRB = (int) $raw['seasonDRB'];

        $career2GM = (int) $raw['career2GM'];
        $career3GM = (int) $raw['career3GM'];
        $career2GA = (int) $raw['career2GA'];
        $career3GA = (int) $raw['career3GA'];
        $careerFTM = (int) $raw['careerFTM'];
        $careerORB = (int) $raw['careerORB'];
        $careerDRB = (int) $raw['careerDRB'];

        $currentContractYear = (int) $raw['currentContractYear'];
        $heightInches = (int) $raw['heightInches'];
        $exp = (int) $raw['exp'];

        $realLifePF = (int) $raw['realLifePF'];
        $realLifeMIN = (int) $raw['realLifeMIN'];

        // Season composite stats
        $seasonFGM = $season2GM + $season3GM;
        $seasonFGA = $season2GA + $season3GA;
        $seasonREB = $seasonORB + $seasonDRB;
        $seasonPTS = $season2GM * 2 + $seasonFTM + $season3GM * 3;

        // Career composite stats
        $careerFGM = $career2GM + $career3GM;
        $careerFGA = $career2GA + $career3GA;
        $careerREB = $careerORB + $careerDRB;
        $careerPTS = $career2GM * 2 + $careerFTM + $career3GM * 3;

        // Current season salary
        if ($currentContractYear === 0) {
            $currentSeasonSalary = (int) $raw['contractYear1'];
        } elseif ($currentContractYear === 7) {
            $currentSeasonSalary = 0;
        } else {
            $currentSeasonSalary = (int) ($raw['contractYear' . $currentContractYear] ?? 0);
        }

        // Height conversion
        $heightFT = (int) floor($heightInches / 12);
        $heightIN = $heightInches % 12;

        // Draft year
        $draftYear = $this->season->endingYear - $exp;

        // Foul rating
        $personalFoulsPerMinute = ($realLifePF > 0 && $realLifeMIN > 0)
            ? round($realLifePF / $realLifeMIN, 6)
            : 0.0;
        $ratingFOUL = $maxFoulRatio > 0.0
            ? (int) (100 - round($personalFoulsPerMinute / $maxFoulRatio * 100, 0))
            : 0;

        // Team name for historical stats
        $tid = (int) $raw['tid'];
        $teamName = $this->commonRepository->getTeamnameFromTeamID($tid) ?? '';

        return array_merge($raw, [
            'seasonFGM' => $seasonFGM,
            'seasonFGA' => $seasonFGA,
            'seasonREB' => $seasonREB,
            'seasonPTS' => $seasonPTS,
            'careerFGM' => $careerFGM,
            'careerFGA' => $careerFGA,
            'careerREB' => $careerREB,
            'careerPTS' => $careerPTS,
            'currentSeasonSalary' => $currentSeasonSalary,
            'heightFT' => $heightFT,
            'heightIN' => $heightIN,
            'draftYear' => $draftYear,
            'ratingFOUL' => $ratingFOUL,
            'teamName' => $teamName,
        ]);
    }

    /**
     * Build historical data record for ibl_hist upsert.
     *
     * @param array<string, int|string|float> $derived Data with derived fields
     * @return array<string, int|string|float> Historical stats record
     */
    private function buildHistoricalData(array $derived): array
    {
        return [
            'pid' => (int) $derived['pid'],
            'name' => (string) $derived['name'],
            'year' => $this->season->endingYear,
            'team' => (string) $derived['teamName'],
            'tid' => (int) $derived['tid'],
            'seasonGamesPlayed' => (int) $derived['seasonGamesPlayed'],
            'seasonMIN' => (int) $derived['seasonMIN'],
            'seasonFGM' => (int) $derived['seasonFGM'],
            'seasonFGA' => (int) $derived['seasonFGA'],
            'seasonFTM' => (int) $derived['seasonFTM'],
            'seasonFTA' => (int) $derived['seasonFTA'],
            'season3GM' => (int) $derived['season3GM'],
            'season3GA' => (int) $derived['season3GA'],
            'seasonORB' => (int) $derived['seasonORB'],
            'seasonREB' => (int) $derived['seasonREB'],
            'seasonAST' => (int) $derived['seasonAST'],
            'seasonSTL' => (int) $derived['seasonSTL'],
            'seasonBLK' => (int) $derived['seasonBLK'],
            'seasonTVR' => (int) $derived['seasonTVR'],
            'seasonPF' => (int) $derived['seasonPF'],
            'seasonPTS' => (int) $derived['seasonPTS'],
            'rating2GA' => (int) $derived['rating2GA'],
            'rating2GP' => (int) $derived['rating2GP'],
            'ratingFTA' => (int) $derived['ratingFTA'],
            'ratingFTP' => (int) $derived['ratingFTP'],
            'rating3GA' => (int) $derived['rating3GA'],
            'rating3GP' => (int) $derived['rating3GP'],
            'ratingORB' => (int) $derived['ratingORB'],
            'ratingDRB' => (int) $derived['ratingDRB'],
            'ratingAST' => (int) $derived['ratingAST'],
            'ratingSTL' => (int) $derived['ratingSTL'],
            'ratingBLK' => (int) $derived['ratingBLK'],
            'ratingTVR' => (int) $derived['ratingTVR'],
            'ratingOO' => (int) $derived['ratingOO'],
            'ratingOD' => (int) $derived['ratingOD'],
            'ratingDO' => (int) $derived['ratingDO'],
            'ratingDD' => (int) $derived['ratingDD'],
            'ratingPO' => (int) $derived['ratingPO'],
            'ratingPD' => (int) $derived['ratingPD'],
            'ratingTO' => (int) $derived['ratingTO'],
            'ratingTD' => (int) $derived['ratingTD'],
            'currentSeasonSalary' => (int) $derived['currentSeasonSalary'],
        ];
    }
}
