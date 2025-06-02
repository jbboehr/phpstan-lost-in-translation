<?php
/**
 * Copyright (c) anno Domini nostri Jesu Christi MMXXV John Boehr & contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
declare(strict_types=1);

namespace jbboehr\PHPStanLostInTranslation\Tests\Fuzzy;

use jbboehr\PHPStanLostInTranslation\Fuzzy\NullFuzzyStringSet;
use jbboehr\PHPStanLostInTranslation\Tests\Benchmark\AbstractFuzzyStringSetBenchmark;
use jbboehr\PHPStanLostInTranslation\Tests\Benchmark\FuseFuzzyStringSetBenchmark;
use jbboehr\PHPStanLostInTranslation\Tests\Benchmark\MyFuzzyStringSetBenchmark;
use jbboehr\PHPStanLostInTranslation\Tests\Benchmark\NaiveFuzzyStringSetBenchmark;
use PHPUnit\Framework\TestCase;

final class FuzzyStringSetTest extends TestCase
{
    /**
     * @dataProvider benchmarkProvider
     * @param class-string<AbstractFuzzyStringSetBenchmark> $className
     */
    public function testDataSet1(string $className): void
    {
        self::expectNotToPerformAssertions();

        /** @var AbstractFuzzyStringSetBenchmark $benchmark */
        $benchmark = new $className();

        $benchmark->setupDataSet1();
        $benchmark->benchDataSet1();
    }

    /**
     * @dataProvider benchmarkProvider
     * @param class-string<AbstractFuzzyStringSetBenchmark> $className
     */
    public function testDataSet1Memoized(string $className): void
    {
        self::expectNotToPerformAssertions();

        /** @var AbstractFuzzyStringSetBenchmark $benchmark */
        $benchmark = new $className();

        $benchmark->setupDataSet1Memoized();

        for ($i = 0; $i < 10; ++$i) {
            $benchmark->benchDataSet1Memoized();
        }
    }

    /**
     * @dataProvider benchmarkProvider
     * @param class-string<AbstractFuzzyStringSetBenchmark> $className
     */
    public function testDataSet2(string $className): void
    {
        self::expectNotToPerformAssertions();

        /** @var AbstractFuzzyStringSetBenchmark $benchmark */
        $benchmark = new $className();

        $benchmark->setupDataSet2();
        $benchmark->benchDataSet2();
    }

    public function testNullFuzzyStringSet(): void
    {
        $set = new NullFuzzyStringSet();
        $set->addMany(AbstractFuzzyStringSetBenchmark::DATA_SET_1);
        $set->add(AbstractFuzzyStringSetBenchmark::DATA_SET_1[0]);

        $this->assertNull($set->search('tezt'));
    }

    /**
     * @return list<array{class-string<AbstractFuzzyStringSetBenchmark>}>
     */
    public static function benchmarkProvider(): array
    {
        return [
            [FuseFuzzyStringSetBenchmark::class],
            [MyFuzzyStringSetBenchmark::class],
            [NaiveFuzzyStringSetBenchmark::class],
        ];
    }
}
