<?php

declare(strict_types=1);

namespace Tests\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Services\QueryConditions;

#[CoversClass(QueryConditions::class)]
class QueryConditionsTest extends TestCase
{
    #[Test]
    public function emptyConditionsReturnsOneEqualsOne(): void
    {
        $qc = new QueryConditions();

        $this->assertSame('1=1', $qc->toWhereClause());
        $this->assertSame('', $qc->getTypes());
        $this->assertSame([], $qc->getParams());
        $this->assertFalse($qc->hasParams());
    }

    #[Test]
    public function baseConditionsIncluded(): void
    {
        $qc = new QueryConditions(['status = 1', 'deleted = 0']);

        $this->assertSame('status = 1 AND deleted = 0', $qc->toWhereClause());
        $this->assertFalse($qc->hasParams());
    }

    #[Test]
    public function addAppendsConditionAndParam(): void
    {
        $qc = new QueryConditions();
        $qc->add('name = ?', 's', 'John');
        $qc->add('age > ?', 'i', 25);

        $this->assertSame('name = ? AND age > ?', $qc->toWhereClause());
        $this->assertSame('si', $qc->getTypes());
        $this->assertSame(['John', 25], $qc->getParams());
        $this->assertTrue($qc->hasParams());
    }

    #[Test]
    public function addIfNotNullSkipsNull(): void
    {
        $qc = new QueryConditions();
        $qc->addIfNotNull('year = ?', 'i', null);
        $qc->addIfNotNull('team = ?', 's', 'Lakers');

        $this->assertSame('team = ?', $qc->toWhereClause());
        $this->assertSame('s', $qc->getTypes());
        $this->assertSame(['Lakers'], $qc->getParams());
    }

    #[Test]
    public function baseConditionsWithBoundParams(): void
    {
        $qc = new QueryConditions(['active = 1']);
        $qc->add('name LIKE ?', 's', '%test%');

        $this->assertSame('active = 1 AND name LIKE ?', $qc->toWhereClause());
        $this->assertSame('s', $qc->getTypes());
        $this->assertSame(['%test%'], $qc->getParams());
    }
}
