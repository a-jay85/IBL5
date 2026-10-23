<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Decides whether a deal is worth suggesting, and why. Pure.
 *
 * Cheap gates run first, in this order:
 * 1. Every player is tradeable, neither roster goes over Team::ROSTER_SPOTS_MAX,
 *    and neither payroll goes over the hard cap (when TeamAssets knows its cap
 *    room). These are hints only; the real validator still runs on anything shown.
 * 2. Meaningful: at least one player moved is in his team's top eight by ovr.
 * 3. Value balance: by its own mode, neither side gives up more than
 *    BALANCE_TOLERANCE (or BALANCE_SLACK, if larger) above what it gets.
 * Then each team's roster is scored before and after (TeamNeedsScorer) and both
 * gains must reach MIN_GAIN.
 */
final class TradeImpactEvaluator
{
    public const float MIN_GAIN = 1.0;
    public const float BALANCE_TOLERANCE = 0.15;
    public const float BALANCE_SLACK = 2.0;

    /** @var \WeakMap<TeamAssets, array<string, TeamScore>> */
    private \WeakMap $beforeScores;

    /** @var \WeakMap<TeamAssets, array<int, true>> */
    private \WeakMap $rotationPids;

    public function __construct(
        private readonly TeamNeedsScorer $scorer = new TeamNeedsScorer(),
        private readonly ReasonBuilder $reasons = new ReasonBuilder(),
        private readonly AssetValuer $valuer = new AssetValuer(),
    ) {
        $this->beforeScores = new \WeakMap();
        $this->rotationPids = new \WeakMap();
    }

    public function evaluate(
        TradeCandidate $c,
        TeamAssets $user,
        TeamAssets $partner,
        TeamMode $userMode,
        TeamMode $partnerMode,
    ): ScoredCandidate|RejectReason {
        $userOut = self::players($user, $c->userPids);
        $partnerOut = self::players($partner, $c->partnerPids);
        $userOutPicks = self::picks($user, $c->userPickIds);
        $partnerOutPicks = self::picks($partner, $c->partnerPickIds);

        foreach ([...$userOut, ...$partnerOut] as $player) {
            if (!$player->tradeable) {
                return RejectReason::Untradeable;
            }
        }

        $userRoster = $user->rosterCount() - count($userOut) + count($partnerOut);
        $partnerRoster = $partner->rosterCount() - count($partnerOut) + count($userOut);
        if (max($userRoster, $partnerRoster) > \Team\Team::ROSTER_SPOTS_MAX) {
            return RejectReason::RosterFull;
        }

        $userSalaryOut = self::salary($userOut);
        $partnerSalaryOut = self::salary($partnerOut);
        if (
            ($user->capRoom !== null && $partnerSalaryOut - $userSalaryOut > $user->capRoom)
            || ($partner->capRoom !== null && $userSalaryOut - $partnerSalaryOut > $partner->capRoom)
        ) {
            return RejectReason::OverHardCap;
        }

        if (!$this->movesRotationPlayer($user, $userOut) && !$this->movesRotationPlayer($partner, $partnerOut)) {
            return RejectReason::NotMeaningful;
        }

        if (!$this->balanced($userMode, $userOut, $userOutPicks, $partnerOut, $partnerOutPicks)) {
            return RejectReason::UserGivesTooMuch;
        }
        if (!$this->balanced($partnerMode, $partnerOut, $partnerOutPicks, $userOut, $userOutPicks)) {
            return RejectReason::PartnerGivesTooMuch;
        }

        $userAfter = $user->afterTrade($c->userPids, $c->userPickIds, $partnerOut, $partnerOutPicks);
        $partnerAfter = $partner->afterTrade($c->partnerPids, $c->partnerPickIds, $userOut, $userOutPicks);

        $userBefore = $this->beforeScore($user, $userMode);
        $partnerBefore = $this->beforeScore($partner, $partnerMode);
        $userAfterScore = $this->scorer->score($userAfter, $userMode);
        $partnerAfterScore = $this->scorer->score($partnerAfter, $partnerMode);

        $userGain = $userAfterScore->total - $userBefore->total;
        if ($userGain < self::MIN_GAIN) {
            return RejectReason::NoUserGain;
        }
        $partnerGain = $partnerAfterScore->total - $partnerBefore->total;
        if ($partnerGain < self::MIN_GAIN) {
            return RejectReason::NoPartnerGain;
        }

        $userOptions = $this->reasons->options($userMode, $userAfter, $partnerOut, $userOut, $partnerOutPicks, $userBefore, $userAfterScore);
        $partnerOptions = $this->reasons->options($partnerMode, $partnerAfter, $userOut, $partnerOut, $userOutPicks, $partnerBefore, $partnerAfterScore);
        [$userReason, $partnerReason] = self::distinctReasons($userOptions, $partnerOptions);

        $significance = 0.0;
        foreach ([...$userOut, ...$partnerOut] as $player) {
            $significance = max($significance, AssetValuer::baseValue($player->ovr));
        }

        return new ScoredCandidate($c, (int) round(min($userGain, $partnerGain) + 0.1 * $significance), $userReason, $partnerReason);
    }

