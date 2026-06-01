<?php

declare(strict_types=1);

namespace Tests\Api\Controller;

use Api\Controller\SeasonController;
use Api\Response\JsonResponder;
use Tests\WideUnit\WideUnitTestCase;

/**
 * Tests for SeasonController.
 *
 * NOTE: The test environment aliases Season\Season → Tests\WideUnit\Mocks\Season,
 * so the controller uses the mock Season with fixed properties:
 *   - phase = 'Regular Season'
 *   - lastSimNumber = 1
 *   - lastSimStartDate = '2024-01-01'
 *   - lastSimEndDate = '2024-01-02'
 *   - projectedNextSimEndDate = '2024-01-10'
 *   - getPhaseSpecificSimNumber() returns lastSimNumber (= 1)
 */
class SeasonControllerTest extends WideUnitTestCase
{
    // These constants mirror the mock Season's fixed properties
    private const PHASE = 'Regular Season';
    private const SIM_NUMBER = 1;
    private const PHASE_SIM_NUMBER = 1;  // mock returns lastSimNumber
    private const SIM_START_DATE = '2024-01-01';
    private const SIM_END_DATE = '2024-01-02';
    private const PROJECTED_NEXT_SIM_END_DATE = '2024-01-10';

    /**
     * Compute the expected ETag from the concatenated cache key.
     *
     * Mirrors SeasonController::handle():
     * $cacheKey = $season->phase . $season->lastSimNumber . $phaseSimNumber
     *           . $season->lastSimStartDate . $season->lastSimEndDate . $projectedNextSimEndDate;
     */
    private function expectedETag(): string
    {
        $cacheKey = self::PHASE
            . self::SIM_NUMBER
            . self::PHASE_SIM_NUMBER
            . self::SIM_START_DATE
            . self::SIM_END_DATE
            . self::PROJECTED_NEXT_SIM_END_DATE;

        return '"' . md5($cacheKey) . '"';
    }

    public function testHandleReturnsSeasonData(): void
    {
        $controller = new SeasonController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success')
            ->with(
                self::callback(function (array $data): bool {
                    return $data['phase'] === self::PHASE
                        && $data['last_sim']['number'] === self::SIM_NUMBER
                        && $data['last_sim']['phase_sim_number'] === self::PHASE_SIM_NUMBER
                        && $data['last_sim']['start_date'] === self::SIM_START_DATE
                        && $data['last_sim']['end_date'] === self::SIM_END_DATE
                        && $data['projected_next_sim_end_date'] === self::PROJECTED_NEXT_SIM_END_DATE;
                }),
                self::isArray(),
                200,
                self::isArray()
            );

        $controller->handle([], [], $responder);
    }

    public function testHandleReturns304WhenETagMatches(): void
    {
        $_SERVER['HTTP_IF_NONE_MATCH'] = $this->expectedETag();

        $controller = new SeasonController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('notModified');

        $responder->expects($this->never())
            ->method('success');

        $controller->handle([], [], $responder);
    }

    public function testHandlePassesCorrectETagInHeaders(): void
    {
        $expectedTag = $this->expectedETag();

        $controller = new SeasonController($this->mockDb);
        $responder = $this->createMock(JsonResponder::class);

        $responder->expects($this->once())
            ->method('success')
            ->with(
                self::isArray(),
                self::isArray(),
                200,
                self::callback(function (array $headers) use ($expectedTag): bool {
                    return isset($headers['ETag'])
                        && $headers['ETag'] === $expectedTag
                        && $headers['Cache-Control'] === 'public, max-age=60';
                })
            );

        $controller->handle([], [], $responder);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_IF_NONE_MATCH']);
        parent::tearDown();
    }
}
