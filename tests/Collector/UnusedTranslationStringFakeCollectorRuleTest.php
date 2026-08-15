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
use jbboehr\PHPStanLostInTranslation\TranslationCall;
use jbboehr\PHPStanLostInTranslation\UnusedTranslationStringCollector;
use jbboehr\PHPStanLostInTranslation\UnusedTranslationStringFakeCollectorRule;
use jbboehr\PHPStanLostInTranslation\UsedTranslationRecord;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Type\Constant\ConstantStringType;

final class UnusedTranslationStringFakeCollectorRuleTest extends \PHPUnit\Framework\TestCase
{
    public function testForwardsBladeCallsToTheOuterCollector(): void
    {
        $node = $this->createStub(FuncCall::class);
        $bladeScope = $this->createMock(Scope::class);
        $bladeScope->method('getFile')
            ->willReturn('/tmp/098f6bcd4621d373cade4e832627b4f6-blade-compiled.php');

        $call = new TranslationCall(
            className: null,
            functionName: '__',
            file: '/tmp/example.blade.php',
            line: 19,
            possibleTranslations: [],
            keyType: new ConstantStringType('messages.blade'),
            localeType: new ConstantStringType('ja'),
            explicitLocales: ['ja'],
            usesImplicitLocale: false,
        );

        $bladeHelper = $this->createMock(LostInTranslationHelper::class);
        $bladeHelper->expects($this->once())
            ->method('parseCallLike')
            ->with($node, $bladeScope)
            ->willReturn($call);

        $outerHelper = $this->createStub(LostInTranslationHelper::class);
        $collector = new UnusedTranslationStringCollector($outerHelper);
        $rule = new UnusedTranslationStringFakeCollectorRule($bladeHelper, $collector);

        $this->assertSame([], $rule->processNode($node, $bladeScope));

        $outerScope = $this->createMock(Scope::class);
        $outerScope->method('getFile')
            ->willReturn('/tmp/example.php');

        $this->assertEquals([
            new UsedTranslationRecord(
                key: 'messages.blade',
                locale: 'ja',
                file: '/tmp/example.blade.php',
                line: 19,
            ),
        ], $collector->processNode($node, $outerScope));
    }

    public function testIgnoresOrdinaryFilesInsideDirectoriesNamedBladeCompiled(): void
    {
        $node = $this->createStub(FuncCall::class);
        $scope = $this->createMock(Scope::class);
        $scope->method('getFile')
            ->willReturn('/tmp/blade-compiled-project/example.php');

        $helper = $this->createMock(LostInTranslationHelper::class);
        $helper->expects($this->never())
            ->method('parseCallLike');

        $rule = new UnusedTranslationStringFakeCollectorRule(
            $helper,
            new UnusedTranslationStringCollector($helper),
        );

        $this->assertSame([], $rule->processNode($node, $scope));
    }

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
            ->willReturn('/tmp/098f6bcd4621d373cade4e832627b4f6-blade-compiled.php');

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