    /**
     * Each side's best reason, except the partner skips any line identical to
     * the user's so the two never read the same.
     *
     * @param non-empty-list<string> $userOptions Best first
     * @param non-empty-list<string> $partnerOptions Best first
     * @return array{string, string}
     */
    public static function distinctReasons(array $userOptions, array $partnerOptions): array
    {
        foreach ($partnerOptions as $option) {
            if ($option !== $userOptions[0]) {
                return [$userOptions[0], $option];
            }
        }

        return [$userOptions[0], $partnerOptions[0]];
    }

    /**
     * @param list<SuggestionPlayer> $players
     */
    private static function salary(array $players): int
    {
        $total = 0;
        foreach ($players as $player) {
            $total += $player->currentSalary;
        }

        return $total;
    }

    /**
     * True when what a side gets is worth at least what it gives, less the tolerance.
     *
     * @param list<SuggestionPlayer> $outPlayers
     * @param list<SuggestionPick> $outPicks
     * @param list<SuggestionPlayer> $inPlayers
     * @param list<SuggestionPick> $inPicks
     */
    private function balanced(TeamMode $mode, array $outPlayers, array $outPicks, array $inPlayers, array $inPicks): bool
    {
        $out = $this->rawValue($mode, $outPlayers, $outPicks);
        $in = $this->rawValue($mode, $inPlayers, $inPicks);

        return $out - $in <= max(self::BALANCE_SLACK, self::BALANCE_TOLERANCE * $out);
    }

    /**
     * @param list<SuggestionPlayer> $players
     * @param list<SuggestionPick> $picks
     */
    private function rawValue(TeamMode $mode, array $players, array $picks): float
    {
        $value = 0.0;
        foreach ($players as $player) {
            $value += $this->valuer->playerValue($player, $mode);
        }
        foreach ($picks as $pick) {
            $value += $this->valuer->pickValue($pick, $mode);
        }

        return $value;
    }

    /**
     * @param list<SuggestionPlayer> $sent
     */
    private function movesRotationPlayer(TeamAssets $team, array $sent): bool
    {
        if (!isset($this->rotationPids[$team])) {
            $pids = [];
            foreach (TeamModeGuesser::rotation($team->players) as $player) {
                $pids[$player->pid] = true;
            }
            $this->rotationPids[$team] = $pids;
        }
        $rotation = $this->rotationPids[$team];
        foreach ($sent as $player) {
            if (isset($rotation[$player->pid])) {
                return true;
            }
        }

        return false;
    }

    private function beforeScore(TeamAssets $team, TeamMode $mode): TeamScore
    {
        $scores = $this->beforeScores[$team] ?? [];
        if (!isset($scores[$mode->name])) {
            $scores[$mode->name] = $this->scorer->score($team, $mode);
            $this->beforeScores[$team] = $scores;
        }

        return $scores[$mode->name];
    }

    /**
     * @param list<int> $pids
     * @return list<SuggestionPlayer>
     */
    private static function players(TeamAssets $assets, array $pids): array
    {
        return array_values(array_filter(
            $assets->players,
            static fn (SuggestionPlayer $p): bool => in_array($p->pid, $pids, true)
        ));
    }

    /**
     * @param list<int> $pickIds
     * @return list<SuggestionPick>
     */
    private static function picks(TeamAssets $assets, array $pickIds): array
    {
        return array_values(array_filter(
            $assets->picks,
            static fn (SuggestionPick $p): bool => in_array($p->pickId, $pickIds, true)
        ));
    }
}
