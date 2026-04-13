<?php

declare(strict_types=1);

namespace Tests\Unit\BulkImport;

use BulkImport\JsbFileType;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BulkImport\JsbFileType
 */
class JsbFileTypeTest extends TestCase
{
    public function testAllThirteenCasesExist(): void
    {
        $this->assertCount(13, JsbFileType::cases());
    }

    public function testSnapshotTypesArePlrAndPlb(): void
    {
        $snapshotTypes = array_filter(
            JsbFileType::cases(),
            static fn (JsbFileType $t): bool => $t->iterationMode() === 'snapshot',
        );

        $values = array_map(
            static fn (JsbFileType $t): string => $t->value,
            array_values($snapshotTypes),
        );

        $this->assertSame(['plr', 'plb'], $values);
    }

    public function testCumulativeTypesAreElevenTypes(): void
    {
        $cumulativeTypes = array_filter(
            JsbFileType::cases(),
            static fn (JsbFileType $t): bool => $t->iterationMode() === 'cumulative',
        );

        $this->assertCount(11, $cumulativeTypes);
    }

    public function testTrnOrderedBeforeCar(): void
    {
        $this->assertLessThan(
            JsbFileType::Car->importOrder(),
            JsbFileType::Trn->importOrder(),
        );
    }

    public function testCumulativeTypesOrderedBeforeSnapshotTypes(): void
    {
        $maxCumulative = max(array_map(
            static fn (JsbFileType $t): int => $t->importOrder(),
            array_filter(
                JsbFileType::cases(),
                static fn (JsbFileType $t): bool => $t->iterationMode() === 'cumulative',
            ),
        ));

        $minSnapshot = min(array_map(
            static fn (JsbFileType $t): int => $t->importOrder(),
            array_filter(
                JsbFileType::cases(),
                static fn (JsbFileType $t): bool => $t->iterationMode() === 'snapshot',
            ),
        ));

        $this->assertLessThan($minSnapshot, $maxCumulative);
    }

    public function testAllImportOrdersAreUnique(): void
    {
        $orders = array_map(
            static fn (JsbFileType $t): int => $t->importOrder(),
            JsbFileType::cases(),
        );

        $this->assertSame(count($orders), count(array_unique($orders)));
    }

    public function testSupportsVerifyTrueForCumulativeTypes(): void
    {
        $this->assertTrue(JsbFileType::Car->supportsVerify());
        $this->assertTrue(JsbFileType::Trn->supportsVerify());
        $this->assertTrue(JsbFileType::Sco->supportsVerify());
        $this->assertTrue(JsbFileType::Lge->supportsVerify());
    }

    public function testSupportsVerifyFalseForSnapshotTypes(): void
    {
        $this->assertFalse(JsbFileType::Plr->supportsVerify());
        $this->assertFalse(JsbFileType::Plb->supportsVerify());
    }

    public function testLabelStartsWithDotAndExtension(): void
    {
        foreach (JsbFileType::cases() as $type) {
            $this->assertStringStartsWith('.' . $type->value, $type->label());
        }
    }

    public function testAllValidReturnsStringValues(): void
    {
        $valid = JsbFileType::allValid();

        $this->assertCount(13, $valid);
        $this->assertContains('trn', $valid);
        $this->assertContains('plr', $valid);
        $this->assertContains('lge', $valid);
    }

    public function testSortedByImportOrderReturnsCorrectSequence(): void
    {
        $sorted = JsbFileType::sortedByImportOrder();

        $this->assertSame(JsbFileType::Trn, $sorted[0]);
        $this->assertSame(JsbFileType::Car, $sorted[1]);
        $this->assertSame(JsbFileType::Plb, $sorted[12]);

        // Verify monotonically increasing
        for ($i = 1; $i < count($sorted); $i++) {
            $this->assertGreaterThan(
                $sorted[$i - 1]->importOrder(),
                $sorted[$i]->importOrder(),
            );
        }
    }

    public function testBackedStringValuesMatchExpected(): void
    {
        $this->assertSame('trn', JsbFileType::Trn->value);
        $this->assertSame('car', JsbFileType::Car->value);
        $this->assertSame('his', JsbFileType::His->value);
        $this->assertSame('asw', JsbFileType::Asw->value);
        $this->assertSame('awa', JsbFileType::Awa->value);
        $this->assertSame('rcb', JsbFileType::Rcb->value);
        $this->assertSame('sco', JsbFileType::Sco->value);
        $this->assertSame('dra', JsbFileType::Dra->value);
        $this->assertSame('ret', JsbFileType::Ret->value);
        $this->assertSame('hof', JsbFileType::Hof->value);
        $this->assertSame('lge', JsbFileType::Lge->value);
        $this->assertSame('plr', JsbFileType::Plr->value);
        $this->assertSame('plb', JsbFileType::Plb->value);
    }

    public function testTryFromReturnsNullForInvalidValue(): void
    {
        $this->assertNull(JsbFileType::tryFrom('xyz'));
    }

    public function testTryFromReturnsEnumForValidValue(): void
    {
        $this->assertSame(JsbFileType::Sco, JsbFileType::tryFrom('sco'));
    }
}
