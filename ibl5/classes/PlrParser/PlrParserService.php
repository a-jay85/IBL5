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
        $data = file_get_contents($filePath);
        if ($data === false) {
            $result = new PlrParseResult();
            $result->addMessage('ERROR: Could not open PLR file');
            return $result;
        }

        return $this->processPlrData($data);
    }

    /**
     * @see PlrParserServiceInterface::processPlrData()
     */
    public function processPlrData(string $data): PlrParseResult
    {
        $result = new PlrParseResult();

        $maxFoulRatio = $this->calculateFoulBaselineFromData($data);
        $result->addMessage('Foul baseline calculated (max ratio: ' . \BasketballStats\StatsFormatter::formatWithDecimals($maxFoulRatio, 6) . ')');

        foreach (explode("\r\n", $data) as $line) {
            $parsed = PlrLineParser::parse($line);
            if ($parsed === null) {
                continue;
            }

            $derived = $this->computeDerivedFields($parsed, $maxFoulRatio);

            $this->repository->upsertPlayer($derived);
            $result->playersUpserted++;
        }

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
        $data = file_get_contents($filePath);
        if ($data === false) {
            return 0.0;
        }
        return $this->calculateFoulBaselineFromData($data);
    }

    /**
     * Pass 1 (string variant): max foul-per-minute ratio across all players.
     *
     * @param string $data Raw .plr file contents (CRLF-separated 607-byte records)
     * @return float Maximum foul ratio (0.0 if no valid data)
     */
    public function calculateFoulBaselineFromData(string $data): float
    {
        $maxRatio = 0.0;

        foreach (explode("\r\n", $data) as $line) {
            $realLifeMIN = (int) substr($line, 56, 4);
            $realLifePF = (int) substr($line, 108, 4);

            if ($realLifePF > 0 && $realLifeMIN > 0) {
                $ratio = round($realLifePF / $realLifeMIN, 6);
                if ($ratio > $maxRatio) {
                    $maxRatio = $ratio;
                }
            }
        }

        return $maxRatio;
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
        $teamid = (int) $raw['teamid'];
        $teamName = $this->commonRepository->getTeamnameFromTeamID($teamid) ?? '';

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
        $data = file_get_contents($filePath);
        if ($data === false) {
            $result = new PlrParseResult();
            $result->addMessage('ERROR: Could not open PLR file');
            return $result;
        }

        return $this->processPlrDataForYear($data, $endingYear, $mode, $snapshotPhase, $sourceArchive);
    }

    /**
     * @see PlrParserServiceInterface::processPlrDataForYear()
     */
    public function processPlrDataForYear(
        string $data,
        int $endingYear,
        PlrImportMode $mode,
        ?string $snapshotPhase = null,
        ?string $sourceArchive = null,
    ): PlrParseResult {
        $result = new PlrParseResult();

        $maxFoulRatio = $this->calculateFoulBaselineFromData($data);
        $result->addMessage('Foul baseline calculated (max ratio: ' . \BasketballStats\StatsFormatter::formatWithDecimals($maxFoulRatio, 6) . ')');

        foreach (explode("\r\n", $data) as $line) {
            $parsed = PlrLineParser::parse($line);
            if ($parsed === null) {
                continue;
            }

            $derived = $this->computeDerivedFields($parsed, $maxFoulRatio, $endingYear);

            match ($mode) {
                PlrImportMode::Live => $this->processLivePlayer($derived, $result),
                PlrImportMode::Snapshot => $this->processSnapshotPlayer($derived, $result, $endingYear, $snapshotPhase ?? '', $sourceArchive ?? ''),
            };
        }

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
            'teamid' => (int) $derived['teamid'],
            'age' => (int) $derived['age'],
            'pos' => (string) $derived['pos'],
            'peak' => (int) $derived['peak'],
            'htft' => (int) $derived['heightFT'],
            'htin' => (int) $derived['heightIN'],
            'wt' => (int) $derived['weight'],
            // Positional ratings (1-9)
            'oo' => (int) $derived['ratingOO'],
            'od' => (int) $derived['ratingOD'],
            'r_drive_off' => (int) $derived['ratingDO'],
            'dd' => (int) $derived['ratingDD'],
            'po' => (int) $derived['ratingPO'],
            'pd' => (int) $derived['ratingPD'],
            'r_trans_off' => (int) $derived['ratingTO'],
            'td' => (int) $derived['ratingTD'],
            // Stat ratings (0-99)
            'r_fga' => (int) $derived['rating2GA'],
            'r_fgp' => (int) $derived['rating2GP'],
            'r_fta' => (int) $derived['ratingFTA'],
            'r_ftp' => (int) $derived['ratingFTP'],
            'r_3ga' => (int) $derived['rating3GA'],
            'r_3gp' => (int) $derived['rating3GP'],
            'r_orb' => (int) $derived['ratingORB'],
            'r_drb' => (int) $derived['ratingDRB'],
            'r_ast' => (int) $derived['ratingAST'],
            'r_stl' => (int) $derived['ratingSTL'],
            'r_tvr' => (int) $derived['ratingTVR'],
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
            'salary_yr1' => (int) $derived['contractYear1'],
            'salary_yr2' => (int) $derived['contractYear2'],
            'salary_yr3' => (int) $derived['contractYear3'],
            'salary_yr4' => (int) $derived['contractYear4'],
            'salary_yr5' => (int) $derived['contractYear5'],
            'salary_yr6' => (int) $derived['contractYear6'],
            // Depth chart
            'pg_depth' => (int) $derived['PGDepth'],
            'sg_depth' => (int) $derived['SGDepth'],
            'sf_depth' => (int) $derived['SFDepth'],
            'pf_depth' => (int) $derived['PFDepth'],
            'c_depth' => (int) $derived['CDepth'],
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
            'stats_tvr' => (int) $derived['seasonTVR'],
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
            'playing_time' => (int) $derived['playingTime'],
            'winner' => (int) $derived['playForWinner'],
            'tradition' => (int) $derived['tradition'],
            'security' => (int) $derived['security'],
            // Draft info
            'draftround' => (int) $derived['draftRound'],
            'draftpickno' => (int) $derived['draftPickNumber'],
            'fa_signing_flag' => (int) $derived['freeAgentSigningFlag'],
            // Other
            'dc_can_play_in_game' => (int) $derived['canPlayInGame'],
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
