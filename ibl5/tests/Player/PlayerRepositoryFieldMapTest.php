<?php

declare(strict_types=1);

namespace Tests\Player;

use PHPUnit\Framework\TestCase;
use Player\PlayerData;
use Player\PlayerRepository;

class PlayerRepositoryFieldMapTest extends TestCase
{
    private PlayerRepository $repository;

    protected function setUp(): void
    {
        $db = $this->createMock(\mysqli::class);
        $this->repository = new PlayerRepository($db);
    }

    public function testFillFromCurrentRowMapsAllExpectedFields(): void
    {
        $row = $this->buildFullCurrentRow();
        $player = $this->repository->fillFromCurrentRow($row);

        self::assertSame(100, $player->playerID);
        self::assertSame(5, $player->ordinal);
        self::assertSame('Test Player', $player->name);
        self::assertSame('TP', $player->nickname);
        self::assertSame(25, $player->age);
        self::assertSame(1, $player->teamid);
        self::assertSame('Test Team', $player->teamName);
        self::assertSame('FF0000', $player->teamColor1);
        self::assertSame('0000FF', $player->teamColor2);
        self::assertSame('PG', $player->position);

        // Ratings
        self::assertSame(3, $player->ratingFieldGoalAttempts);
        self::assertSame(4, $player->ratingFieldGoalPercentage);
        self::assertSame(2, $player->ratingFreeThrowAttempts);
        self::assertSame(5, $player->ratingFreeThrowPercentage);
        self::assertSame(3, $player->ratingThreePointAttempts);
        self::assertSame(4, $player->ratingThreePointPercentage);
        self::assertSame(2, $player->ratingOffensiveRebounds);
        self::assertSame(3, $player->ratingDefensiveRebounds);
        self::assertSame(4, $player->ratingAssists);
        self::assertSame(3, $player->ratingSteals);
        self::assertSame(2, $player->ratingTurnovers);
        self::assertSame(3, $player->ratingBlocks);
        self::assertSame(2, $player->ratingFouls);
        self::assertSame(4, $player->ratingOutsideOffense);
        self::assertSame(3, $player->ratingOutsideDefense);
        self::assertSame(4, $player->ratingDriveOffense);
        self::assertSame(3, $player->ratingDriveDefense);
        self::assertSame(4, $player->ratingPostOffense);
        self::assertSame(3, $player->ratingPostDefense);
        self::assertSame(4, $player->ratingTransitionOffense);
        self::assertSame(3, $player->ratingTransitionDefense);
        self::assertSame(5, $player->ratingClutch);
        self::assertSame(4, $player->ratingConsistency);
        self::assertSame(5, $player->ratingTalent);
        self::assertSame(4, $player->ratingSkill);
        self::assertSame(3, $player->ratingIntangibles);

        // Free agency
        self::assertSame(3, $player->freeAgencyLoyalty);
        self::assertSame(4, $player->freeAgencyPlayingTime);
        self::assertSame(2, $player->freeAgencyPlayForWinner);
        self::assertSame(3, $player->freeAgencyTradition);
        self::assertSame(4, $player->freeAgencySecurity);

        // Contract
        self::assertSame(5, $player->yearsOfExperience);
        self::assertSame(3, $player->birdYears);
        self::assertSame(2, $player->contractCurrentYear);
        self::assertSame(4, $player->contractTotalYears);
        self::assertSame(5000, $player->contractYear1Salary);
        self::assertSame(5500, $player->contractYear2Salary);
        self::assertSame(6000, $player->contractYear3Salary);
        self::assertSame(6500, $player->contractYear4Salary);
        self::assertSame(0, $player->contractYear5Salary);
        self::assertSame(0, $player->contractYear6Salary);

        // Draft
        self::assertSame(2020, $player->draftYear);
        self::assertSame(1, $player->draftRound);
        self::assertSame(5, $player->draftPickNumber);
        self::assertSame('Original Team', $player->draftTeamOriginalName);
        self::assertSame('Current Team', $player->draftTeamCurrentName);
        self::assertSame('Test University', $player->collegeName);

        // Physical
        self::assertSame(6, $player->heightFeet);
        self::assertSame(3, $player->heightInches);
        self::assertSame(195, $player->weightPounds);

        // Status
        self::assertSame(0, $player->daysRemainingForInjury);
        self::assertSame(0, $player->isRetired);
        self::assertSame(0, $player->timeDroppedOnWaivers);
    }

