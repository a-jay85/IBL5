<?php

declare(strict_types=1);

namespace OnThisDay\Contracts;

use OnThisDay\Moment;

interface OnThisDayMomentBuilderInterface
{
    /**
     * Every moment in league history, with no calendar window.
     *
     * @return list<Moment>
     */
    public function collect(): array;

    /**
     * Replace the stored moments table with collect() in one transaction.
     *
     * @return int Rows written
     */
    public function rebuild(): int;
}
