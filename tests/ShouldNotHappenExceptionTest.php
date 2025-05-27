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

use jbboehr\PHPStanLostInTranslation\DynamicTranslationStringRule;
use jbboehr\PHPStanLostInTranslation\InvalidChoiceRule;
use jbboehr\PHPStanLostInTranslation\InvalidReplacementRule;
use jbboehr\PHPStanLostInTranslation\LostInTranslationHelper;
use jbboehr\PHPStanLostInTranslation\MissingTranslationStringInBaseLocaleRule;
use jbboehr\PHPStanLostInTranslation\MissingTranslationStringRule;
use jbboehr\PHPStanLostInTranslation\ShouldNotHappenException;
use jbboehr\PHPStanLostInTranslation\UnusedTranslationStringCollector;
use jbboehr\PHPStanLostInTranslation\UnusedTranslationStringRule;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;

final class ShouldNotHappenExceptionTest extends \PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $r = new \ReflectionProperty(ShouldNotHappenException::class, 'url');
        $r->setValue(null, null);
    }

    public function testMissingComposerJson(): void
    {
        $propertyReflection = new \ReflectionProperty(ShouldNotHappenException::class, 'composerJsonPath');
        $originalValue = $propertyReflection->getValue();

        $constReflection = new \ReflectionClassConstant(ShouldNotHappenException::class, 'URL');

        try {
            $propertyReflection->setValue(null, __DIR__ . '/does-not-exist');
            $exception = new ShouldNotHappenException();

            $this->assertIsString($constReflection->getValue());
            $this->assertStringContainsString($constReflection->getValue(), $exception->getMessage());
        } finally {
            $propertyReflection->setValue(null, $originalValue);
        }
    }

    public function testInvalidComposerJson(): void
    {
        $propertyReflection = new \ReflectionProperty(ShouldNotHappenException::class, 'composerJsonPath');
        $originalValue = $propertyReflection->getValue();

        $constReflection = new \ReflectionClassConstant(ShouldNotHappenException::class, 'URL');

        $tmpFile = tempnam(sys_get_temp_dir(), '') ?: throw new \RuntimeException();
        file_put_contents($tmpFile, '{"foo') ?: throw new \RuntimeException();

        try {
            $propertyReflection->setValue(null, $tmpFile);
            $exception = new ShouldNotHappenException();

            $this->assertIsString($constReflection->getValue());
            $this->assertStringContainsString($constReflection->getValue(), $exception->getMessage());
        } finally {
            $propertyReflection->setValue(null, $originalValue);

            unlink($tmpFile);
        }
    }

    public function testDetectsPackageHomepage(): void
    {
        $propertyReflection = new \ReflectionProperty(ShouldNotHappenException::class, 'composerJsonPath');
        $originalValue = $propertyReflection->getValue();

        $tmpFile = tempnam(sys_get_temp_dir(), '') ?: throw new \RuntimeException();
        file_put_contents($tmpFile, json_encode([
            'name' => 'foobar/lost-in-translation',
            'homepage' => 'google',
        ], flags: JSON_THROW_ON_ERROR)) ?: throw new \RuntimeException();

        try {
            $propertyReflection->setValue(null, $tmpFile);
            $exception = new ShouldNotHappenException();

            $this->assertStringContainsString('google', $exception->getMessage());
        } finally {
            $propertyReflection->setValue(null, $originalValue);

            unlink($tmpFile);
        }
    }

    public function testRethrow(): void
    {
        $exception = new \Exception('msg');
        $this->expectExceptionMessage('msg');
        $this->expectException(ShouldNotHappenException::class);
        SHouldNotHappenException::rethrow($exception);
    }

    public function testCachesUrl(): void
    {
        $r = new \ReflectionProperty(ShouldNotHappenException::class, 'url');
        $originalValue = $r->getValue();

        try {
            $r->setValue(null, 'foobar');

            $this->assertStringContainsString('foobar', (new ShouldNotHappenException())->getMessage());
        } finally {
            $r->setValue(null, $originalValue);
        }
    }

    /**
     * @dataProvider classNameProvider
     */
    public function testExceptionConversion(string $className): void
    {
        if (!class_exists(FuncCall::class)) {
            $this->markTestIncomplete('This seems to fail when you filter, probably PHPStan autoload does not get initialized');
        }

        $ex = new \RuntimeException(self::class);
        $mock = $this->createMock(LostInTranslationHelper::class);
        $mock->method('parseCallLike')
            ->willThrowException($ex);
        $mock->method('markUsed')
            ->willThrowException($ex);
        $obj = new $className($mock);
        $this->assertTrue($obj instanceof Rule || $obj instanceof Collector);

        if ($className === UnusedTranslationStringRule::class) {
            /** @phpstan-ignore-next-line phpstanApi.constructor */
            $node = new CollectedDataNode([
                __FILE__ => [
                    UnusedTranslationStringCollector::class => [[['', '']]],
                ],
            ], true);
        } else {
            $node = $this->createStub(FuncCall::class);
        }

        $this->expectException(ShouldNotHappenException::class);
        $this->expectExceptionMessage('phpstan-lost-in-translation');

        $obj->processNode(
            $node,
            $this->createStub(Scope::class),
        );
    }

    private const DATA_SET = [
        [DynamicTranslationStringRule::class],
        [InvalidChoiceRule::class],
        [InvalidReplacementRule::class],
        [MissingTranslationStringRule::class],
        [MissingTranslationStringInBaseLocaleRule::class],
        [UnusedTranslationStringRule::class],
        [UnusedTranslationStringCollector::class],
    ];

    /**
     * @return self::DATA_SET
     */
    public static function classNameProvider(): array
    {
        return self::DATA_SET;
    }
}
