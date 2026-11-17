<?php

declare(strict_types=1);

namespace SimRecap;

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

    /**
     * @param callable(): void $poke Called after a successful enqueue; may throw.
     */
    public function __construct(
        private readonly SimPublishProgressRepository $progress,
        callable $poke,
    ) {
        $this->poke = $poke;
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
        } catch (\Throwable) {
            // Fixed line only: the exception text can carry the URL.
            fwrite(STDERR, "sim-recap publish: poke failed\n");
        }

        return true;
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
