<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use FreeAgency\Contracts\FreeAgencyDemandRepositoryInterface;
use FreeAgency\Contracts\FreeAgencyRepositoryInterface;
use FreeAgency\FreeAgencyService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FreeAgency\FreeAgencyService
 */
class FreeAgencyServiceTest extends TestCase
{
    private FreeAgencyRepositoryInterface $stubRepo;
    private FreeAgencyDemandRepositoryInterface $stubDemandRepo;

    protected function setUp(): void
    {
        $this->stubRepo = $this->createStub(FreeAgencyRepositoryInterface::class);
        $this->stubDemandRepo = $this->createStub(FreeAgencyDemandRepositoryInterface::class);
    }

    // ── getExistingOffer ─────────────────────────────────────────

    public function testGetExistingOfferReturnsZerosWhenRepoReturnsNull(): void
    {
        $this->stubRepo->method('getExistingOffer')->willReturn(null);

        $service = new FreeAgencyService($this->stubRepo, $this->stubDemandRepo, $this->createStub(\mysqli::class));
        $result = $service->getExistingOffer(1, 100);

        $this->assertSame(0, $result['offer1']);
        $this->assertSame(0, $result['offer6']);
        $this->assertCount(6, $result);
    }

    public function testGetExistingOfferMapsAllSixOfferFields(): void
    {
        $this->stubRepo->method('getExistingOffer')->willReturn([
            'offer1' => 500,
            'offer2' => 450,
            'offer3' => 400,
            'offer4' => 350,
            'offer5' => 300,
            'offer6' => 250,
        ]);

        $service = new FreeAgencyService($this->stubRepo, $this->stubDemandRepo, $this->createStub(\mysqli::class));
        $result = $service->getExistingOffer(1, 100);

        $this->assertSame(500, $result['offer1']);
        $this->assertSame(450, $result['offer2']);
        $this->assertSame(400, $result['offer3']);
        $this->assertSame(350, $result['offer4']);
        $this->assertSame(300, $result['offer5']);
        $this->assertSame(250, $result['offer6']);
    }

    public function testGetExistingOfferCoercesNullValuesToZero(): void
    {
        $this->stubRepo->method('getExistingOffer')->willReturn([
            'offer1' => 500,
            'offer2' => null,
            'offer3' => null,
            'offer4' => 350,
            'offer5' => null,
            'offer6' => null,
        ]);

        $service = new FreeAgencyService($this->stubRepo, $this->stubDemandRepo, $this->createStub(\mysqli::class));
        $result = $service->getExistingOffer(1, 100);

        $this->assertSame(500, $result['offer1']);
        $this->assertSame(0, $result['offer2']);
        $this->assertSame(0, $result['offer3']);
        $this->assertSame(350, $result['offer4']);
        $this->assertSame(0, $result['offer5']);
        $this->assertSame(0, $result['offer6']);
    }

    public function testGetExistingOfferReturnsIntegers(): void
    {
        $this->stubRepo->method('getExistingOffer')->willReturn([
            'offer1' => '500',
            'offer2' => '0',
            'offer3' => '400',
            'offer4' => '350',
            'offer5' => '300',
            'offer6' => '250',
        ]);

        $service = new FreeAgencyService($this->stubRepo, $this->stubDemandRepo, $this->createStub(\mysqli::class));
        $result = $service->getExistingOffer(1, 100);

        foreach ($result as $value) {
            $this->assertIsInt($value);
        }
    }
}
