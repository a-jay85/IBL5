<?php

declare(strict_types=1);

namespace Tests\Api\Response;

use Api\Response\JsonResponder;
use PHPUnit\Framework\TestCase;
use Tests\Clock\FixedClock;

class JsonResponderTest extends TestCase
{
    private JsonResponder $responder;

    protected function setUp(): void
    {
        $this->responder = new JsonResponder();
    }

    public function testSuccessOutputsValidJsonWithStatusAndData(): void
    {
        ob_start();
        $this->responder->success(['players' => 10]);
        $output = (string) ob_get_clean();

        $decoded = json_decode($output, true);
        self::assertIsArray($decoded);
        self::assertSame('success', $decoded['status']);
        self::assertSame(['players' => 10], $decoded['data']);
    }

    public function testSuccessIncludesDefaultMetaFields(): void
    {
        ob_start();
        $this->responder->success([]);
        $output = (string) ob_get_clean();

        $decoded = json_decode($output, true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('meta', $decoded);
        self::assertArrayHasKey('timestamp', $decoded['meta']);
        self::assertSame('v1', $decoded['meta']['version']);
    }

    public function testSuccessMergesCustomMetaFields(): void
    {
        ob_start();
        $this->responder->success([], ['page' => 2, 'total' => 50]);
        $output = (string) ob_get_clean();

        $decoded = json_decode($output, true);
        self::assertIsArray($decoded);
        self::assertSame(2, $decoded['meta']['page']);
        self::assertSame(50, $decoded['meta']['total']);
        self::assertSame('v1', $decoded['meta']['version']);
    }

    public function testErrorOutputsJsonWithErrorEnvelope(): void
    {
        ob_start();
        $this->responder->error(404, 'not_found', 'Player not found');
        $output = (string) ob_get_clean();

        $decoded = json_decode($output, true);
        self::assertIsArray($decoded);
        self::assertSame('error', $decoded['status']);
        self::assertSame('not_found', $decoded['error']['code']);
        self::assertSame('Player not found', $decoded['error']['message']);
    }

    public function testErrorIncludesMetaTimestampAndVersion(): void
    {
        ob_start();
        $this->responder->error(400, 'bad_request', 'Invalid parameter');
        $output = (string) ob_get_clean();

        $decoded = json_decode($output, true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('meta', $decoded);
        self::assertArrayHasKey('timestamp', $decoded['meta']);
        self::assertSame('v1', $decoded['meta']['version']);
    }

    public function testSuccessTimestampIsIso8601UtcWithinCallWindow(): void
    {
        $before = time();
        ob_start();
        $this->responder->success([]);
        $output = (string) ob_get_clean();
        $after = time();

        $decoded = json_decode($output, true);
        self::assertIsArray($decoded);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $decoded['meta']['timestamp']);
        $ts = strtotime($decoded['meta']['timestamp']);
        self::assertGreaterThanOrEqual($before, $ts);
        self::assertLessThanOrEqual($after, $ts);
    }

    public function testErrorTimestampIsIso8601UtcWithinCallWindow(): void
    {
        $before = time();
        ob_start();
        $this->responder->error(400, 'bad_request', 'Invalid parameter');
        $output = (string) ob_get_clean();
        $after = time();

        $decoded = json_decode($output, true);
        self::assertIsArray($decoded);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $decoded['meta']['timestamp']);
        $ts = strtotime($decoded['meta']['timestamp']);
        self::assertGreaterThanOrEqual($before, $ts);
        self::assertLessThanOrEqual($after, $ts);
    }

    public function testRawOutputsFlatBodyWithoutEnvelope(): void
    {
        ob_start();
        $this->responder->raw(['status' => 'ok', 'db' => true]);
        $output = (string) ob_get_clean();

        $decoded = json_decode($output, true);
        self::assertIsArray($decoded);
        self::assertSame('ok', $decoded['status']);
        self::assertTrue($decoded['db']);
        self::assertArrayNotHasKey('data', $decoded);
    }

    public function testNotModifiedOutputsNoBody(): void
    {
        ob_start();
        $this->responder->notModified();
        $output = (string) ob_get_clean();

        self::assertSame('', $output);
    }

    public function testSuccessTimestampComesFromInjectedClock(): void
    {
        $responder = new JsonResponder(new FixedClock(1791549296));

        ob_start();
        $responder->success(['x' => 1]);
        $output = (string) ob_get_clean();

        $decoded = json_decode($output, true);
        self::assertIsArray($decoded);
        self::assertSame('2026-10-09T12:34:56Z', $decoded['meta']['timestamp']);
    }

    public function testErrorTimestampComesFromInjectedClock(): void
    {
        $responder = new JsonResponder(new FixedClock(1791549296));

        ob_start();
        $responder->error(404, 'not_found', 'Nope');
        $output = (string) ob_get_clean();

        $decoded = json_decode($output, true);
        self::assertIsArray($decoded);
        self::assertSame('2026-10-09T12:34:56Z', $decoded['meta']['timestamp']);
    }

    public function testTimestampRollsOverAtUtcMidnight(): void
    {
        $clock = new FixedClock(1791763199);
        $responder = new JsonResponder($clock);

        ob_start();
        $responder->success(['x' => 1]);
        $first = json_decode((string) ob_get_clean(), true);
        self::assertIsArray($first);
        self::assertSame('2026-10-11T23:59:59Z', $first['meta']['timestamp']);

        $clock->advance(1);

        ob_start();
        $responder->success(['x' => 1]);
        $second = json_decode((string) ob_get_clean(), true);
        self::assertIsArray($second);
        self::assertSame('2026-10-12T00:00:00Z', $second['meta']['timestamp']);
    }

    public function testCallerSuppliedMetaTimestampOverridesClock(): void
    {
        $responder = new JsonResponder(new FixedClock(1791549296));

        ob_start();
        $responder->success(['x' => 1], ['timestamp' => 'caller-value']);
        $output = (string) ob_get_clean();

        $decoded = json_decode($output, true);
        self::assertIsArray($decoded);
        self::assertSame('caller-value', $decoded['meta']['timestamp']);
    }
}
