<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Tuning;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\Tuning\Contracts\TuningAssemblerInterface;
use TradeSuggestions\Tuning\Contracts\TuningViewInterface;
use TradeSuggestions\Tuning\TuningController;
use TradeSuggestions\Tuning\TuningFilter;
use TradeSuggestions\Tuning\TuningReport;
use TradeSuggestions\Tuning\TuningStatus;

final class TuningControllerTest extends TestCase
{
    /** @var list<TuningFilter> */
    private array $received = [];

    public function testHandleParsesQueryBuildsAndRenders(): void
    {
        $out = $this->controller()->handle(['team' => '3', 'status' => 'vetoed']);

        self::assertSame('OK', $out);
        self::assertCount(1, $this->received);
        self::assertSame(3, $this->received[0]->tid);
        self::assertSame(TuningStatus::Vetoed, $this->received[0]->status);
    }

    public function testUnknownQueryValuesFallBackToDefaults(): void
    {
        $this->controller()->handle(['team' => 'x', 'status' => 'y']);

        self::assertSame(TuningFilter::DEFAULT_TID, $this->received[0]->tid);
        self::assertSame(TuningStatus::All, $this->received[0]->status);
        self::assertSame(['team', 'status'], $this->received[0]->ignored);
    }

    public function testHandleReadsNothingFromSuperglobals(): void
    {
        $saved = $_GET;
        try {
            $_GET['team'] = '5';
            $this->controller()->handle([]);
        } finally {
            $_GET = $saved;
        }

        self::assertSame(TuningFilter::DEFAULT_TID, $this->received[0]->tid);
    }

    private function controller(): TuningController
    {
        $assembler = new class ($this->received) implements TuningAssemblerInterface {
            /**
             * @param list<TuningFilter> $received
             */
            public function __construct(private array &$received)
            {
            }

            public function build(TuningFilter $f): TuningReport
            {
                $this->received[] = $f;

                return new TuningReport('sim', $f, [], [], [], false, [], 0.0);
            }
        };
        $view = self::createStub(TuningViewInterface::class);
        $view->method('render')->willReturn('OK');

        return new TuningController($assembler, $view);
    }
}
