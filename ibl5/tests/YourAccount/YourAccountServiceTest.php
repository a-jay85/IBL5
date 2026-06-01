<?php

declare(strict_types=1);

namespace Tests\YourAccount;

use Auth\Contracts\AuthServiceInterface;
use Mail\Contracts\MailServiceInterface;
use PHPUnit\Framework\TestCase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Tests\Support\AuthLogAssertions;
use YourAccount\YourAccountService;

class YourAccountServiceTest extends TestCase
{
    use AuthLogAssertions;
    /** @var AuthServiceInterface&\PHPUnit\Framework\MockObject\Stub */
    private AuthServiceInterface $stubAuthService;

    /** @var TeamIdentityRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private TeamIdentityRepositoryInterface $stubCommonRepository;

    /** @var MailServiceInterface&\PHPUnit\Framework\MockObject\Stub */
    private MailServiceInterface $stubMailService;

    private YourAccountService $service;

    protected function setUp(): void
    {
        $this->setUpAuthLogCapture();

        $this->stubAuthService = self::createStub(AuthServiceInterface::class);
        $this->stubCommonRepository = self::createStub(TeamIdentityRepositoryInterface::class);
        $this->stubMailService = self::createStub(MailServiceInterface::class);

        $this->service = $this->buildService();
    }

    protected function tearDown(): void
    {
        $this->tearDownAuthLogCapture();
    }

    private function buildService(
        AuthServiceInterface|null $authService = null,
        MailServiceInterface|null $mailService = null,
    ): YourAccountService {
        return new YourAccountService(
            $authService ?? $this->stubAuthService,
            $this->stubCommonRepository,
            $mailService ?? $this->stubMailService,
            'https://iblhoops.net',
            'IBL Hoops',
            'admin@iblhoops.net',
            5,
        );
    }

    // ─── Login ───────────────────────────────────────────────────────

    public function testAttemptLoginSuccessReturnsTrue(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->once())
            ->method('attempt')
            ->with('testuser', 'pass123', false)
            ->willReturn(true);

        $this->service = $this->buildService(authService: $mockAuth);
        $result = $this->service->attemptLogin('testuser', 'pass123', false, '10.0.0.1');