    public function testFillFromHistoricalRowMapsAllExpectedFields(): void
    {
        $row = $this->buildFullHistoricalRow();
        $player = $this->repository->fillFromHistoricalRow($row);

        self::assertSame(200, $player->playerID);
        self::assertSame(2015, $player->historicalYear);
        self::assertSame('Historical Player', $player->name);
        self::assertSame('Old Team', $player->teamName);
        self::assertSame(5, $player->teamid);

        // Ratings (historical uses different column names)
        self::assertSame(3, $player->ratingFieldGoalAttempts);
        self::assertSame(4, $player->ratingFieldGoalPercentage);
        self::assertSame(2, $player->ratingFreeThrowAttempts);
        self::assertSame(5, $player->ratingFreeThrowPercentage);
        self::assertSame(3, $player->ratingThreePointAttempts);
        self::assertSame(4, $player->ratingThreePointPercentage);
        self::assertSame(2, $player->ratingOffensiveRebounds);
        self::assertSame(3, $player->ratingDefensiveRebounds);
        self::assertSame(4, $player->ratingAssists);
        self::assertSame(3, $player->ratingSteals);
        self::assertSame(3, $player->ratingBlocks);
        self::assertSame(2, $player->ratingTurnovers);
        self::assertSame(4, $player->ratingOutsideOffense);
        self::assertSame(3, $player->ratingOutsideDefense);
        self::assertSame(4, $player->ratingDriveOffense);
        self::assertSame(3, $player->ratingDriveDefense);
        self::assertSame(4, $player->ratingPostOffense);
        self::assertSame(3, $player->ratingPostDefense);
        self::assertSame(4, $player->ratingTransitionOffense);
        self::assertSame(3, $player->ratingTransitionDefense);

        // Salary
        self::assertSame(8000, $player->salaryJSB);

        // Contract fields initialized to 0 for historical
        self::assertSame(0, $player->contractCurrentYear);
        self::assertSame(0, $player->contractTotalYears);
        self::assertSame(0, $player->contractYear1Salary);
        self::assertSame(0, $player->contractYear2Salary);
        self::assertSame(0, $player->contractYear3Salary);
        self::assertSame(0, $player->contractYear4Salary);
        self::assertSame(0, $player->contractYear5Salary);
        self::assertSame(0, $player->contractYear6Salary);
    }

    public function testFillFromCurrentRowHandlesNullOptionalFields(): void
    {
        $row = $this->buildFullCurrentRow();
        $row['nickname'] = null;
        $row['teamname'] = null;
        $row['color1'] = null;
        $row['color2'] = null;
        $row['draftedby'] = null;
        $row['draftedbycurrentname'] = null;
        $row['college'] = null;
        $row['htft'] = null;
        $row['htin'] = null;
        $row['wt'] = null;
        $row['retired'] = null;

        $player = $this->repository->fillFromCurrentRow($row);

        self::assertNull($player->nickname);
        self::assertNull($player->teamName);
        self::assertNull($player->teamColor1);
        self::assertNull($player->teamColor2);
        self::assertNull($player->draftTeamOriginalName);
        self::assertNull($player->draftTeamCurrentName);
        self::assertNull($player->collegeName);
        self::assertNull($player->heightFeet);
        self::assertNull($player->heightInches);
        self::assertNull($player->weightPounds);
        self::assertNull($player->isRetired);
    }

    public function testFillFromCurrentRowStripsSlashesFromName(): void
    {
        $row = $this->buildFullCurrentRow();
        $row['name'] = "O\\'Brien";

        $player = $this->repository->fillFromCurrentRow($row);
        self::assertSame("O'Brien", $player->name);
    }

    public function testFieldMapCoversAllPlayerDataProperties(): void
    {
        $reflection = new \ReflectionClass(PlayerData::class);
        $allProperties = array_map(
            static fn (\ReflectionProperty $p): string => $p->getName(),
            $reflection->getProperties()
        );

        $mappedTargets = [];
        foreach (PlayerRepository::FIELD_MAP as $entries) {
            foreach ($entries as $entry) {
                $mappedTargets[] = $entry['target'];
            }
        }
        $mappedTargets = array_unique($mappedTargets);

        $accountedFor = array_merge($mappedTargets, PlayerRepository::EXCLUDED_FROM_FIELD_MAP);

        $unmapped = array_diff($allProperties, $accountedFor);

        self::assertSame(
            [],
            array_values($unmapped),
            'PlayerData properties not covered by FIELD_MAP or EXCLUDED_FROM_FIELD_MAP: ' . implode(', ', $unmapped)
        );
    }

