<?php

declare(strict_types=1);

namespace Tests\Support;

use Eris\TestTrait;

/**
 * Eris TestTrait with a reproducible seed. Call pinErisSeed() first in every
 * property test, before forAll(); forAll() reads $this->seed.
 */
trait PinnedErisSeed
{
    use TestTrait;

    protected function pinErisSeed(int $iterations): void
    {
        $this->seed = ErisSeed::resolve(getenv('ERIS_SEED'));
        $this->limitTo($iterations);
        $this->shrinkingTimeLimit(ErisSeed::SHRINK_SECONDS);
    }
}
