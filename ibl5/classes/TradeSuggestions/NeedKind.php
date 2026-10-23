<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * One kind of team need. Priority 1 is the most pressing.
 */
enum NeedKind
{
    case OverHardCap;
    case BadContract;
    case DeadWeight;
    case WeakStarter;
    case ExpiringStarOnLoser;
    case CappedOut;
    case Logjam;
    case Consolidate;
    case RoomToAbsorb;
    case WinNow;
    case Rebuild;
    case FutureCapStrain;

    public function priority(): int
    {
        return match ($this) {
            self::OverHardCap => 1,
            self::BadContract => 2,
            self::DeadWeight => 3,
            self::WeakStarter => 4,
            self::ExpiringStarOnLoser => 5,
            self::CappedOut => 6,
            self::Logjam => 7,
            self::Consolidate => 8,
            self::RoomToAbsorb => 9,
            self::WinNow, self::Rebuild => 10,
            self::FutureCapStrain => 99,
        };
    }

    /**
     * @return list<Asset>
     */
    public function gives(): array
    {
        return match ($this) {
            self::OverHardCap, self::BadContract => [Asset::Salary, Asset::Sweetener],
            self::DeadWeight, self::CappedOut => [Asset::Salary],
            self::WeakStarter, self::Consolidate => [Asset::Surplus, Asset::Picks],
            self::ExpiringStarOnLoser, self::Rebuild => [Asset::Veterans],
            self::Logjam => [Asset::Surplus],
            self::RoomToAbsorb => [Asset::CapRoom, Asset::RosterSpot],
            self::WinNow => [Asset::Picks, Asset::YoungPlayers, Asset::Surplus],
            self::FutureCapStrain => [],
        };
    }

    /**
     * @return list<Asset>
     */
    public function wants(): array
    {
        return match ($this) {
            self::OverHardCap, self::DeadWeight => [Asset::LessSalary],
            self::BadContract, self::FutureCapStrain => [Asset::ShorterContract],
            self::WeakStarter, self::Logjam, self::Consolidate => [Asset::Starter],
            self::ExpiringStarOnLoser, self::RoomToAbsorb => [Asset::Picks, Asset::YoungPlayers],
            self::CappedOut => [Asset::ShorterContract, Asset::LessSalary],
            self::WinNow => [Asset::Veterans],
            self::Rebuild => [Asset::Picks, Asset::YoungPlayers, Asset::CapRoom],
        };
    }
}
