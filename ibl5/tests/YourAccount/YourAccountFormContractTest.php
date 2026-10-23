<?php

declare(strict_types=1);

namespace Tests\YourAccount;

use PHPUnit\Framework\TestCase;

/**
 * Class-blind form contract over the YourAccount golden snapshots.
 *
 * Extracts every form's action, method, hx-boost and ordered `name:type`
 * control list, every non-CSRF hidden value and every link href. No class
 * or role attribute enters the contract, so a pure class swap leaves it
 * unchanged.
 *
 * Reading the fixture is sound because YourAccountViewCharacterizationTest
 * pins fixture == live render. A field rename in a view fails that test
 * until the fixture is regenerated, and the regenerated fixture then fails
 * this one.
 */
final class YourAccountFormContractTest extends TestCase
{
    private const FIXTURE_PATH = __DIR__ . '/Fixtures/your-account-view-snapshots.json';

    private const EXPECTED = [
        'renderLoginPage_null' => [
            'forms' => [
                [
                    'action' => 'modules.php?name=YourAccount',
                    'method' => 'post',
                    'hxBoost' => 'false',
                    'controls' => [
                        'username:text',
                        'user_password:password',
                        'remember_me:checkbox',
                        '_csrf_token:hidden',
                        'op:hidden',
                    ],
                ],
            ],
            'hidden' => [
                'op=login',
            ],
            'links' => [
                'modules.php?name=YourAccount&op=pass_lost',
                'modules.php?name=YourAccount&op=new_user',
            ],
        ],
        'renderLoginPage_error' => [
            'forms' => [
                [
                    'action' => 'modules.php?name=YourAccount',
                    'method' => 'post',
                    'hxBoost' => 'false',
                    'controls' => [
                        'username:text',
                        'user_password:password',
                        'remember_me:checkbox',
                        '_csrf_token:hidden',
                        'op:hidden',
                    ],
                ],
            ],
            'hidden' => [
                'op=login',
            ],
            'links' => [
                'modules.php?name=YourAccount&op=pass_lost',
                'modules.php?name=YourAccount&op=new_user',
            ],
        ],
        'renderRegisterPage' => [
            'forms' => [
                [
                    'action' => 'modules.php?name=YourAccount',
                    'method' => 'post',
                    'hxBoost' => 'false',
                    'controls' => [
                        'username:text',
                        'user_email:email',
                        'user_password:password',
                        'user_password2:password',
                        '_csrf_token:hidden',
                        'op:hidden',
                    ],
                ],
            ],
            'hidden' => [
                'op=finish',
            ],
            'links' => [
                'modules.php?name=YourAccount',
            ],
        ],
        'renderRegistrationCompletePage' => [
            'forms' => [],
            'hidden' => [],
            'links' => [
                'modules.php?name=YourAccount',
            ],
        ],
        'renderRegistrationErrorPage' => [
            'forms' => [],
            'hidden' => [],
            'links' => [
                'modules.php?name=YourAccount&op=new_user',
            ],
        ],
        'renderForgotPasswordPage' => [
            'forms' => [
                [
                    'action' => 'modules.php?name=YourAccount',
                    'method' => 'post',
                    'hxBoost' => 'false',
                    'controls' => [
                        'user_email:email',
                        '_csrf_token:hidden',
                        'op:hidden',
                    ],
                ],
            ],
            'hidden' => [
                'op=mailpasswd',
            ],
            'links' => [
                'modules.php?name=YourAccount',
                'modules.php?name=YourAccount&op=new_user',
            ],
        ],
        'renderResetEmailSentPage' => [
            'forms' => [],
            'hidden' => [],
            'links' => [
                'modules.php?name=YourAccount',
            ],
        ],
        'renderResetPasswordPage' => [
            'forms' => [
                [
                    'action' => 'modules.php?name=YourAccount',
                    'method' => 'post',
                    'hxBoost' => 'false',
                    'controls' => [
                        'new_password:password',
                        'new_password2:password',
                        'selector:hidden',
                        'token:hidden',
                        '_csrf_token:hidden',
                        'op:hidden',
                    ],
                ],
            ],
            'hidden' => [
                'selector=abc123',
                'token=token456',
                'op=do_reset_password',
            ],
            'links' => [
                'modules.php?name=YourAccount',
            ],
        ],
        'renderPasswordResetSuccessPage' => [
            'forms' => [],
            'hidden' => [],
            'links' => [
                'modules.php?name=YourAccount',
            ],
        ],
        'renderPasswordResetErrorPage' => [
            'forms' => [],
            'hidden' => [],
            'links' => [
                'modules.php?name=YourAccount&op=pass_lost',
            ],
        ],
        'renderActivationSuccessPage' => [
            'forms' => [],
            'hidden' => [],
            'links' => [
                'modules.php?name=YourAccount',
            ],
        ],
        'renderActivationErrorPage_mismatch' => [
            'forms' => [],
            'hidden' => [],
            'links' => [
                'modules.php?name=YourAccount&op=new_user',
            ],
        ],
        'renderActivationErrorPage_expired' => [
            'forms' => [],
            'hidden' => [],
            'links' => [
                'modules.php?name=YourAccount&op=new_user',
            ],
        ],
    ];

