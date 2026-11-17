<?php

declare(strict_types=1);

namespace SimRecap;

use Psr\Log\LoggerInterface;

/**
 * Marks a sim ready for the Discord publisher and pokes IBLbot to sweep early.
 *
 * The enqueue is the durable part: IBLbot also sweeps on a timer, so a failed
 * poke only delays the drip. A poke failure never undoes the enqueue.
 */
final class PublishEnqueuer
{
    /** @var callable(): void */
    private $poke;

    private readonly LoggerInterface $logger;

    /**
     * @param callable(): void $poke Called after a successful enqueue; may throw.
     * @param LoggerInterface|null $logger Defaults to the LoggerFactory sim-recap channel.
     */
    public function __construct(
        private readonly SimPublishProgressRepository $progress,
        callable $poke,
        ?LoggerInterface $logger = null,
    ) {
        $this->poke = $poke;
        $this->logger = $logger ?? \Logging\LoggerFactory::getChannel('sim-recap');
    }

    /**
     * Enqueue $sim when $phase is publishable. Returns false, writing nothing, otherwise.
     */
    public function enqueue(int $sim, string $phase): bool
    {
        if (!PublishPhasePolicy::isPublishable($phase)) {
            return false;
        }

        $this->progress->enqueue($sim);

        try {
            ($this->poke)();
        } catch (\Throwable $e) {
            // Fixed line only: the exception text can carry the URL.
            fwrite(STDERR, "sim-recap publish: poke failed\n");
            self::logError($this->logger, 'sim-recap publish: poke failed', ['sim' => $sim, 'exception_class' => $e::class]);
        }

        return true;
    }

    /**
     * The notify verb's hand-off. A failure writes the fixed STDERR line, logs at error
     * level for #errors, and never escapes, so the notify exit code never changes.
     *
     * @param callable(): bool $enqueue
     * @param resource $stderr Where the fixed failure line goes; tests pass a memory stream.
     */
    public static function guardedEnqueue(int $sim, callable $enqueue, ?LoggerInterface $logger = null, mixed $stderr = STDERR): void
    {
        try {
            $enqueue();
        } catch (\Throwable $failure) {
            fwrite($stderr,'simRecapQueue: publish enqueue failed (' . $failure::class . ")\n");
            self::logError($logger ?? \Logging\LoggerFactory::getChannel('sim-recap'), 'simRecapQueue: publish enqueue failed', [
                'sim' => $sim,
                'exception_class' => $failure::class,
            ]);
        }
    }

    /**
     * @param array<string, int|string> $context
     */
    private static function logError(LoggerInterface $logger, string $message, array $context): void
    {
        try {
            $logger->error($message, $context);
        } catch (\Throwable) {
            // A broken log handler must never break the enqueue or the notify verb.
        }
    }

    /**
     * POST an empty body to IBLbot's local /simRecapPublish route (2 s timeout).
     * IBLbot answers with a 2xx once it has started a sweep.
     *
     * @throws \RuntimeException on a missing base URL, a transport error, or a non-2xx reply
     */
    public static function pokeIblbot(): void
    {
        $base = \Discord\Discord::getIblbotUrl();
        if ($base === '') {
            throw new \RuntimeException('iblbot_url is not configured');
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $base . '/simRecapPublish',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 2,
        ]);
        curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $failed = curl_errno($curl) !== 0;
        // curl_close() is deprecated since PHP 8.0 and the handle frees itself.

        if ($failed || $httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException('poke failed');
        }
    }
}
