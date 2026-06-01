<?php

declare(strict_types=1);

namespace EngineRunner;

/**
 * Thrown when the native engine process cannot be run or its output is unusable
 * (missing/invalid binary, nonzero exit, empty stdout, or malformed JSON).
 */
final class EngineRunnerException extends \RuntimeException
{
}
