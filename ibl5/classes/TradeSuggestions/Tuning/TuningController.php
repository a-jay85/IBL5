<?php

declare(strict_types=1);

namespace TradeSuggestions\Tuning;

use TradeSuggestions\Tuning\Contracts\TuningAssemblerInterface;
use TradeSuggestions\Tuning\Contracts\TuningViewInterface;

/**
 * GET-only tuning page controller. It never reads a superglobal: the route
 * passes `$_GET` in after the admin gate has run.
 */
final readonly class TuningController
{
    public function __construct(
        private TuningAssemblerInterface $assembler,
        private TuningViewInterface $view,
    ) {
    }

    /**
     * @param array<mixed> $query the request's GET parameters
     */
    public function handle(array $query): string
    {
        $filter = TuningFilter::fromQuery($query);

        return $this->view->render($this->assembler->build($filter));
    }
}
