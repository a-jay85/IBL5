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
        'DebugMenu',
        'DepthChartEntry',
        'Draft',
        'DraftHistory',
        'DraftPickLocator',
        'FranchiseHistory',
        'FranchiseRecordBook',
        'FreeAgency',
        'FreeAgencyPreview',
        'GMContactList',
        'Injuries',
        'LeagueStarters',
        'News',
        'NextSim',
        'Notifications',
        'OneOnOneGame',
        'Player',
        'PlayerDatabase',
        'PlayerExportGuide',
        'PlayerMovement',
        'ProjectedDraftOrder',
        'RecordHolders',
        'Schedule',
        'Search',
        'SeasonArchive',
        'SeasonHighs',
        'SeasonLeaderboards',
        'SeriesRecords',
        'Standings',
        'Team',
        'TeamOffDefStats',
        'Topics',
        'TradeBlock',
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
