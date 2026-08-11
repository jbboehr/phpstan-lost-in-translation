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
