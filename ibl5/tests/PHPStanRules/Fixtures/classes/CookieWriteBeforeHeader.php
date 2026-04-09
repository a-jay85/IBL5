<?php

declare(strict_types=1);

final class CookieWriteBeforeHeader
{
    public function handle(): void
    {
        global $cookie;
        $cookie['override'] = 'value';
        \PageLayout::header('Title');
    }
}
