<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Utilities\HtmxHelper;

class HtmxHelperTest extends TestCase
{
    private mixed $originalHxRequest;
    private bool $hadHxRequest;
    private mixed $originalHxBoosted;
    private bool $hadHxBoosted;

    protected function setUp(): void
    {
        $this->hadHxRequest = array_key_exists('HTTP_HX_REQUEST', $_SERVER);
        $this->originalHxRequest = $_SERVER['HTTP_HX_REQUEST'] ?? null;

        $this->hadHxBoosted = array_key_exists('HTTP_HX_BOOSTED', $_SERVER);
        $this->originalHxBoosted = $_SERVER['HTTP_HX_BOOSTED'] ?? null;

        unset($_SERVER['HTTP_HX_REQUEST'], $_SERVER['HTTP_HX_BOOSTED']);
    }

    protected function tearDown(): void
    {
        if ($this->hadHxRequest) {
            $_SERVER['HTTP_HX_REQUEST'] = $this->originalHxRequest;
        } else {
            unset($_SERVER['HTTP_HX_REQUEST']);
        }

        if ($this->hadHxBoosted) {
            $_SERVER['HTTP_HX_BOOSTED'] = $this->originalHxBoosted;
        } else {
            unset($_SERVER['HTTP_HX_BOOSTED']);
        }
    }

    #[Test]
    public function isHtmxRequestReturnsFalseWhenHeaderMissing(): void
    {
        self::assertFalse(HtmxHelper::isHtmxRequest());
    }

    #[Test]
    public function isHtmxRequestReturnsTrueWhenHeaderIsTrue(): void
    {
        $_SERVER['HTTP_HX_REQUEST'] = 'true';
        self::assertTrue(HtmxHelper::isHtmxRequest());
    }

    #[Test]
    public function isHtmxRequestReturnsFalseWhenHeaderIsNotTrue(): void
    {
        $_SERVER['HTTP_HX_REQUEST'] = 'false';
        self::assertFalse(HtmxHelper::isHtmxRequest());
    }

    #[Test]
    public function isBoostedRequestReturnsFalseWhenHeaderMissing(): void
    {
        self::assertFalse(HtmxHelper::isBoostedRequest());
    }

    #[Test]
    public function isBoostedRequestReturnsTrueWhenHeaderIsTrue(): void
    {
        $_SERVER['HTTP_HX_BOOSTED'] = 'true';
        self::assertTrue(HtmxHelper::isBoostedRequest());
    }

    #[Test]
    public function isBoostedRequestReturnsFalseWhenHeaderIsNotTrue(): void
    {
        $_SERVER['HTTP_HX_BOOSTED'] = 'false';
        self::assertFalse(HtmxHelper::isBoostedRequest());
    }
}
