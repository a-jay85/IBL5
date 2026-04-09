<?php

declare(strict_types=1);

// Preload fixture classes so PHPStan's ReflectionProvider can find them during rule
// tests. RuleTestCase analyses individual fixture files in isolation, but the rules
// themselves (and level-max follow-ups) inspect class reflection, which requires the
// classes to be discoverable.
//
// Only fixtures that declare classes are listed here. Fixtures that only contain
// top-level statements do not need to be preloaded — they are parsed file-by-file
// without ever being executed.

// BanBeginTransactionInRepositoryRule fixtures
require_once __DIR__ . '/SubclassCallsBeginTransaction.php';
require_once __DIR__ . '/NonRepositoryCallsBeginTransaction.php';

// PageLayoutHeaderBeforeCookieRule fixtures
require_once __DIR__ . '/classes/CookieBeforeHeader.php';
require_once __DIR__ . '/classes/CookieAfterHeader.php';
require_once __DIR__ . '/classes/CookieWithoutHeader.php';
require_once __DIR__ . '/classes/CookieWriteBeforeHeader.php';
