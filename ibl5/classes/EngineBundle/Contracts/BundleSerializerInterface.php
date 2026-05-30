<?php

declare(strict_types=1);

namespace EngineBundle\Contracts;

use EngineBundle\Dto\Bundle;

/**
 * Serializes a {@see Bundle} to the JSON shape the Go engine decodes
 * (engine/internal/bundle/bundle.go). This interface owns the contract: the
 * JSON key spelling for teams, games, and the bundle envelope lives in the
 * implementation, the single place that translates PHP/DB names to Go tags.
 */
interface BundleSerializerInterface
{
    public function serialize(Bundle $bundle): string;
}
