<?php

declare(strict_types=1);

namespace GoogleSheets;

use Security\SecretBoxKeyUnavailableException;

/**
 * GoogleOAuthFlowHandler - the start and callback halves of the Google sign-in.
 *
 * The controller supplies the session user id; this class never reads identity
 * from the request. Error text shown to the GM names a reason only, never a
 * Google response body.
 */
class GoogleOAuthFlowHandler
{
    public const TEXT_CANCELLED = 'Google sign-in was cancelled.';
    public const TEXT_BAD_STATE = 'Sign-in link expired or invalid. Please try again.';
    public const TEXT_SUCCESS = 'Google Sheet created. Your player export will refresh after every sim.';

    public function __construct(
        private readonly GoogleOAuthClient $oauth,
        private readonly GoogleSheetExportService $export,
    ) {
    }

    public function start(int $userId): string
    {
        return $this->oauth->buildAuthorizationUrl(GoogleOAuthState::issue($userId));
    }

    /**
     * @return array{type: 'success'|'error', text: string}
     */
    public function callback(int $userId, mixed $state, mixed $code, mixed $error): array
    {
        if (is_string($error) && $error !== '') {
            GoogleOAuthState::consume($state, $userId);

            return ['type' => 'error', 'text' => self::TEXT_CANCELLED];
        }
        if (!GoogleOAuthState::consume($state, $userId)) {
            return ['type' => 'error', 'text' => self::TEXT_BAD_STATE];
        }
        if (!is_string($code) || $code === '') {
            return ['type' => 'error', 'text' => self::TEXT_BAD_STATE];
        }

        try {
            $this->export->connect($userId, $code);
        } catch (GoogleApiException $e) {
            return ['type' => 'error', 'text' => self::connectError($e->reason)];
        } catch (GoogleHttpException) {
            return ['type' => 'error', 'text' => self::connectError('network')];
        } catch (SecretBoxKeyUnavailableException) {
            return ['type' => 'error', 'text' => self::connectError('server key unavailable')];
        }

        return ['type' => 'success', 'text' => self::TEXT_SUCCESS];
    }

    private static function connectError(string $reason): string
    {
        if (preg_match('/^[A-Za-z0-9 _.:-]{1,64}$/', $reason) !== 1) {
            $reason = 'unknown';
        }

        return 'Could not connect Google Sheets (' . $reason . ').';
    }
}
