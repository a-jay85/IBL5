<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * NotificationSettings/index.php calls loginbox() (→ die()) for unauthenticated
 * users — that path is covered by E2E (same pattern as ApiKeys/FreeAgency).
 *
 * The module reads $authService->getUserId(); tests override the auth stub
 * locally to supply an authenticated identity.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class NotificationSettingsEntryPointTest extends ModuleEntryPointTestCase
{
    private function authenticateWithUserId(string $username, int $userId): void
    {
        $this->authenticateAs($username);

        $authStub = self::createStub(\Auth\Contracts\AuthServiceInterface::class);
        $authStub->method('isAuthenticated')->willReturn(true);
        $authStub->method('isAdmin')->willReturn(false);
        $authStub->method('getCookieArray')->willReturn([$username, $username, '']);
        $authStub->method('getUserId')->willReturn($userId);
        $authStub->method('getUsername')->willReturn($username);
        $GLOBALS['authService'] = $authStub;
    }

    public function testMainOpRendersPreferencesFormForAuthenticatedUser(): void
    {
        $this->authenticateWithUserId('testgm', 1);
        $this->mockDb->setMockData([]); // no stored row → defaults

        $output = $this->runModule('NotificationSettings', [], [], array_merge($this->dbGlobals(), [
            'user' => $GLOBALS['user'],
        ]));

        $this->assertStringContainsString('Notification Preferences', $output);
        $this->assertStringContainsString('name="notify_trade_offers"', $output);
        // CSRF hidden input uses the underscore-prefixed field name.
        $this->assertStringContainsString('name="_csrf_token"', $output);
    }

    public function testSaveOpRequiresPostMethod(): void
    {
        $this->authenticateWithUserId('testgm', 1);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->mockDb->setMockData([]);

        $output = $this->runModule('NotificationSettings', ['op' => 'save'], [], array_merge($this->dbGlobals(), [
            'user' => $GLOBALS['user'],
        ]));

        // GET to save redirects to main (header('Location: ...'); return;)
        $this->assertEmpty($output);
    }

    /** Matrix #7 — POST save without a CSRF token shows the error banner and writes nothing. */
    public function testSaveWithoutCsrfTokenRendersErrorAndDoesNotWrite(): void
    {
        $this->authenticateWithUserId('testgm', 1);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->mockDb->setMockData([]);

        $output = $this->runModule(
            'NotificationSettings',
            ['op' => 'save'],
            ['notify_trade_offers' => '1'], // no _csrf_token
            array_merge($this->dbGlobals(), ['user' => $GLOBALS['user']]),
        );

        $this->assertStringContainsString('Invalid or expired form submission', $output);

        $executed = implode("\n", $this->getExecutedQueries());
        $this->assertStringNotContainsStringIgnoringCase('INSERT INTO gm_notification_prefs', $executed);
    }

    /**
     * Matrix #8 — IDOR: authenticated as user 1, POST a forged user_id=2 with a
     * valid CSRF token. The write must target the SESSION id (1), never the POST
     * param, and the forged 'user_id' key must not be treated as a toggle.
     */
    public function testForgedUserIdInPostIsIgnoredWriteTargetsSessionId(): void
    {
        $this->authenticateWithUserId('testgm', 1);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->mockDb->setMockData([]);

        // Start the session and mint a valid token for the save form.
        $token = \Security\CsrfGuard::generateRawToken('notification_prefs_save');

        $output = $this->runModule(
            'NotificationSettings',
            ['op' => 'save'],
            [
                '_csrf_token' => $token,
                'user_id' => '2',            // forged — must be ignored
                'notify_trade_offers' => '1',
            ],
            array_merge($this->dbGlobals(), ['user' => $GLOBALS['user']]),
        );

        // Valid save redirects (header + return) — no body.
        $this->assertEmpty($output);

        $executed = implode("\n", $this->getExecutedQueries());
        $this->assertStringContainsString('INSERT INTO gm_notification_prefs', $executed);
        // The first bound value (user_id) is the session id 1, not the forged 2.
        $this->assertStringContainsString('VALUES (1,', $executed);
        $this->assertStringNotContainsString('VALUES (2,', $executed);
    }
}
