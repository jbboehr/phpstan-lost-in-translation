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

namespace jbboehr\PHPStanLostInTranslation\Tests;

use jbboehr\PHPStanLostInTranslation\CallRule\CallRuleCollection;
use jbboehr\PHPStanLostInTranslation\LostInTranslationHelper;
use jbboehr\PHPStanLostInTranslation\Rule\LostInTranslationRule;
use jbboehr\PHPStanLostInTranslation\ShouldNotHappenException;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;

final class ShouldNotHappenExceptionTest extends \PHPUnit\Framework\TestCase
{
    public function testRethrow(): void
    {
        $exception = new \Exception('msg');
        $this->expectExceptionMessage('msg');
        $this->expectException(ShouldNotHappenException::class);
        SHouldNotHappenException::rethrow($exception);
    }

    public function testExceptionConversion(): void
    {
        if (!class_exists(FuncCall::class)) {
            $this->markTestIncomplete('This seems to fail when you filter, probably PHPStan autoload does not get initialized');
        }

        $ex = new \RuntimeException(self::class);
        $mock = $this->createMock(LostInTranslationHelper::class);
        $mock->method('parseCallLike')
            ->willThrowException($ex);

        $node = $this->createStub(FuncCall::class);

        $obj = new LostInTranslationRule($mock, CallRuleCollection::createFromArray([]));

        $this->expectException(ShouldNotHappenException::class);
        $this->expectExceptionMessage('phpstan-lost-in-translation');

        $obj->processNode(
            $node,
            $this->createStub(Scope::class),
        );
    }
}
