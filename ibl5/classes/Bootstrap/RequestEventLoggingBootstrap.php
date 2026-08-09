<?php

declare(strict_types=1);

namespace Bootstrap;

use Bootstrap\Contracts\BootstrapStepInterface;
use Bootstrap\Contracts\ContainerInterface;
use EventLog\EventLogger;
use EventLog\EventLogRepository;
use EventLog\RouteNameNormalizer;
use EventLog\TrafficClassifier;
use Logging\LoggerFactory;
use Repositories\TeamIdentityRepository;

/**
 * Records one ibl_events row per web request for product analytics.
 *
 * Registered LAST in WebApplicationFactory (after DemoModeBootstrap) so the
 * EFFECTIVE user is logged. Runs before the PageCache short-circuit in
 * modules.php, so cache-hit pageviews are still counted. Fire-and-forget:
 * any failure is swallowed and logged; the page is never broken.
 */
class RequestEventLoggingBootstrap implements BootstrapStepInterface
{
    private const MAX_URI = 512;
    private const MAX_ROUTE = 64;
    private const MAX_METHOD = 10;
    private const MAX_HEADER = 512;

    public function boot(ContainerInterface $container): void
    {
        // (1) No-op for CLI and any entry lacking a real request line.
        //     mainfile.php is included by CLI scripts too — do not log junk rows.
        if (\PHP_SAPI === 'cli' || !isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        try {
            // (2) Capture from superglobals; every value is mixed → narrow with is_string().
            $rawUri = $_SERVER['REQUEST_URI'];
            $requestUri = \is_string($rawUri) ? $this->trunc($rawUri, self::MAX_URI) : '';

            // Read ?name= RAW (bootstrap runs before modules.php sanitizes it at :24-29);
            // sanitize independently to a module-name charset, null when absent/empty.
            // Normalization runs before truncation (normalization can lengthen a value).
            $routeName = null;
            if (isset($_GET['name']) && \is_string($_GET['name'])) {
                $clean = preg_replace('/[^A-Za-z0-9_-]/', '', $_GET['name']);
                if ($clean !== null && $clean !== '') {
                    $normalized = RouteNameNormalizer::normalize($clean);
                    $routeName = $normalized === null ? null : $this->trunc($normalized, self::MAX_ROUTE);
                }
            }

            $method = $_SERVER['REQUEST_METHOD'] ?? '';
            $httpMethod = \is_string($method) ? $this->trunc($method, self::MAX_METHOD) : '';

            $ref = $_SERVER['HTTP_REFERER'] ?? null;
            $referer = \is_string($ref) ? $this->trunc($ref, self::MAX_HEADER) : null;

            $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $userAgent = \is_string($ua) ? $this->trunc($ua, self::MAX_HEADER) : null;

            // Session id — hashed for storage; never the raw token (D5).
            $sessionId = null;
            if (session_status() === \PHP_SESSION_ACTIVE) {
                $rawSession = session_id();
                if (is_string($rawSession) && $rawSession !== '') {
                    $sessionId = hash('sha256', $rawSession);
                }
            }

            // (3) Resolve effective user + current team.
            $authService = $GLOBALS['authService'] ?? null;
            $username = null;
            if ($authService instanceof \Auth\Contracts\AuthServiceInterface && $authService->isAuthenticated()) {
                $username = $authService->getUsername(); // ?string
            }

            $teamId = null;
            $db = $GLOBALS['mysqli_db'] ?? null;
            if ($db instanceof \mysqli) {
                if ($username !== null) {
                    $teamRepo = new TeamIdentityRepository($db);
                    // getTeamnameFromUsername returns FREE_AGENTS name if empty; null if unknown.
                    $teamName = $teamRepo->getTeamnameFromUsername($username);
                    if ($teamName !== null) {
                        $teamId = $teamRepo->getTidFromTeamname($teamName); // ?int
                    }
                }

                // Classify traffic — read op from $_GET, not REQUEST_URI scan (D4).
                $op = isset($_GET['op']) && \is_string($_GET['op']) ? $_GET['op'] : null;
                $trafficClass = TrafficClassifier::classify($username, $userAgent, $op);

                // (4) Synchronous guarded INSERT, then arm the outcome flush.
                $eventId = (new EventLogRepository($db))->insert(
                    $requestUri,
                    $routeName,
                    $httpMethod,
                    $username,
                    $teamId,
                    $referer,
                    $userAgent,
                    $sessionId,
                    $trafficClass
                );
                if ($eventId > 0) {
                    EventLogger::arm($eventId, $db);
                }
            }
        } catch (\Throwable $e) {
            // Fire-and-forget: never break the page. Log and move on.
            LoggerFactory::getChannel('perf')->warning(
                'Request event logging failed',
                ['exception' => $e->getMessage()]
            );
        }
    }

    private function trunc(string $value, int $max): string
    {
        return mb_strcut($value, 0, $max, 'UTF-8');
    }
}
