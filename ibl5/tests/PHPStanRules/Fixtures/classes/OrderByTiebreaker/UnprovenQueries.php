<?php

declare(strict_types=1);

namespace Tests\PHPStanRules\Fixtures\OrderByTiebreaker;

final class UnprovenQueries
{
    public function query1(): string
    {
        return 'SELECT cid FROM fx_comments ORDER BY posted DESC, sid DESC';
    }

    public function query2(): string
    {
        return 'SELECT name FROM fx_awards ORDER BY year, award';
    }

    public function query3(): string
    {
        return 'SELECT year FROM fx_awards ORDER BY year, note';
    }

    public function query4(): string
    {
        return 'SELECT award FROM fx_awards WHERE name = ? OR year = ? ORDER BY year, award';
    }

    public function query5(): string
    {
        return 'SELECT award FROM fx_awards WHERE name <= ? ORDER BY year, award';
    }

    public function query6(): string
    {
        return 'SELECT award FROM fx_awards WHERE NOT name = ? ORDER BY year, award';
    }

    public function query7(): string
    {
        return 'SELECT a.year FROM fx_awards a ORDER BY a.year, a.award, b.name';
    }

    public function query8(): string
    {
        return 'SELECT table_id AS award, name FROM fx_awards WHERE year = ? ORDER BY name, award';
    }

    public function query9(): string
    {
        return 'SELECT award FROM fx_awards WHERE name = ? OR year = ? AND award = ? ORDER BY year, name';
    }

    public function query10(): string
    {
        return 'SELECT award FROM fx_awards WHERE NOT (name = ? AND award = ?) ORDER BY year, name';
    }
}
