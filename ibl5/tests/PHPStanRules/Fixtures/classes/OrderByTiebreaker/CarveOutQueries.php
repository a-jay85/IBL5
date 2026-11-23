<?php

declare(strict_types=1);

namespace Tests\PHPStanRules\Fixtures\OrderByTiebreaker;

final class CarveOutQueries
{
    public function query1(): string
    {
        return 'SELECT year, COUNT(*) FROM fx_awards GROUP BY year ORDER BY year';
    }

    public function query2(): string
    {
        return 'SELECT year FROM fx_awards ORDER BY year LIMIT 1';
    }

    public function query3(): string
    {
        return 'SELECT year FROM fx_missing ORDER BY year, id';
    }

    public function query4(): string
    {
        return 'SELECT year FROM fx_awards';
    }
}