    public function testFormContractMatchesPinnedMarkup(): void
    {
        self::assertSame(self::EXPECTED, self::extractAll(self::loadFixtures()));
    }

    public function testEveryPostFormCarriesCsrfField(): void
    {
        $postForms = 0;
        foreach (self::loadFixtures() as $key => $html) {
            foreach (self::extract($html)['forms'] as $index => $form) {
                if (strtolower($form['method']) !== 'post') {
                    continue;
                }
                $postForms++;
                $names = array_map(
                    static fn (string $control): string => explode(':', $control, 2)[0],
                    $form['controls']
                );
                self::assertContains(
                    '_csrf_token',
                    $names,
                    "POST form #{$index} in fixture key {$key} has no _csrf_token control"
                );
            }
        }

        self::assertGreaterThan(0, $postForms, 'Expected at least one POST form across the snapshots');
    }

    /**
     * @return array<string, string>
     */
    private static function loadFixtures(): array
    {
        $decoded = json_decode((string) file_get_contents(self::FIXTURE_PATH), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded, 'Fixture file must decode to an array');
        /** @var array<string, string> $decoded */
        return $decoded;
    }

    /**
     * @param array<string, string> $fixtures
     * @return array<string, array{forms: list<array{action: string, method: string, hxBoost: string|null, controls: list<string>}>, hidden: list<string>, links: list<string>}>
     */
    private static function extractAll(array $fixtures): array
    {
        $contract = [];
        foreach ($fixtures as $key => $html) {
            $contract[$key] = self::extract($html);
        }
        return $contract;
    }

    /**
     * @return array{forms: list<array{action: string, method: string, hxBoost: string|null, controls: list<string>}>, hidden: list<string>, links: list<string>}
     */
    private static function extract(string $html): array
    {
        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($doc);

        $forms = [];
        foreach ($xpath->query('//form') ?: [] as $form) {
            if (!$form instanceof \DOMElement) {
                continue;
            }
            $controls = [];
            foreach ($xpath->query('.//input|.//select|.//textarea|.//button', $form) ?: [] as $control) {
                if (!$control instanceof \DOMElement || !$control->hasAttribute('name')) {
                    continue;
                }
                $controls[] = $control->getAttribute('name') . ':' . $control->getAttribute('type');
            }
            $forms[] = [
                'action' => $form->getAttribute('action'),
                'method' => $form->getAttribute('method'),
                'hxBoost' => $form->hasAttribute('hx-boost') ? $form->getAttribute('hx-boost') : null,
                'controls' => $controls,
            ];
        }

        $hidden = [];
        foreach ($xpath->query('//input[@type="hidden"]') ?: [] as $input) {
            if (!$input instanceof \DOMElement || $input->getAttribute('name') === '_csrf_token') {
                continue;
            }
            $hidden[] = $input->getAttribute('name') . '=' . $input->getAttribute('value');
        }

        $links = [];
        foreach ($xpath->query('//a') ?: [] as $link) {
            if ($link instanceof \DOMElement) {
                $links[] = $link->getAttribute('href');
            }
        }

        return ['forms' => $forms, 'hidden' => $hidden, 'links' => $links];
    }
}
