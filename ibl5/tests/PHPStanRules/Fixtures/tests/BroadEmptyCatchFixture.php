<?php

declare(strict_types=1);

final class BroadEmptyCatchFixture
{
    public function testSwallowsEverything(): void
    {
        try {
            $this->systemUnderTest();
        } catch (\Throwable $e) {
            // Intentionally ignored.
        }

        $this->assertTrue($this->collaboratorWasCalled());
    }

    public function testNarrowEmptyCatchIsAllowed(): void
    {
        try {
            $this->systemUnderTest();
        } catch (\RuntimeException $e) {
        }

        $this->assertTrue($this->collaboratorWasCalled());
    }

    public function testBroadCatchWithABodyIsAllowed(): void
    {
        try {
            $this->systemUnderTest();
        } catch (\Throwable $e) {
            $this->assertSame('boom', $e->getMessage());
        }
    }

    public function testSwallowsEverythingWithoutEvenAComment(): void
    {
        try {
            $this->systemUnderTest();
        } catch (\Exception $e) {
        }

        $this->assertTrue($this->collaboratorWasCalled());
    }

    private function systemUnderTest(): void
    {
    }

    private function collaboratorWasCalled(): bool
    {
        return true;
    }
}
