<?php

declare(strict_types=1);

namespace Module;

final class ModuleRegistry
{
    /** @var list<string> */
    private const VALID_MODULES = [
        'ActivityTracker',
        'AllStarAppearances',
        'ApiKeys',
        'AwardHistory',
        'CapSpace',
        'CareerLeaderboards',
        'ComparePlayers',
        'ContractList',
        'Contracts',
        'DebugMenu',
        'DepthChartEntry',
        'Draft',
        'DraftHistory',
        'DraftInfo',
        'DraftPickLocator',
        'FranchiseHistory',
        'FranchiseRecordBook',
        'FreeAgency',
        'FreeAgencyPreview',
        'GameBoxscore',
        'GMContactList',
        'HeadToHeadRecords',
        'Injuries',
        'Leaderboards',
        'LeagueControlPanel',
        'LeagueStarters',
        'News',
        'NextSim',
        'OneOnOneGame',
        'Player',
        'PlayerExportGuide',
        'PlayerSearch',
        'Privacy',
        'ProjectedDraftOrder',
        'RecordHolders',
        'Records',
        'Rivalries',
        'Schedule',
        'Search',
        'SeasonArchive',
        'SeasonHighs',
        'SeasonLeaderboards',
        'SeasonRosterChanges',
        'Standings',
        'Team',
        'TeamOffDefStats',
        'Terms',
        'Topics',
        'Trading',
        'TrainingCampRatingsDiff',
        'TransactionHistory',
        'Voting',
        'VotingResults',
        'Waivers',
        'YourAccount',
    ];

    /** @return list<string> */
    public static function getAllModules(): array
    {
        return self::VALID_MODULES;
    }

    public static function isValid(string $name): bool
    {
        return in_array($name, self::VALID_MODULES, true);
    }
}
