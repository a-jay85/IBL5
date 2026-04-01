<?php

declare(strict_types=1);

namespace PlrParser;

use PlrParser\Contracts\PlrParserRepositoryInterface;
use PlrParser\Contracts\PlrParserServiceInterface;
use Season\Season;

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
    private Season $season;

    public function __construct(
        PlrParserRepositoryInterface $repository,
        \Services\CommonMysqliRepository $commonRepository,
        Season $season,
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
        }

        fclose($handle);

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
            // Unknown gap: offsets 112-127 (16 bytes)
            'unk_112' => (int) substr($line, 112, 2),
            'unk_114' => (int) substr($line, 114, 2),
            'unk_116' => (int) substr($line, 116, 2),
            'unk_118' => (int) substr($line, 118, 2),
            'unk_120' => (int) substr($line, 120, 2),
            'unk_122' => (int) substr($line, 122, 2),
            'unk_124' => (int) substr($line, 124, 2),
            'unk_126' => (int) substr($line, 126, 2),
            'clutch' => (int) substr($line, 128, 2),
            'consistency' => (int) substr($line, 130, 2),
            'PGDepth' => (int) substr($line, 132, 1),
            'SGDepth' => (int) substr($line, 133, 1),
            'SFDepth' => (int) substr($line, 134, 1),
            'PFDepth' => (int) substr($line, 135, 1),
            'CDepth' => (int) substr($line, 136, 1),
            'canPlayInGame' => (int) substr($line, 137, 1),
            // Unknown gap: offset 138-139 (2 bytes)
            'unk_138' => (int) substr($line, 138, 2),
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
            // Playoff season stats (speculative — offsets 208-267 mirror season stats minus GS)
            'playoffSeasonGP' => (int) substr($line, 208, 4),
            'playoffSeasonMIN' => (int) substr($line, 212, 4),
            'playoffSeason2GM' => (int) substr($line, 216, 4),
            'playoffSeason2GA' => (int) substr($line, 220, 4),
            'playoffSeasonFTM' => (int) substr($line, 224, 4),
            'playoffSeasonFTA' => (int) substr($line, 228, 4),
            'playoffSeason3GM' => (int) substr($line, 232, 4),
            'playoffSeason3GA' => (int) substr($line, 236, 4),
            'playoffSeasonORB' => (int) substr($line, 240, 4),
            'playoffSeasonDRB' => (int) substr($line, 244, 4),
            'playoffSeasonAST' => (int) substr($line, 248, 4),
            'playoffSeasonSTL' => (int) substr($line, 252, 4),
            'playoffSeasonTVR' => (int) substr($line, 256, 4),
            'playoffSeasonBLK' => (int) substr($line, 260, 4),
            'playoffSeasonPF' => (int) substr($line, 264, 4),
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
            // Unknown gap: offsets 294-297 (4 bytes)
            'unk_294' => (int) substr($line, 294, 2),
            'unk_296' => (int) substr($line, 296, 2),
            'contractYear1' => (int) substr($line, 298, 4),
            'contractYear2' => (int) substr($line, 302, 4),
            'contractYear3' => (int) substr($line, 306, 4),
            'contractYear4' => (int) substr($line, 310, 4),
            'contractYear5' => (int) substr($line, 314, 4),
            'contractYear6' => (int) substr($line, 318, 4),
            // Unknown gap: offsets 322-325 (4 bytes)
            'unk_322' => (int) substr($line, 322, 2),
            'unk_324' => (int) substr($line, 324, 2),
            'draftRound' => (int) substr($line, 326, 2),
            'draftPickNumber' => (int) substr($line, 328, 2),
            'freeAgentSigningFlag' => (int) substr($line, 330, 1),
            // Unknown gap: offsets 331-340 (10 bytes)
            'unk_331' => (int) substr($line, 331, 2),
            'unk_333' => (int) substr($line, 333, 2),
            'unk_335' => (int) substr($line, 335, 2),
            'unk_337' => (int) substr($line, 337, 2),
            'unk_339' => (int) substr($line, 339, 2),
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
            // Unknown gap: offsets 512-549 (38 bytes)
            'unk_512' => (int) substr($line, 512, 2),
            'unk_514' => (int) substr($line, 514, 2),
            'unk_516' => (int) substr($line, 516, 2),
            'unk_518' => (int) substr($line, 518, 2),
            'unk_520' => (int) substr($line, 520, 2),
            'unk_522' => (int) substr($line, 522, 2),
            'unk_524' => (int) substr($line, 524, 2),
            'unk_526' => (int) substr($line, 526, 2),
            'unk_528' => (int) substr($line, 528, 2),
            'unk_530' => (int) substr($line, 530, 2),
            'unk_532' => (int) substr($line, 532, 2),
            'unk_534' => (int) substr($line, 534, 2),
            'unk_536' => (int) substr($line, 536, 2),
            'unk_538' => (int) substr($line, 538, 2),
            'unk_540' => (int) substr($line, 540, 2),
            'unk_542' => (int) substr($line, 542, 2),
            'unk_544' => (int) substr($line, 544, 2),
            'unk_546' => (int) substr($line, 546, 2),
            'unk_548' => (int) substr($line, 548, 2),
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
     * @param int|null $endingYear Override season ending year (for bulk imports without Season dependency)
     * @return array<string, int|string|float> Complete data with derived fields
     */
    public function computeDerivedFields(array $raw, float $maxFoulRatio, ?int $endingYear = null): array
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
        $draftYear = ($endingYear ?? $this->season->endingYear) - $exp;

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
     * @see PlrParserServiceInterface::processPlrFileForYear()
     */
    public function processPlrFileForYear(
        string $filePath,
        int $endingYear,
        PlrImportMode $mode,
        ?string $snapshotPhase = null,
        ?string $sourceArchive = null,
    ): PlrParseResult {
        $result = new PlrParseResult();

        $maxFoulRatio = $this->calculateFoulBaseline($filePath);
        $result->addMessage('Foul baseline calculated (max ratio: ' . \BasketballStats\StatsFormatter::formatWithDecimals($maxFoulRatio, 6) . ')');

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

            $derived = $this->computeDerivedFields($parsed, $maxFoulRatio, $endingYear);

            match ($mode) {
                PlrImportMode::Live => $this->processLivePlayer($derived, $result),
                PlrImportMode::Snapshot => $this->processSnapshotPlayer($derived, $result, $endingYear, $snapshotPhase ?? '', $sourceArchive ?? ''),
            };
        }

        fclose($handle);

        return $result;
    }

    /**
     * Process a player in Live mode: upsert into ibl_plr.
     *
     * @param array<string, int|string|float> $derived
     */
    private function processLivePlayer(array $derived, PlrParseResult $result): void
    {
        $this->repository->upsertPlayer($derived);
        $result->playersUpserted++;
    }

    /**
     * Process a player in Snapshot mode: upsert into ibl_plr_snapshots.
     *
     * @param array<string, int|string|float> $derived
     */
    private function processSnapshotPlayer(
        array $derived,
        PlrParseResult $result,
        int $endingYear,
        string $snapshotPhase,
        string $sourceArchive,
    ): void {
        $snapshotData = $this->buildSnapshotData($derived, $endingYear, $snapshotPhase, $sourceArchive);
        $this->repository->upsertSnapshot($snapshotData);
        $result->playersUpserted++;
    }

    /**
     * Build snapshot data record for ibl_plr_snapshots upsert.
     *
     * @param array<string, int|string|float> $derived Data with derived fields
     * @return array<string, int|string> Snapshot record
     */
    private function buildSnapshotData(
        array $derived,
        int $endingYear,
        string $snapshotPhase,
        string $sourceArchive,
    ): array {
        return [
            // Identity & metadata
            'pid' => (int) $derived['pid'],
            'name' => (string) $derived['name'],
            'season_year' => $endingYear,
            'snapshot_phase' => $snapshotPhase,
            'source_archive' => $sourceArchive,
            'ordinal' => (int) $derived['ordinal'],
            // Physical & position
            'tid' => (int) $derived['tid'],
            'age' => (int) $derived['age'],
            'pos' => (string) $derived['pos'],
            'peak' => (int) $derived['peak'],
            'htft' => (int) $derived['heightFT'],
            'htin' => (int) $derived['heightIN'],
            'wt' => (int) $derived['weight'],
            // Positional ratings (1-9)
            'oo' => (int) $derived['ratingOO'],
            'od' => (int) $derived['ratingOD'],
            'do' => (int) $derived['ratingDO'],
            'dd' => (int) $derived['ratingDD'],
            'po' => (int) $derived['ratingPO'],
            'pd' => (int) $derived['ratingPD'],
            'to' => (int) $derived['ratingTO'],
            'td' => (int) $derived['ratingTD'],
            // Stat ratings (0-99)
            'r_fga' => (int) $derived['rating2GA'],
            'r_fgp' => (int) $derived['rating2GP'],
            'r_fta' => (int) $derived['ratingFTA'],
            'r_ftp' => (int) $derived['ratingFTP'],
            'r_tga' => (int) $derived['rating3GA'],
            'r_tgp' => (int) $derived['rating3GP'],
            'r_orb' => (int) $derived['ratingORB'],
            'r_drb' => (int) $derived['ratingDRB'],
            'r_ast' => (int) $derived['ratingAST'],
            'r_stl' => (int) $derived['ratingSTL'],
            'r_to' => (int) $derived['ratingTVR'],
            'r_blk' => (int) $derived['ratingBLK'],
            'r_foul' => (int) $derived['ratingFOUL'],
            // TSI attributes
            'talent' => (int) $derived['talent'],
            'skill' => (int) $derived['skill'],
            'intangibles' => (int) $derived['intangibles'],
            'clutch' => (int) $derived['clutch'],
            'consistency' => (int) $derived['consistency'],
            // Contract
            'exp' => (int) $derived['exp'],
            'bird' => (int) $derived['bird'],
            'cy' => (int) $derived['currentContractYear'],
            'cyt' => (int) $derived['totalContractYears'],
            'cy1' => (int) $derived['contractYear1'],
            'cy2' => (int) $derived['contractYear2'],
            'cy3' => (int) $derived['contractYear3'],
            'cy4' => (int) $derived['contractYear4'],
            'cy5' => (int) $derived['contractYear5'],
            'cy6' => (int) $derived['contractYear6'],
            // Depth chart
            'PGDepth' => (int) $derived['PGDepth'],
            'SGDepth' => (int) $derived['SGDepth'],
            'SFDepth' => (int) $derived['SFDepth'],
            'PFDepth' => (int) $derived['PFDepth'],
            'CDepth' => (int) $derived['CDepth'],
            // Season stats (regular season)
            'stats_gs' => (int) $derived['seasonGamesStarted'],
            'stats_gm' => (int) $derived['seasonGamesPlayed'],
            'stats_min' => (int) $derived['seasonMIN'],
            'stats_fgm' => (int) $derived['seasonFGM'],
            'stats_fga' => (int) $derived['seasonFGA'],
            'stats_ftm' => (int) $derived['seasonFTM'],
            'stats_fta' => (int) $derived['seasonFTA'],
            'stats_3gm' => (int) $derived['season3GM'],
            'stats_3ga' => (int) $derived['season3GA'],
            'stats_orb' => (int) $derived['seasonORB'],
            'stats_drb' => (int) $derived['seasonDRB'],
            'stats_ast' => (int) $derived['seasonAST'],
            'stats_stl' => (int) $derived['seasonSTL'],
            'stats_to' => (int) $derived['seasonTVR'],
            'stats_blk' => (int) $derived['seasonBLK'],
            'stats_pf' => (int) $derived['seasonPF'],
            'stats_reb' => (int) $derived['seasonREB'],
            'stats_pts' => (int) $derived['seasonPTS'],
            // Playoff season stats (speculative — from offset gap 208-267)
            'po_stats_gm' => (int) $derived['playoffSeasonGP'],
            'po_stats_min' => (int) $derived['playoffSeasonMIN'],
            'po_stats_2gm' => (int) $derived['playoffSeason2GM'],
            'po_stats_2ga' => (int) $derived['playoffSeason2GA'],
            'po_stats_ftm' => (int) $derived['playoffSeasonFTM'],
            'po_stats_fta' => (int) $derived['playoffSeasonFTA'],
            'po_stats_3gm' => (int) $derived['playoffSeason3GM'],
            'po_stats_3ga' => (int) $derived['playoffSeason3GA'],
            'po_stats_orb' => (int) $derived['playoffSeasonORB'],
            'po_stats_drb' => (int) $derived['playoffSeasonDRB'],
            'po_stats_ast' => (int) $derived['playoffSeasonAST'],
            'po_stats_stl' => (int) $derived['playoffSeasonSTL'],
            'po_stats_tvr' => (int) $derived['playoffSeasonTVR'],
            'po_stats_blk' => (int) $derived['playoffSeasonBLK'],
            'po_stats_pf' => (int) $derived['playoffSeasonPF'],
            // Career stats
            'car_gm' => (int) $derived['careerGP'],
            'car_min' => (int) $derived['careerMIN'],
            'car_fgm' => (int) $derived['careerFGM'],
            'car_fga' => (int) $derived['careerFGA'],
            'car_ftm' => (int) $derived['careerFTM'],
            'car_fta' => (int) $derived['careerFTA'],
            'car_tgm' => (int) $derived['career3GM'],
            'car_tga' => (int) $derived['career3GA'],
            'car_orb' => (int) $derived['careerORB'],
            'car_drb' => (int) $derived['careerDRB'],
            'car_reb' => (int) $derived['careerREB'],
            'car_ast' => (int) $derived['careerAST'],
            'car_stl' => (int) $derived['careerSTL'],
            'car_to' => (int) $derived['careerTVR'],
            'car_blk' => (int) $derived['careerBLK'],
            'car_pf' => (int) $derived['careerPF'],
            'car_pts' => (int) $derived['careerPTS'],
            // Season highs
            'sh_pts' => (int) $derived['seasonHighPTS'],
            'sh_reb' => (int) $derived['seasonHighREB'],
            'sh_ast' => (int) $derived['seasonHighAST'],
            'sh_stl' => (int) $derived['seasonHighSTL'],
            'sh_blk' => (int) $derived['seasonHighBLK'],
            's_dd' => (int) $derived['seasonHighDoubleDoubles'],
            's_td' => (int) $derived['seasonHighTripleDoubles'],
            // Playoff highs
            'sp_pts' => (int) $derived['seasonPlayoffHighPTS'],
            'sp_reb' => (int) $derived['seasonPlayoffHighREB'],
            'sp_ast' => (int) $derived['seasonPlayoffHighAST'],
            'sp_stl' => (int) $derived['seasonPlayoffHighSTL'],
            'sp_blk' => (int) $derived['seasonPlayoffHighBLK'],
            // Career season highs
            'ch_pts' => (int) $derived['careerSeasonHighPTS'],
            'ch_reb' => (int) $derived['careerSeasonHighREB'],
            'ch_ast' => (int) $derived['careerSeasonHighAST'],
            'ch_stl' => (int) $derived['careerSeasonHighSTL'],
            'ch_blk' => (int) $derived['careerSeasonHighBLK'],
            'c_dd' => (int) $derived['careerSeasonHighDoubleDoubles'],
            'c_td' => (int) $derived['careerSeasonHighTripleDoubles'],
            // Career playoff highs
            'cp_pts' => (int) $derived['careerPlayoffHighPTS'],
            'cp_reb' => (int) $derived['careerPlayoffHighREB'],
            'cp_ast' => (int) $derived['careerPlayoffHighAST'],
            'cp_stl' => (int) $derived['careerPlayoffHighSTL'],
            'cp_blk' => (int) $derived['careerPlayoffHighBLK'],
            // Real-life stats
            'rl_gp' => (int) $derived['realLifeGP'],
            'rl_min' => (int) $derived['realLifeMIN'],
            'rl_fgm' => (int) $derived['realLifeFGM'],
            'rl_fga' => (int) $derived['realLifeFGA'],
            'rl_ftm' => (int) $derived['realLifeFTM'],
            'rl_fta' => (int) $derived['realLifeFTA'],
            'rl_3gm' => (int) $derived['realLife3GM'],
            'rl_3ga' => (int) $derived['realLife3GA'],
            'rl_orb' => (int) $derived['realLifeORB'],
            'rl_drb' => (int) $derived['realLifeDRB'],
            'rl_ast' => (int) $derived['realLifeAST'],
            'rl_stl' => (int) $derived['realLifeSTL'],
            'rl_tvr' => (int) $derived['realLifeTVR'],
            'rl_blk' => (int) $derived['realLifeBLK'],
            'rl_pf' => (int) $derived['realLifePF'],
            // Preference weights
            'coach' => (int) $derived['coach'],
            'loyalty' => (int) $derived['loyalty'],
            'playingTime' => (int) $derived['playingTime'],
            'winner' => (int) $derived['playForWinner'],
            'tradition' => (int) $derived['tradition'],
            'security' => (int) $derived['security'],
            // Draft info
            'draftround' => (int) $derived['draftRound'],
            'draftpickno' => (int) $derived['draftPickNumber'],
            'fa_signing_flag' => (int) $derived['freeAgentSigningFlag'],
            // Other
            'dc_canPlayInGame' => (int) $derived['canPlayInGame'],
            'injured' => (int) $derived['injuryDaysLeft'],
            // Derived
            'draftyear' => (int) $derived['draftYear'],
            'salary' => (int) $derived['currentSeasonSalary'],
            // Unknown gaps (raw capture for future decoding)
            'unk_112' => (int) $derived['unk_112'],
            'unk_114' => (int) $derived['unk_114'],
            'unk_116' => (int) $derived['unk_116'],
            'unk_118' => (int) $derived['unk_118'],
            'unk_120' => (int) $derived['unk_120'],
            'unk_122' => (int) $derived['unk_122'],
            'unk_124' => (int) $derived['unk_124'],
            'unk_126' => (int) $derived['unk_126'],
            'unk_138' => (int) $derived['unk_138'],
            'unk_294' => (int) $derived['unk_294'],
            'unk_296' => (int) $derived['unk_296'],
            'unk_322' => (int) $derived['unk_322'],
            'unk_324' => (int) $derived['unk_324'],
            'unk_331' => (int) $derived['unk_331'],
            'unk_333' => (int) $derived['unk_333'],
            'unk_335' => (int) $derived['unk_335'],
            'unk_337' => (int) $derived['unk_337'],
            'unk_339' => (int) $derived['unk_339'],
            'unk_512' => (int) $derived['unk_512'],
            'unk_514' => (int) $derived['unk_514'],
            'unk_516' => (int) $derived['unk_516'],
            'unk_518' => (int) $derived['unk_518'],
            'unk_520' => (int) $derived['unk_520'],
            'unk_522' => (int) $derived['unk_522'],
            'unk_524' => (int) $derived['unk_524'],
            'unk_526' => (int) $derived['unk_526'],
            'unk_528' => (int) $derived['unk_528'],
            'unk_530' => (int) $derived['unk_530'],
            'unk_532' => (int) $derived['unk_532'],
            'unk_534' => (int) $derived['unk_534'],
            'unk_536' => (int) $derived['unk_536'],
            'unk_538' => (int) $derived['unk_538'],
            'unk_540' => (int) $derived['unk_540'],
            'unk_542' => (int) $derived['unk_542'],
            'unk_544' => (int) $derived['unk_544'],
            'unk_546' => (int) $derived['unk_546'],
            'unk_548' => (int) $derived['unk_548'],
        ];
    }

}
