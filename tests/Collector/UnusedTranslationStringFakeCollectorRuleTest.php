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

namespace jbboehr\PHPStanLostInTranslation\Tests\Collector;

use jbboehr\PHPStanLostInTranslation\LostInTranslationHelper;
use jbboehr\PHPStanLostInTranslation\ShouldNotHappenException;
use jbboehr\PHPStanLostInTranslation\UnusedTranslationStringCollector;
use jbboehr\PHPStanLostInTranslation\UnusedTranslationStringFakeCollectorRule;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;

final class UnusedTranslationStringFakeCollectorRuleTest extends \PHPUnit\Framework\TestCase
{
    public function testExceptionConversion(): void
    {
        if (!class_exists(FuncCall::class)) {
            $this->markTestIncomplete('This seems to fail when you filter, probably PHPStan autoload does not get initialized');
        }

        $ex = new \RuntimeException(self::class);
        $node = $this->createStub(FuncCall::class);

        $helper = $this->createMock(LostInTranslationHelper::class);
        $helper->method('parseCallLike')
            ->willThrowException($ex);

        $scope = $this->createMock(Scope::class);
        $scope->method('getFile')
            ->willReturn('blade-compiled');

        $obj = new UnusedTranslationStringFakeCollectorRule(
            $helper,
            new UnusedTranslationStringCollector($helper),
        );

        $this->expectException(ShouldNotHappenException::class);
        $this->expectExceptionMessage('phpstan-lost-in-translation');

        $obj->processNode(
            $node,
            $scope,
        );
    }
}
