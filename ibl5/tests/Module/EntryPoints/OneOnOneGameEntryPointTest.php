<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;

/**
 * OneOnOneGame/index.php decodes the session cookie into $GLOBALS['cookie']
 * before reading the owner name. It has no is_user() gate, so an
 * unauthenticated visitor gets the page with an empty owner name.
 * Each test runs in a separate process.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class OneOnOneGameEntryPointTest extends ModuleEntryPointTestCase
{
    public function testOneOnOneGameRendersForAnonymousGet(): void
    {
        $this->mockDb->setMockData([]);

        $output = $this->runModule('OneOnOneGame', [], [], $this->dbGlobals());

        $this->assertNotSame('', $output);
        $this->assertStringContainsString('One-on-One Match', $output);
        $this->assertQueryExecuted('ibl_plr');
    }

    public function testAuthenticatedRunDecodesCookieIntoGlobal(): void
    {
        $this->authenticateAs('testgm');
        unset($GLOBALS['cookie']);
        $this->mockDb->setMockData([]);

        $this->runModule('OneOnOneGame', [], [], [
            'user' => $GLOBALS['user'],
        ]);

        $this->assertSame(['testgm', 'testgm', ''], $GLOBALS['cookie']);
    }

    public function testUnauthenticatedRunLeavesCookieUnset(): void
    {
        unset($GLOBALS['cookie']);
        $this->mockDb->setMockData([]);

        $this->runModule('OneOnOneGame', [], [], [
            'user' => $GLOBALS['user'] ?? '',
        ]);

        // cookiedecode() declares `global $cookie`, which leaves the key present
        // but null when no cookie array exists. An empty-array fallback would
        // plant an identity for a logged-out visitor.
        $this->assertNull($GLOBALS['cookie'] ?? null);
    }
}
