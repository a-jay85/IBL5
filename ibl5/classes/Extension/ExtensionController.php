<?php

declare(strict_types=1);

namespace Extension;

use Extension\Contracts\ExtensionControllerInterface;
use Extension\Contracts\ExtensionProcessorInterface;
use Repositories\Contracts\TeamIdentityRepositoryInterface;

/**
 * Contract-extension POST handler. Absorbs the procedural body of
 * modules/Player/extension.php, which stays as a thin shim because
 * NegotiationOfferView hard-codes that path as the form action.
 *
 * @see ExtensionControllerInterface
 */
final class ExtensionController implements ExtensionControllerInterface
{
    private const HOME_URL = '/ibl5/index.php';
    private const FORBIDDEN_URL = '/ibl5/index.php?result=extension_forbidden';

    public function __construct(
        private readonly TeamIdentityRepositoryInterface $teamIdentityRepo,
        private readonly ExtensionProcessorInterface $processor,
    ) {
    }

    /**
     * @see ExtensionControllerInterface::handleSubmission()
     */
    public function handleSubmission(\Closure $csrfValid, \Closure $isUser, \Closure $resolveUsername, array $post): string
    {
        if (!$csrfValid()) {
            return self::HOME_URL;
        }

        // Auth + ownership gate (IDOR fix D-10).
        if (!$isUser()) {
            return self::HOME_URL;
        }

        $username = $resolveUsername();
        $sessionTeam = $this->teamIdentityRepo->getTeamnameFromUsername($username);
        if ($sessionTeam === null || $sessionTeam === '' || $sessionTeam === \League\League::FREE_AGENTS_TEAM_NAME) {
            return self::HOME_URL;
        }

        // Ownership check: the POSTed team must equal the session team. A logged-in GM
        // cannot replay a valid CSRF token against another team. The distinct signal
        // makes the IDOR rejection provable (not the generic not-found bounce).
        $postedTeam = is_string($post['teamName'] ?? null) ? $post['teamName'] : '';
        if ($postedTeam !== $sessionTeam) {
            return self::FORBIDDEN_URL;
        }

        // Use the session team as the authoritative value downstream — never POST input.
        $teamName = $sessionTeam;
        $playerID = self::intField($post, 'playerID');
        $playerName = is_string($post['playerName'] ?? null) ? $post['playerName'] : '';
        $demandsYears = self::intField($post, 'demandsYears');
        $demandsTotal = self::intField($post, 'demandsTotal');

        // Build offer array
        $offer = [
            'year1' => self::intField($post, 'offerYear1'),
            'year2' => self::intField($post, 'offerYear2'),
            'year3' => self::intField($post, 'offerYear3'),
            'year4' => self::intField($post, 'offerYear4'),
            'year5' => self::intField($post, 'offerYear5'),
        ];

        // Build demands array
        $demands = [
            'total' => $demandsTotal,
            'years' => $demandsYears,
        ];

        // Build extension data for processor
        $extensionData = [
            'teamName' => $teamName,
            'playerID' => $playerID,
            'playerName' => $playerName,
            'offer' => $offer,
            'demands' => $demands,
        ];

        $result = $this->processor->processExtension($extensionData);

        $teamid = $this->teamIdentityRepo->getTidFromTeamname($teamName);

        if ($teamid === null) {
            return self::HOME_URL;
        }

        $redirectBase = '/ibl5/modules.php?name=Team&op=team&teamid=' . $teamid . '&display=contracts';

        if (!$result['success']) {
            return $redirectBase . '&result=extension_error&msg=' . rawurlencode($result['error']);
        }

        if ($result['accepted']) {
            return $redirectBase . '&result=extension_accepted&msg=' . rawurlencode($result['message']);
        }

        return $redirectBase . '&result=extension_rejected&msg=' . rawurlencode($result['message']);
    }

    /**
     * Read a POSTed integer field; non-scalar (array) input is treated as 0.
     *
     * @param array<array-key, mixed> $post
     */
    private static function intField(array $post, string $key): int
    {
        $value = $post[$key] ?? 0;

        return is_string($value) || is_int($value) ? (int) $value : 0;
    }
}
