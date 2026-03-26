<?php

declare(strict_types=1);

namespace YourAccount;

use Auth\Contracts\AuthServiceInterface;
use League\League;
use Mail\Contracts\MailServiceInterface;
use Services\CommonMysqliRepository;
use YourAccount\Contracts\YourAccountRepositoryInterface;
use YourAccount\Contracts\YourAccountServiceInterface;

/**
 * @see YourAccountServiceInterface
 */
class YourAccountService implements YourAccountServiceInterface
{
    private const MAX_USERNAME_LENGTH = 25;
    private const AUTO_PASSWORD_LENGTH = 10;

    private YourAccountRepositoryInterface $repository;
    private AuthServiceInterface $authService;
    private CommonMysqliRepository $commonRepository;
    private MailServiceInterface $mailService;
    private string $siteUrl;
    private string $siteName;
    private string $adminEmail;
    private int $minPasswordLength;

    public function __construct(
        YourAccountRepositoryInterface $repository,
        AuthServiceInterface $authService,
        CommonMysqliRepository $commonRepository,
        MailServiceInterface $mailService,
        string $siteUrl,
        string $siteName,
        string $adminEmail,
        int $minPasswordLength = 5,
    ) {
        $this->repository = $repository;
        $this->authService = $authService;
        $this->commonRepository = $commonRepository;
        $this->mailService = $mailService;
        $this->siteUrl = $siteUrl;
        $this->siteName = $siteName;
        $this->adminEmail = $adminEmail;
        $this->minPasswordLength = $minPasswordLength;
    }

    /**
     * @see YourAccountServiceInterface::attemptLogin()
     */
    public function attemptLogin(string $username, string $password, bool $rememberMe, string $clientIp): array
    {
        if ($this->authService->attempt($username, $password, $rememberMe)) {
            $this->repository->updateLastLoginIp($username, $clientIp);

            return ['success' => true, 'error' => null];
        }

        return ['success' => false, 'error' => $this->authService->getLastError()];
    }

    /**
     * @see YourAccountServiceInterface::registerUser()
     */
    public function registerUser(string $username, string $email, string $password1, string $password2): array
    {
        // Validate username
        if ($username === '' || strlen($username) > self::MAX_USERNAME_LENGTH || preg_match('/[^a-zA-Z0-9_-]/', $username) === 1) {
            return ['success' => false, 'error' => 'Invalid username. Only letters, numbers, underscores and hyphens are allowed.'];
        }

        // Validate passwords
        $password = $this->resolvePassword($password1, $password2);
        if ($password === null) {
            return ['success' => false, 'error' => $this->getPasswordError($password1, $password2)];
        }

        try {
            $this->authService->register(
                $email,
                $password,
                $username,
                function (string $selector, string $token) use ($email, $username): void {
                    $this->sendVerificationEmail($email, $username, $selector, $token);
                },
            );

            return ['success' => true, 'error' => null];
        } catch (\RuntimeException) {
            $error = $this->authService->getLastError() ?? 'An error occurred during registration.';
            return ['success' => false, 'error' => $error];
        }
    }

    /**
     * @see YourAccountServiceInterface::confirmEmail()
     */
    public function confirmEmail(string $selector, string $token): array
    {
        if ($selector === '' || $token === '') {
            return ['success' => false, 'username' => null, 'error' => 'mismatch'];
        }

        try {
            $result = $this->authService->confirmEmail($selector, $token);
            $username = is_string($result['username'] ?? null) ? $result['username'] : null;

            return ['success' => true, 'username' => $username, 'error' => null];
        } catch (\RuntimeException) {
            $error = $this->authService->getLastError() ?? 'expired';
            return ['success' => false, 'username' => null, 'error' => $error];
        }
    }

    /**
     * @see YourAccountServiceInterface::requestPasswordReset()
     */
    public function requestPasswordReset(string $email): array
    {
        if ($email === '') {
            return ['success' => false, 'error' => 'Please enter your email address.'];
        }

        $this->authService->forgotPassword(
            $email,
            function (string $selector, string $token) use ($email): void {
                $this->sendPasswordResetEmail($email, $selector, $token);
            },
        );

        // Check if AuthService set an error (e.g., rate limiting)
        $error = $this->authService->getLastError();
        if ($error !== null) {
            return ['success' => false, 'error' => $error];
        }

        return ['success' => true, 'error' => null];
    }

