<?php

declare(strict_types=1);

$verifiedSafeHtml = '<b>ok</b>';

HtmlSanitizer::trusted($verifiedSafeHtml); // @phpstan-ignore ibl.trustedVariable
