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

namespace jbboehr\PHPStanLostInTranslation\Tests\CallRule;

use jbboehr\PHPStanLostInTranslation\CallRule\CallRuleCollection;
use PHPStan\DependencyInjection\Container;
use PHPStan\DependencyInjection\ParameterNotFoundException;
use PHPUnit\Framework\TestCase;

class CallRuleCollectionTest extends TestCase
{
    public function testMissingParameterDoesNotThrow(): void
    {
        $mock = $this->createMock(Container::class);
        $mock->method('getParameter')
            /** @phpstan-ignore-next-line phpstanApi.constructor */
            ->willThrowException(new ParameterNotFoundException('lostInTranslation'));

        $collection = new CallRuleCollection($mock);
        $this->assertCount(0, $collection);
    }

    public function testNonArrayParameterDoesNotThrow(): void
    {
        $mock = $this->createMock(Container::class);
        $mock->method('getParameter')
            ->willReturn('foo');

        $collection = new CallRuleCollection($mock);
        $this->assertCount(0, $collection);
    }
}
