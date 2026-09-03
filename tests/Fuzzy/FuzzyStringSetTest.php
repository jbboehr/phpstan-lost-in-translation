<?php
/**
 * Copyright (c) anno Domini nostri Jesu Christi MMXXV John Boehr & contributors
 *
 * SPDX-License-Identifier: AGPL-3.0-only WITH romic-exception
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License version 3,
 * as published by the Free Software Foundation, together with the Romic
 * Exception (an additional permission under section 7 of that license).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * and the Romic Exception along with this program.  If not, see
 * <http://www.gnu.org/licenses/> and docs/LICENSE_EXCEPTION.md.
 */
declare(strict_types=1);

namespace jbboehr\PHPStanLostInTranslation\Tests\Fuzzy;

use jbboehr\PHPStanLostInTranslation\Fuzzy\FuzzyStringSetFactory;
use jbboehr\PHPStanLostInTranslation\Fuzzy\FuzzyStringSetInterface;
use jbboehr\PHPStanLostInTranslation\Fuzzy\MemoizingFuzzyStringSet;
use jbboehr\PHPStanLostInTranslation\Fuzzy\NaiveFuzzyStringSet;
use jbboehr\PHPStanLostInTranslation\Fuzzy\NullFuzzyStringSet;
use jbboehr\PHPStanLostInTranslation\Tests\Benchmark\AbstractFuzzyStringSetBenchmark;
use jbboehr\PHPStanLostInTranslation\Tests\Benchmark\NaiveFuzzyStringSetBenchmark;
use PHPUnit\Framework\TestCase;

final class FuzzyStringSetTest extends TestCase
{
    public function testDataSet1(): void
    {
        self::expectNotToPerformAssertions();

        $benchmark = new NaiveFuzzyStringSetBenchmark();

        $benchmark->setupDataSet1();
        $benchmark->benchDataSet1Cold();
    }

    public function testDataSet1Memoized(): void
    {
        self::expectNotToPerformAssertions();

        $benchmark = new NaiveFuzzyStringSetBenchmark();

        $benchmark->setupDataSet1Memoized();

        for ($i = 0; $i < 10; ++$i) {
            $benchmark->benchDataSet1Warm();
        }
    }

    public function testDataSet2(): void
    {
        self::expectNotToPerformAssertions();

        $benchmark = new NaiveFuzzyStringSetBenchmark();

        $benchmark->setupDataSet2();
        $benchmark->benchDataSet2Cold();
    }

    public function testNullFuzzyStringSet(): void
    {
        $set = new NullFuzzyStringSet();
        $set->addMany(AbstractFuzzyStringSetBenchmark::DATA_SET_1);
        $set->add(AbstractFuzzyStringSetBenchmark::DATA_SET_1[0]);

        $this->assertNull($set->search('tezt'));
    }

    public function testEmptySetReturnsNull(): void
    {
        $set = new NaiveFuzzyStringSet();

        $this->assertNull($set->search('test'));
    }

    public function testSearchReturnsTheClosestCandidateOrNull(): void
    {
        $set = new NaiveFuzzyStringSet();
        $set->addMany(['test', 'toast']);

        $this->assertSame('test', $set->search('tezt'));
        $this->assertNull($set->search('zzzz'));
    }

    public function testNumericStringCandidatesRemainStrings(): void
    {
        $set = new NaiveFuzzyStringSet(['1234']);

        $this->assertSame('1234', $set->search('1235'));
    }

    public function testMemoizingSetPreservesSearchResults(): void
    {
        $inner = $this->createMock(FuzzyStringSetInterface::class);
        $inner->expects($this->exactly(2))
            ->method('search')
            ->willReturnMap([
                ['tezt', 'test'],
                ['zzzz', null],
            ]);
        $set = new MemoizingFuzzyStringSet($inner);

        $this->assertSame('test', $set->search('tezt'));
        $this->assertSame('test', $set->search('tezt'));
        $this->assertNull($set->search('zzzz'));
        $this->assertNull($set->search('zzzz'));
    }

    public function testMemoizingSetForwardsAddManyAndInvalidatesCachedResults(): void
    {
        $inner = $this->createMock(FuzzyStringSetInterface::class);
        $inner->expects($this->once())
            ->method('addMany')
            ->with(['test']);
        $inner->expects($this->exactly(2))
            ->method('search')
            ->with('tezt')
            ->willReturnOnConsecutiveCalls(null, 'test');
        $set = new MemoizingFuzzyStringSet($inner);

        $this->assertNull($set->search('tezt'));
        $set->addMany(['test']);
        $this->assertSame('test', $set->search('tezt'));
    }

    public function testFactoryMemoizesByDefaultAndCanReturnTheSelectedImplementationDirectly(): void
    {
        $memoized = (new FuzzyStringSetFactory())->createFuzzyStringSet(['test']);
        $direct = (new FuzzyStringSetFactory(NaiveFuzzyStringSet::class, false))->createFuzzyStringSet(['test']);

        $this->assertInstanceOf(MemoizingFuzzyStringSet::class, $memoized);
        $this->assertSame('test', $memoized->search('tezt'));
        $this->assertInstanceOf(NaiveFuzzyStringSet::class, $direct);
        $this->assertSame('test', $direct->search('tezt'));
    }
}