    public function testFieldMapTargetsAreValidPlayerDataProperties(): void
    {
        $reflection = new \ReflectionClass(PlayerData::class);
        $validProperties = array_map(
            static fn (\ReflectionProperty $p): string => $p->getName(),
            $reflection->getProperties()
        );

        foreach (PlayerRepository::FIELD_MAP as $category => $entries) {
            foreach ($entries as $entry) {
                self::assertContains(
                    $entry['target'],
                    $validProperties,
                    "FIELD_MAP[{$category}] target '{$entry['target']}' is not a valid PlayerData property"
                );
            }
        }
    }

    public function testExcludedPropertiesAreValidPlayerDataProperties(): void
    {
        $reflection = new \ReflectionClass(PlayerData::class);
        $validProperties = array_map(
            static fn (\ReflectionProperty $p): string => $p->getName(),
            $reflection->getProperties()
        );

        foreach (PlayerRepository::EXCLUDED_FROM_FIELD_MAP as $prop) {
            self::assertContains(
                $prop,
                $validProperties,
                "EXCLUDED_FROM_FIELD_MAP entry '{$prop}' is not a valid PlayerData property"
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFullCurrentRow(): array
    {
        return [
            'pid' => 100,
            'ordinal' => 5,
            'name' => 'Test Player',
            'nickname' => 'TP',
            'age' => 25,
            'teamid' => 1,
            'teamname' => 'Test Team',
            'color1' => 'FF0000',
            'color2' => '0000FF',
            'pos' => 'PG',
            'r_fga' => 3,
            'r_fgp' => 4,
            'r_fta' => 2,
            'r_ftp' => 5,
            'r_3ga' => 3,
            'r_3gp' => 4,
            'r_orb' => 2,
            'r_drb' => 3,
            'r_ast' => 4,
            'r_stl' => 3,
            'r_tvr' => 2,
            'r_blk' => 3,
            'r_foul' => 2,
            'oo' => 4,
            'od' => 3,
            'r_drive_off' => 4,
            'dd' => 3,
            'po' => 4,
            'pd' => 3,
            'r_trans_off' => 4,
            'td' => 3,
            'clutch' => 5,
            'consistency' => 4,
            'talent' => 5,
            'skill' => 4,
            'intangibles' => 3,
            'loyalty' => 3,
            'playing_time' => 4,
            'winner' => 2,
            'tradition' => 3,
            'security' => 4,
            'exp' => 5,
            'bird' => 3,
            'cy' => 2,
            'cyt' => 4,
            'salary_yr1' => 5000,
            'salary_yr2' => 5500,
            'salary_yr3' => 6000,
            'salary_yr4' => 6500,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
            'draftyear' => 2020,
            'draftround' => 1,
            'draftpickno' => 5,
            'draftedby' => 'Original Team',
            'draftedbycurrentname' => 'Current Team',
            'college' => 'Test University',
            'htft' => 6,
            'htin' => 3,
            'wt' => 195,
            'injured' => 0,
            'retired' => 0,
            'droptime' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFullHistoricalRow(): array
    {
        return [
            'pid' => 200,
            'year' => 2015,
            'name' => 'Historical Player',
            'team' => 'Old Team',
            'teamid' => 5,
            'r_2ga' => 3,
            'r_2gp' => 4,
            'r_fta' => 2,
            'r_ftp' => 5,
            'r_3ga' => 3,
            'r_3gp' => 4,
            'r_orb' => 2,
            'r_drb' => 3,
            'r_ast' => 4,
            'r_stl' => 3,
            'r_blk' => 3,
            'r_tvr' => 2,
            'r_oo' => 4,
            'r_od' => 3,
            'r_drive_off' => 4,
            'r_dd' => 3,
            'r_po' => 4,
            'r_pd' => 3,
            'r_trans_off' => 4,
            'r_td' => 3,
            'salary' => 8000,
        ];
    }
}
