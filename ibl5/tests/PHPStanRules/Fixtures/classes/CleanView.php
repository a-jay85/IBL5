<?php

declare(strict_types=1);

class CleanView
{
    public function render(array $data): string
    {
        return '<div>' . htmlspecialchars($data['name'] ?? '', ENT_QUOTES) . '</div>';
    }
}
