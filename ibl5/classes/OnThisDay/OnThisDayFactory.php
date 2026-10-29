<?php

declare(strict_types=1);

namespace OnThisDay;

/**
 * OnThisDayFactory - the one composition root for "on this day".
 *
 * The homepage, the updater pipeline, and the pre-prod integration test all
 * build their object graph here, so they run the same code.
 */
final class OnThisDayFactory
{
    public static function createService(
        \mysqli $db,
        ?\League\LeagueContext $leagueContext = null,
    ): OnThisDayService {
        $repository = new OnThisDayRepository($db, $leagueContext);

        return new OnThisDayService(
            $repository,
            $repository,
            new \Season\SeasonQueryRepository($db, $leagueContext),
            new WowScorer(),
            new OnThisDayCalendar(),
        );
    }

    public static function createRebuilder(\mysqli $db): OnThisDayMomentBuilder
    {
        return new OnThisDayMomentBuilder($db);
    }

    public static function createPostStep(\mysqli $db): \Updater\Steps\PostOnThisDayStep
    {
        return new \Updater\Steps\PostOnThisDayStep(
            self::createService($db),
            new OnThisDayRepository($db),
            new \Season\SeasonQueryRepository($db),
            new DiscordMomentPoster(),
        );
    }
}
