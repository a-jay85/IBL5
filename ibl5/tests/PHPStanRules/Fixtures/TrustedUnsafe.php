<?php

declare(strict_types=1);

$userTeam = 'raw-html-from-request';

HtmlSanitizer::trusted($userTeam);
HtmlSanitizer::trusted((string) $userTeam);
Security\HtmlSanitizer::trusted($userTeam);
