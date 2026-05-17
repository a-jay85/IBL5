<?php

declare(strict_types=1);

class SomeService {
    public function doWork(): void {
        global $leagueContext;
        echo $leagueContext;
    }
}
