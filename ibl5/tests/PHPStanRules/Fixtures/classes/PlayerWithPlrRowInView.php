<?php

declare(strict_types=1);

class PlayerWithPlrRowInView
{
    public function render(array $rows): string
    {
        foreach ($rows as $row) {
            $player = \Player\Player::withPlrRow(new \mysqli(), $row);
        }

        return '';
    }
}