    /**
     * @see YourAccountServiceInterface::resetPassword()
     */
    public function resetPassword(string $selector, string $token, string $newPassword, string $confirmPassword): array
    {
        if ($newPassword !== $confirmPassword) {
            return ['success' => false, 'error' => 'The passwords you entered do not match. Please go back and try again.'];
        }

        try {
            $this->authService->resetPassword($selector, $token, $newPassword);
            return ['success' => true, 'error' => null];
        } catch (\RuntimeException) {
            $error = $this->authService->getLastError() ?? 'An error occurred while resetting your password.';
            return ['success' => false, 'error' => $error];
        }
    }

    /**
     * @see YourAccountServiceInterface::logout()
     */
    public function logout(): void
    {
        $this->authService->logout();
    }

    /**
     * @see YourAccountServiceInterface::getTeamRedirectUrl()
     */
    public function getTeamRedirectUrl(string $username): ?string
    {
        $teamName = $this->commonRepository->getTeamnameFromUsername($username);
        if ($teamName === null || $teamName === '' || $teamName === League::FREE_AGENTS_TEAM_NAME) {
            return null;
        }

        $tid = $this->commonRepository->getTidFromTeamname($teamName);
        if ($tid === null || $tid <= 0) {
            return null;
        }

        return 'modules.php?name=Team&op=team&teamID=' . $tid;
    }

    /**
     * Resolve the password from the two inputs.
     *
     * Returns the validated password, or null if validation fails.
     */
    private function resolvePassword(string $password1, string $password2): ?string
    {
        if ($password1 === '' && $password2 === '') {
            return substr(bin2hex(random_bytes(5)), 0, self::AUTO_PASSWORD_LENGTH);
        }

        if ($password1 !== $password2) {
            return null;
        }

        if (strlen($password1) < $this->minPasswordLength) {
            return null;
        }

        return $password1;
    }

    /**
     * Get the specific password validation error message.
     */
    private function getPasswordError(string $password1, string $password2): string
    {
        if ($password1 !== $password2) {
            return 'The passwords you entered do not match.';
        }

        return 'Your password must be at least ' . $this->minPasswordLength . ' characters long.';
    }

    private function sendVerificationEmail(string $email, string $username, string $selector, string $token): void
    {
        $baseUrl = str_replace('http://', 'https://', rtrim($this->siteUrl, '/'));
        $link = $baseUrl . '/ibl5/modules.php?name=YourAccount&op=confirm_email&selector='
            . urlencode($selector) . '&token=' . urlencode($token);

        $message = "Welcome to {$this->siteName}!\n\n"
            . "You or someone else has used your email account ({$email}) to register an account at {$this->siteName}.\n\n"
            . "To finish the registration process you should visit the following link in the next 24 hours "
            . "to activate your user account, otherwise the information will be automatically deleted by the system "
            . "and you should apply again:\n\n"
            . "{$link}\n\n"
            . "Following is the member information:\n\n"
            . "Nickname: {$username}";

        $this->mailService->send(
            $email,
            'New User Account Activation',
            $message,
            $this->adminEmail,
        );
    }

    private function sendPasswordResetEmail(string $email, string $selector, string $token): void
    {
        $baseUrl = str_replace('http://', 'https://', rtrim($this->siteUrl, '/'));
        $link = $baseUrl . '/ibl5/modules.php?name=YourAccount&op=reset_password&selector='
            . urlencode($selector) . '&token=' . urlencode($token);

        $message = "A password reset was requested for your account at {$this->siteName}.\n\n"
            . "Click the link below to reset your password:\n\n{$link}\n\n"
            . "This link will expire in 6 hours.\n\n"
            . "If you did not request this, you can safely ignore this email.";

        $this->mailService->send(
            $email,
            "Password Reset - {$this->siteName}",
            $message,
            $this->adminEmail,
        );
    }
}
