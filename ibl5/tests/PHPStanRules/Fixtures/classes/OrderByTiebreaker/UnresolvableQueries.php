<?php

declare(strict_types=1);

namespace Tests\PHPStanRules\Fixtures\OrderByTiebreaker;

final class UnresolvableQueries
{
    public function query1(): string
    {
        return 'SELECT year FROM fx_missing ORDER BY year, table_id';
    }

    public function query2(): string
    {
        return 'SELECT a.year FROM fx_awards a JOIN fx_players p ON p.name = a.name ORDER BY a.year, a.table_id';
    }

    public function query3(): string
    {
        return 'SELECT year FROM fx_awards WHERE name IN (SELECT name FROM fx_players) ORDER BY year, award, name';
    }

    public function query4(): string
    {
        return 'SELECT year FROM fx_awards WHERE year IN (SELECT ?) ORDER BY year, award, name';
    }

    public function query5(): string
    {
        return 'SELECT year FROM fx_awards ORDER BY %s, table_id';
    }

    public function query6(): string
    {
        return 'SELECT DISTINCT year FROM fx_awards ORDER BY year, table_id';
    }
}
