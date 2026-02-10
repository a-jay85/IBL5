<?php

declare(strict_types=1);

namespace Api;

use Api\Contracts\RouterInterface;

class Router implements RouterInterface
{
    private const UUID_PATTERN = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';
    private const CONFERENCE_PATTERN = 'East(?:ern)?|West(?:ern)?';
    private const OFFER_ID_PATTERN = '\d+';

    /** @var array<string, string> GET route patterns to controller class names. Order matters: specific first. */
    private const GET_ROUTES = [
        'players/{uuid}/stats'    => Controller\PlayerStatsController::class,
        'players/{uuid}/history'  => Controller\PlayerHistoryController::class,
        'players/{uuid}'          => Controller\PlayerDetailController::class,
        'players'                 => Controller\PlayerListController::class,
        'teams/{uuid}/roster'     => Controller\TeamRosterController::class,
        'teams/{uuid}'            => Controller\TeamDetailController::class,
        'teams'                   => Controller\TeamListController::class,
        'standings/{conference}'  => Controller\StandingsController::class,
        'standings'               => Controller\StandingsController::class,
        'games/{uuid}/boxscore'   => Controller\GameBoxscoreController::class,
        'games/{uuid}'            => Controller\GameDetailController::class,
        'games'                   => Controller\GameListController::class,
        'stats/leaders'           => Controller\LeadersController::class,
        'injuries'                => Controller\InjuriesController::class,
        'season'                  => Controller\SeasonController::class,
    ];

    /** @var array<string, string> POST route patterns to controller class names. */
    private const POST_ROUTES = [
        'trades/{offerId}/accept'  => Controller\TradeAcceptController::class,
        'trades/{offerId}/decline' => Controller\TradeDeclineController::class,
    ];

    /**
     * @see RouterInterface::match()
     */
    public function match(string $path, string $method): ?array
    {
        $routes = match ($method) {
            'GET' => self::GET_ROUTES,
            'POST' => self::POST_ROUTES,
            default => [],
        };

        $path = trim($path, '/');

        foreach ($routes as $pattern => $controllerClass) {
            $params = $this->matchPattern($pattern, $path);
            if ($params !== null) {
                return [
                    'controller' => $controllerClass,
                    'params' => $params,
                ];
            }
        }

        return null;
    }

    /**
     * Match a route pattern against a URL path, extracting named parameters.
     *
     * @return array<string, string>|null Extracted parameters, or null if no match
     */
    private function matchPattern(string $pattern, string $path): ?array
    {
        $regex = $this->patternToRegex($pattern);
        if (preg_match($regex, $path, $matches) !== 1) {
            return null;
        }

        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    /**
     * Convert a route pattern to a regex.
     * {uuid} -> UUID regex, {conference} -> East|West
     */
    private function patternToRegex(string $pattern): string
    {
        $regex = preg_quote($pattern, '#');

        // Replace escaped placeholders with capturing groups
        $regex = str_replace(
            [preg_quote('{uuid}', '#'), preg_quote('{conference}', '#'), preg_quote('{offerId}', '#')],
            ['(?P<uuid>' . self::UUID_PATTERN . ')', '(?P<conference>' . self::CONFERENCE_PATTERN . ')', '(?P<offerId>' . self::OFFER_ID_PATTERN . ')'],
            $regex
        );

        // Replace / with escaped /
        $regex = str_replace('/', '\\/', $regex);

        return '#^' . $regex . '$#i';
    }
}