        $this->assertTrue($result['success']);
        $this->assertNull($result['error']);
    }

    public function testAttemptLoginPassesRememberMeFlag(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->once())
            ->method('attempt')
            ->with('testuser', 'pass123', true)
            ->willReturn(true);

        $this->service = $this->buildService(authService: $mockAuth);
        $this->service->attemptLogin('testuser', 'pass123', true, '10.0.0.1');
    }

    public function testAttemptLoginFailureReturnsError(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->once())
            ->method('attempt')
            ->willReturn(false);

        $mockAuth->method('getLastError')
            ->willReturn('Please verify your email address.');

        $this->service = $this->buildService(authService: $mockAuth);
        $result = $this->service->attemptLogin('testuser', 'wrongpass', false, '10.0.0.1');

        $this->assertFalse($result['success']);
        $this->assertSame('Please verify your email address.', $result['error']);
    }

    public function testAttemptLoginFailureReturnsNullErrorWhenGeneric(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->once())
            ->method('attempt')
            ->willReturn(false);

        $mockAuth->method('getLastError')
            ->willReturn(null);

        $this->service = $this->buildService(authService: $mockAuth);
        $result = $this->service->attemptLogin('testuser', 'wrongpass', false, '10.0.0.1');

        $this->assertFalse($result['success']);
        $this->assertNull($result['error']);
    }

    public function testAttemptLoginFailureReturnsFalse(): void
    {
        $this->stubAuthService->method('attempt')->willReturn(false);

        $this->service = $this->buildService();
        $result = $this->service->attemptLogin('testuser', 'wrongpass', false, '10.0.0.1');

        $this->assertFalse($result['success']);
    }

    // ─── Registration ────────────────────────────────────────────────

    public function testRegisterUserSuccessSendsEmail(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->once())
            ->method('register')
            ->willReturnCallback(function (string $email, string $password, string $username, ?callable $callback): int {
                $this->assertSame('user@test.com', $email);
                $this->assertSame('newuser', $username);
                if ($callback !== null) {
                    $callback('sel123', 'tok456');
                }
                return 1;
            });

        $mockMail = $this->createMock(MailServiceInterface::class);
        $mockMail->expects($this->once())
            ->method('send')
            ->with(
                'user@test.com',
                'New User Account Activation',
                self::stringContains('newuser'),
                'admin@iblhoops.net',
            );

        $this->service = $this->buildService(authService: $mockAuth, mailService: $mockMail);
        $result = $this->service->registerUser('newuser', 'user@test.com', 'password1', 'password1');

        $this->assertTrue($result['success']);
        $this->assertNull($result['error']);
    }

    public function testRegisterUserAutoGeneratesPasswordWhenBothBlank(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->once())
            ->method('register')
            ->willReturnCallback(function (string $email, string $password, string $username, ?callable $callback): int {
                $this->assertSame(10, strlen($password));
                return 1;
            });

        $this->service = $this->buildService(authService: $mockAuth);
        $result = $this->service->registerUser('newuser', 'user@test.com', '', '');

        $this->assertTrue($result['success']);
    }

    public function testRegisterUserPasswordMismatchReturnsError(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->never())->method('register');

        $this->service = $this->buildService(authService: $mockAuth);
        $result = $this->service->registerUser('newuser', 'user@test.com', 'pass1', 'pass2');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('do not match', (string) $result['error']);
    }

    public function testRegisterUserPasswordTooShortReturnsError(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->never())->method('register');

        $this->service = $this->buildService(authService: $mockAuth);
        $result = $this->service->registerUser('newuser', 'user@test.com', 'ab', 'ab');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('at least 5 characters', (string) $result['error']);
    }

    public function testRegisterUserEmptyUsernameReturnsError(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->never())->method('register');

        $this->service = $this->buildService(authService: $mockAuth);
        $result = $this->service->registerUser('', 'user@test.com', 'password1', 'password1');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid username', (string) $result['error']);
    }

    public function testRegisterUserInvalidCharsInUsernameReturnsError(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->never())->method('register');

        $this->service = $this->buildService(authService: $mockAuth);
        $result = $this->service->registerUser('user name!', 'user@test.com', 'password1', 'password1');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid username', (string) $result['error']);
    }

    public function testRegisterUserUsernameTooLongReturnsError(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->never())->method('register');

        $this->service = $this->buildService(authService: $mockAuth);
        $longName = str_repeat('a', 26);
        $result = $this->service->registerUser($longName, 'user@test.com', 'password1', 'password1');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid username', (string) $result['error']);
    }

    public function testRegisterUserAuthServiceExceptionReturnsError(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->once())
            ->method('register')
            ->willThrowException(new \RuntimeException('Duplicate email'));

        $mockAuth->method('getLastError')
            ->willReturn('Email already registered');

        $this->service = $this->buildService(authService: $mockAuth);
        $result = $this->service->registerUser('newuser', 'user@test.com', 'password1', 'password1');

        $this->assertFalse($result['success']);
        $this->assertSame('Email already registered', $result['error']);
    }

    public function testRegisterUserAuthServiceExceptionFallsBackToGenericError(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->once())
            ->method('register')
            ->willThrowException(new \RuntimeException('error'));

        $mockAuth->method('getLastError')->willReturn(null);

        $this->service = $this->buildService(authService: $mockAuth);
        $result = $this->service->registerUser('newuser', 'user@test.com', 'password1', 'password1');

        $this->assertFalse($result['success']);
        $this->assertSame('An error occurred during registration.', $result['error']);
    }

    public function testRegisterUserVerificationEmailContainsLink(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->once())
            ->method('register')
            ->willReturnCallback(function (string $email, string $password, string $username, ?callable $callback): int {
                if ($callback !== null) {
                    $callback('my-selector', 'my-token');
                }
                return 1;
            });

        $mockMail = $this->createMock(MailServiceInterface::class);
        $mockMail->expects($this->once())
            ->method('send')
            ->with(
                self::anything(),
                self::anything(),
                self::logicalAnd(
                    self::stringContains('confirm_email'),
                    self::stringContains('my-selector'),
                    self::stringContains('my-token'),
                    self::stringContains('https://iblhoops.net'),
                ),
                self::anything(),
            );

        $this->service = $this->buildService(authService: $mockAuth, mailService: $mockMail);
        $this->service->registerUser('newuser', 'user@test.com', 'password1', 'password1');
    }

    // ─── Email Confirmation ──────────────────────────────────────────

    public function testConfirmEmailSuccessReturnsUsername(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->once())
            ->method('confirmEmail')
            ->with('sel123', 'tok456')
            ->willReturn(['username' => 'confirmeduser']);

        $this->service = $this->buildService(authService: $mockAuth);
        $result = $this->service->confirmEmail('sel123', 'tok456');

        $this->assertTrue($result['success']);
        $this->assertSame('confirmeduser', $result['username']);
        $this->assertNull($result['error']);
    }

    public function testConfirmEmailEmptySelectorReturnsError(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->never())->method('confirmEmail');

        $this->service = $this->buildService(authService: $mockAuth);
        $result = $this->service->confirmEmail('', 'tok456');

        $this->assertFalse($result['success']);
        $this->assertSame('mismatch', $result['error']);
    }

    public function testConfirmEmailEmptyTokenReturnsError(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->never())->method('confirmEmail');

        $this->service = $this->buildService(authService: $mockAuth);
        $result = $this->service->confirmEmail('sel123', '');

        $this->assertFalse($result['success']);
        $this->assertSame('mismatch', $result['error']);
    }

    public function testConfirmEmailFailureReturnsError(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->once())
            ->method('confirmEmail')
            ->willThrowException(new \RuntimeException('expired'));

        $mockAuth->method('getLastError')
            ->willReturn('expired');

        $this->service = $this->buildService(authService: $mockAuth);
        $result = $this->service->confirmEmail('sel123', 'tok456');

        $this->assertFalse($result['success']);
        $this->assertNull($result['username']);
        $this->assertSame('expired', $result['error']);
    }

    // ─── Password Reset Request ──────────────────────────────────────

    public function testRequestPasswordResetSuccessSendsEmail(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->once())
            ->method('forgotPassword')
            ->willReturnCallback(function (string $email, callable $callback): void {
                $callback('sel-reset', 'tok-reset');
            });

        $mockAuth->method('getLastError')->willReturn(null);

        $mockMail = $this->createMock(MailServiceInterface::class);
        $mockMail->expects($this->once())
            ->method('send')
            ->with(
                'user@test.com',
                self::stringContains('Password Reset'),
                self::logicalAnd(
                    self::stringContains('sel-reset'),
                    self::stringContains('tok-reset'),
                    self::stringContains('reset_password'),
                ),
                'admin@iblhoops.net',
            );

        $this->service = $this->buildService(authService: $mockAuth, mailService: $mockMail);
        $result = $this->service->requestPasswordReset('user@test.com');

        $this->assertTrue($result['success']);
        $this->assertNull($result['error']);
    }

    public function testRequestPasswordResetEmptyEmailReturnsError(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->never())->method('forgotPassword');

        $this->service = $this->buildService(authService: $mockAuth);
        $result = $this->service->requestPasswordReset('');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('email address', (string) $result['error']);
    }

    public function testRequestPasswordResetRateLimitedReturnsError(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->once())
            ->method('forgotPassword');

        $mockAuth->method('getLastError')
            ->willReturn('Too many requests. Please try again later.');

        $this->service = $this->buildService(authService: $mockAuth);
        $result = $this->service->requestPasswordReset('user@test.com');

        $this->assertFalse($result['success']);
        $this->assertSame('Too many requests. Please try again later.', $result['error']);
    }

    // ─── Password Reset ──────────────────────────────────────────────

    public function testResetPasswordSuccessReturnsSuccess(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->once())
            ->method('resetPassword')
            ->with('sel123', 'tok456', 'newpass');

        $this->service = $this->buildService(authService: $mockAuth);
        $result = $this->service->resetPassword('sel123', 'tok456', 'newpass', 'newpass');

        $this->assertTrue($result['success']);
        $this->assertNull($result['error']);
    }

    public function testResetPasswordMismatchReturnsError(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->never())->method('resetPassword');

        $this->service = $this->buildService(authService: $mockAuth);
        $result = $this->service->resetPassword('sel123', 'tok456', 'pass1', 'pass2');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('do not match', (string) $result['error']);
    }

    public function testResetPasswordAuthServiceExceptionReturnsError(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->once())
            ->method('resetPassword')
            ->willThrowException(new \RuntimeException('Token expired'));

        $mockAuth->method('getLastError')
            ->willReturn('This reset link has expired.');

        $this->service = $this->buildService(authService: $mockAuth);
        $result = $this->service->resetPassword('sel123', 'tok456', 'newpass', 'newpass');

        $this->assertFalse($result['success']);
        $this->assertSame('This reset link has expired.', $result['error']);
    }

    public function testResetPasswordAuthServiceExceptionFallsBackToGenericError(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->once())
            ->method('resetPassword')
            ->willThrowException(new \RuntimeException('error'));

        $mockAuth->method('getLastError')->willReturn(null);

        $this->service = $this->buildService(authService: $mockAuth);
        $result = $this->service->resetPassword('sel123', 'tok456', 'newpass', 'newpass');

        $this->assertFalse($result['success']);
        $this->assertSame('An error occurred while resetting your password.', $result['error']);
    }

    // ─── Logout ──────────────────────────────────────────────────────

    public function testLogoutCallsAuthService(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->once())->method('logout');

        $this->service = $this->buildService(authService: $mockAuth);
        $this->service->logout();
    }

    // ─── Team Redirect URL ───────────────────────────────────────────

    public function testGetTeamRedirectUrlWithTeamReturnsUrl(): void
    {
        $this->stubCommonRepository->method('getTeamnameFromUsername')
            ->willReturn('Sting');

        $this->stubCommonRepository->method('getTidFromTeamname')
            ->willReturn(10);

        $result = $this->service->getTeamRedirectUrl('testuser');

        $this->assertSame('modules.php?name=Team&op=team&teamid=10', $result);
    }

    public function testGetTeamRedirectUrlFreeAgentReturnsNull(): void
    {
        $this->stubCommonRepository->method('getTeamnameFromUsername')
            ->willReturn('Free Agents');

        $result = $this->service->getTeamRedirectUrl('testuser');

        $this->assertNull($result);
    }

    public function testGetTeamRedirectUrlNoTeamReturnsNull(): void
    {
        $this->stubCommonRepository->method('getTeamnameFromUsername')
            ->willReturn(null);

        $result = $this->service->getTeamRedirectUrl('testuser');

        $this->assertNull($result);
    }

    public function testGetTeamRedirectUrlEmptyTeamNameReturnsNull(): void
    {
        $this->stubCommonRepository->method('getTeamnameFromUsername')
            ->willReturn('');

        $result = $this->service->getTeamRedirectUrl('testuser');

        $this->assertNull($result);
    }

    public function testGetTeamRedirectUrlNullTidReturnsNull(): void
    {
        $this->stubCommonRepository->method('getTeamnameFromUsername')
            ->willReturn('Sting');

        $this->stubCommonRepository->method('getTidFromTeamname')
            ->willReturn(null);

        $result = $this->service->getTeamRedirectUrl('testuser');

        $this->assertNull($result);
    }

    public function testGetTeamRedirectUrlZeroTidReturnsNull(): void
    {
        $this->stubCommonRepository->method('getTeamnameFromUsername')
            ->willReturn('Sting');

        $this->stubCommonRepository->method('getTidFromTeamname')
            ->willReturn(0);

        $result = $this->service->getTeamRedirectUrl('testuser');

        $this->assertNull($result);
    }

    // ─── Auth Logging ────────────────────────────────────────────────

    public function testLoginSuccessEmitsAuthLog(): void
    {
        $this->stubAuthService->method('attempt')->willReturn(true);

        $this->service->attemptLogin('testuser', 'pass123', false, '192.168.1.1');

        $this->assertAuthLogEmitted('login_success');
        $this->assertAuthLogContext('login_success', [
            'username' => 'testuser',
            'client_ip' => '192.168.1.1',
        ]);
    }

    public function testLoginFailedEmitsWarningAuthLog(): void
    {
        $this->stubAuthService->method('attempt')->willReturn(false);
        $this->stubAuthService->method('getLastError')->willReturn('Invalid credentials');

        $this->service->attemptLogin('baduser', 'wrongpass', false, '10.0.0.5');

        $this->assertAuthWarningLogEmitted('login_failed');
        $this->assertAuthWarningLogContext('login_failed', [
            'username' => 'baduser',
            'client_ip' => '10.0.0.5',
            'error' => 'Invalid credentials',
        ]);
    }

    public function testRegisterUserSuccessEmitsAuthLog(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->once())
            ->method('register')
            ->willReturnCallback(static function (string $email, string $password, string $username, ?callable $callback): int {
                return 1;
            });

        $this->service = $this->buildService(authService: $mockAuth);
        $this->service->registerUser('newgm', 'gm@test.com', 'password1', 'password1');

        $this->assertAuthLogEmitted('user_registered');
        $this->assertAuthLogContext('user_registered', [
            'username' => 'newgm',
        ]);
    }

    public function testPasswordResetRequestedEmitsAuthLog(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->once())
            ->method('forgotPassword')
            ->willReturnCallback(static function (string $email, callable $callback): void {
                $callback('sel', 'tok');
            });
        $mockAuth->method('getLastError')->willReturn(null);

        $this->service = $this->buildService(authService: $mockAuth);
        $this->service->requestPasswordReset('user@test.com');

        $this->assertAuthLogEmitted('password_reset_requested');
    }

    public function testPasswordResetRequestedDoesNotLogEmail(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->once())
            ->method('forgotPassword')
            ->willReturnCallback(static function (string $email, callable $callback): void {
                $callback('sel', 'tok');
            });
        $mockAuth->method('getLastError')->willReturn(null);

        $this->service = $this->buildService(authService: $mockAuth);
        $this->service->requestPasswordReset('secret@private.com');

        $this->assertAuthLogContextMissing('password_reset_requested', 'email');
    }

    public function testPasswordResetCompletedEmitsAuthLog(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->once())->method('resetPassword');

        $this->service = $this->buildService(authService: $mockAuth);
        $this->service->resetPassword('sel', 'tok', 'newpass', 'newpass');

        $this->assertAuthLogEmitted('password_reset_completed');
    }

    public function testResetPasswordDoesNotLogPasswordMaterial(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->once())->method('resetPassword');

        $this->service = $this->buildService(authService: $mockAuth);
        $this->service->resetPassword('sel', 'tok', 'secretpass', 'secretpass');

        $this->assertAuthLogContextMissing('password_reset_completed', 'password');
        $this->assertAuthLogContextMissing('password_reset_completed', 'newPassword');
        $this->assertAuthLogContextMissing('password_reset_completed', 'token');
        $this->assertAuthLogContextMissing('password_reset_completed', 'selector');
    }

    public function testLogoutEmitsAuthLog(): void
    {
        $mockAuth = $this->createMock(AuthServiceInterface::class);
        $mockAuth->expects($this->once())->method('logout');

        $this->service = $this->buildService(authService: $mockAuth);
        $this->service->logout();

        $this->assertAuthLogEmitted('logout');
    }
}
