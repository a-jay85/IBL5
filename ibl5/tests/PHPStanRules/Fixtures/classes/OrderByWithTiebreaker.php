<?php

declare(strict_types=1);

namespace Tests\PHPStanRules\Fixtures\Classes;

final class OrderByWithTiebreaker
{
    public function getLeaders(): string
    {
        return "SELECT pid, value FROM ibl_leaders ORDER BY value DESC, pid ASC";
    }

    public function getDraftYear(): string
    {
        return "SELECT draftyear FROM ibl_plr ORDER BY draftyear DESC LIMIT 1";
    }

    public function getGrouped(): string
    {
        return "SELECT teamid, COUNT(*) c FROM ibl_box GROUP BY teamid ORDER BY c DESC";
    }

    public function getQualified(): string
    {
        return "SELECT bs.`id`, bs.pts FROM ibl_box_scores bs ORDER BY bs.pts DESC, bs.`id` ASC";
    }

    public function getNoOrderBy(): string
    {
        return "SELECT pid, name FROM ibl_plr WHERE tid = 1";
    }
}
