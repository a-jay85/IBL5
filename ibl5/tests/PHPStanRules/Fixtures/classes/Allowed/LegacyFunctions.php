<?php

declare(strict_types=1);

function someLegacyFunction(): void {
    global $authService;
    echo $authService;
}
