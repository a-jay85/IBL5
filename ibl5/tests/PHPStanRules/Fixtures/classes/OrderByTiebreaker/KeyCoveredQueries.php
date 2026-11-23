<?php

declare(strict_types=1);

namespace Tests\PHPStanRules\Fixtures\OrderByTiebreaker;

final class KeyCoveredQueries
{
    public function query1(): string
    {
        return 'SELECT year FROM fx_awards ORDER BY year DESC, table_id ASC';
    }

    public function query2(): string
    {
        return 'SELECT a.year FROM `fx_awards` a ORDER BY a.year, a.table_id';
    }

    public function query3(): string
    {
        return 'SELECT `a`.year FROM fx_awards `a` ORDER BY `a`.`year`, `a`.`table_id`';
    }

    public function query4(): string
    {
        return 'SELECT a.year FROM fx_awards AS a ORDER BY a.year, a.table_id';
    }

    public function query5(): string
    {
        return 'SELECT name FROM fx_awards ORDER BY year, award, name';
    }

    public function query6(): string
    {
        return 'SELECT award FROM fx_awards WHERE name = ? AND year = ? ORDER BY award';
    }

    public function query7(): string
    {
        return 'SELECT award FROM fx_awards WHERE table_id = ? ORDER BY award';
    }

    public function query8(): string
    {
        return 'SELECT cid FROM fx_comments WHERE (posted > ? OR sid = ?) ORDER BY posted DESC, cid DESC';
    }

    public function query9(): string
    {
        return 'SELECT amount FROM fx_offers ORDER BY amount DESC, primary_key ASC';
    }
}
