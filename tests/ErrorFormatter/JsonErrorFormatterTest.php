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

namespace jbboehr\PHPStanLostInTranslation\Tests\ErrorFormatter;

use jbboehr\PHPStanLostInTranslation\CallRule\MissingTranslationStringRule;
use jbboehr\PHPStanLostInTranslation\ErrorFormatter\JsonErrorFormatter;
use jbboehr\PHPStanLostInTranslation\ShouldNotHappenException;
use Nette\Utils\Json;
use PHPStan\Command\AnalysisResult;
use PHPStan\Command\Output;
use PHPStan\Testing\ErrorFormatterTestCase;

/**
 * @phpstan-ignore phpstanApi.class
 */
final class JsonErrorFormatterTest extends ErrorFormatterTestCase
{
    public function testStuff(): void
    {
        $formatter = new JsonErrorFormatter(true);

        $analysisResult = self::makeAnalysisResult();

        /** @phpstan-ignore-next-line phpstanApi.method */
        $formatter->formatErrors($analysisResult, $this->getOutput());

        /** @phpstan-ignore-next-line phpstanApi.method */
        $actual = Json::decode($this->getOutputContent(), true);

        $this->assertIsArray($actual);
        $this->assertArrayHasKey(MissingTranslationStringRule::IDENTIFIER, $actual);
        $this->assertArrayHasKey('ja', $actual[MissingTranslationStringRule::IDENTIFIER]);
        $this->assertArrayHasKey('missing translation string', $actual[MissingTranslationStringRule::IDENTIFIER]['ja']);
    }

    public function testExceptionConversion(): void
    {
        $exception = new \RuntimeException('foobar');
        $analysisResult = self::makeAnalysisResult();

        $output = $this->createMock(Output::class);
        $output->expects(self::atLeastOnce())
            ->method('writeRaw')
            ->willThrowException($exception);

        $formatter = new JsonErrorFormatter(true, static function (string $message) {
            self::assertStringContainsString('RuntimeException', $message);
        });

        $this->expectException(ShouldNotHappenException::class);

        $formatter->formatErrors($analysisResult, $output);
    }

    private static function makeAnalysisResult(): AnalysisResult
    {
        // phpcs:disable
        return (static function () {
            $class = new \ReflectionClass(\PHPStan\Command\AnalysisResult::class);
            $object = $class->newInstanceWithoutConstructor();

            (function () {
                $this->notFileSpecificErrors = [];
                $this->internalErrors = [];
                $this->warnings = [];
                $this->collectedData = [
                    \PHPStan\Collectors\CollectedData::__set_state([
                        'data' => [
                            [
                                'jbboehr\\PHPStanLostInTranslation\\UsedTranslationRecord' => 'Tzo1NDoiamJib2VoclxQSFBTdGFuTG9zdEluVHJhbnNsYXRpb25cVXNlZFRyYW5zbGF0aW9uUmVjb3JkIjo0OntzOjM6ImtleSI7czo3OiJmb28uYmFyIjtzOjY6ImxvY2FsZSI7czoyOiJlbiI7czo0OiJmaWxlIjtzOjk2OiIvaG9tZS9yaW4vQ29kZS9waHBzdGFuLWxvc3QtaW4tdHJhbnNsYXRpb24vZTJlL3NyYy9taXNzaW5nLXRyYW5zbGF0aW9uLXN0cmluZy1pbi1iYXNlLWxvY2FsZS5waHAiO3M6NDoibGluZSI7aTozO30=',
                            ],
                        ],
                        'filePath' => __DIR__ . '/../../e2e/src/missing-translation-string-in-base-locale.php',
                        'collectorType' => 'jbboehr\\PHPStanLostInTranslation\\UnusedTranslationStringCollector',
                    ]),
                    \PHPStan\Collectors\CollectedData::__set_state([
                        'data' => [
                            [
                                'jbboehr\\PHPStanLostInTranslation\\UsedTranslationRecord' => 'Tzo1NDoiamJib2VoclxQSFBTdGFuTG9zdEluVHJhbnNsYXRpb25cVXNlZFRyYW5zbGF0aW9uUmVjb3JkIjo0OntzOjM6ImtleSI7czo1NjoiezB9IFRoZXJlIGFyZSBub25lfHsxfSBUaGVyZSBpcyBvbmV8WzJdIFRoZXJlIGFyZSA6Y291bnQiO3M6NjoibG9jYWxlIjtzOjI6ImVuIjtzOjQ6ImZpbGUiO3M6Njk6Ii9ob21lL3Jpbi9Db2RlL3BocHN0YW4tbG9zdC1pbi10cmFuc2xhdGlvbi9lMmUvc3JjL2ludmFsaWQtY2hvaWNlLnBocCI7czo0OiJsaW5lIjtpOjM7fQ==',
                            ],
                        ],
                        'filePath' => __DIR__ . '/../../e2e/src/invalid-choice.php',
                        'collectorType' => 'jbboehr\\PHPStanLostInTranslation\\UnusedTranslationStringCollector',
                    ]),
                    \PHPStan\Collectors\CollectedData::__set_state([
                        'data' => 'sample',
                        'filePath' => __DIR__ . '/../../e2e/src/blade.php',
                        'collectorType' => 'Larastan\\Larastan\\Collectors\\UsedViewFunctionCollector',
                    ]),
                    \PHPStan\Collectors\CollectedData::__set_state([
                        'data' => [
                            [
                                'jbboehr\\PHPStanLostInTranslation\\UsedTranslationRecord' => 'Tzo1NDoiamJib2VoclxQSFBTdGFuTG9zdEluVHJhbnNsYXRpb25cVXNlZFRyYW5zbGF0aW9uUmVjb3JkIjo0OntzOjM6ImtleSI7czoxODoiYmxhZGUgYXQgZGlyZWN0aXZlIjtzOjY6ImxvY2FsZSI7czoxOiIqIjtzOjQ6ImZpbGUiO3M6NTY6Ii90bXAvMjRmZjU4YzZjYmIyYjkwNzY3NGVmMmFiYjg1YTg1ZGMtYmxhZGUtY29tcGlsZWQucGhwIjtzOjQ6ImxpbmUiO2k6NTt9',
                            ],
                            [
                                'jbboehr\\PHPStanLostInTranslation\\UsedTranslationRecord' => 'Tzo1NDoiamJib2VoclxQSFBTdGFuTG9zdEluVHJhbnNsYXRpb25cVXNlZFRyYW5zbGF0aW9uUmVjb3JkIjo0OntzOjM6ImtleSI7czoyMzoiYmxhZGUgZG91YmxlIHVuZGVyc2NvcmUiO3M6NjoibG9jYWxlIjtzOjE6IioiO3M6NDoiZmlsZSI7czo1NjoiL3RtcC8yNGZmNThjNmNiYjJiOTA3Njc0ZWYyYWJiODVhODVkYy1ibGFkZS1jb21waWxlZC5waHAiO3M6NDoibGluZSI7aTo3O30=',
                            ],
                            [
                                'jbboehr\\PHPStanLostInTranslation\\UsedTranslationRecord' => 'Tzo1NDoiamJib2VoclxQSFBTdGFuTG9zdEluVHJhbnNsYXRpb25cVXNlZFRyYW5zbGF0aW9uUmVjb3JkIjo0OntzOjM6ImtleSI7czoyMToiZXhpc3RzIGluIGFsbCBsb2NhbGVzIjtzOjY6ImxvY2FsZSI7czoxOiIqIjtzOjQ6ImZpbGUiO3M6NTY6Ii90bXAvMjRmZjU4YzZjYmIyYjkwNzY3NGVmMmFiYjg1YTg1ZGMtYmxhZGUtY29tcGlsZWQucGhwIjtzOjQ6ImxpbmUiO2k6OTt9',
                            ],
                            [
                                'jbboehr\\PHPStanLostInTranslation\\UsedTranslationRecord' => 'Tzo1NDoiamJib2VoclxQSFBTdGFuTG9zdEluVHJhbnNsYXRpb25cVXNlZFRyYW5zbGF0aW9uUmVjb3JkIjo0OntzOjM6ImtleSI7czoxMDoib25seSBpbiBqYSI7czo2OiJsb2NhbGUiO3M6MToiKiI7czo0OiJmaWxlIjtzOjU2OiIvdG1wLzI0ZmY1OGM2Y2JiMmI5MDc2NzRlZjJhYmI4NWE4NWRjLWJsYWRlLWNvbXBpbGVkLnBocCI7czo0OiJsaW5lIjtpOjExO30=',
                            ],
                            [
                                'jbboehr\\PHPStanLostInTranslation\\UsedTranslationRecord' => 'Tzo1NDoiamJib2VoclxQSFBTdGFuTG9zdEluVHJhbnNsYXRpb25cVXNlZFRyYW5zbGF0aW9uUmVjb3JkIjo0OntzOjM6ImtleSI7czoxNjoidmlhIGFwcCBmdW5jdGlvbiI7czo2OiJsb2NhbGUiO3M6MToiKiI7czo0OiJmaWxlIjtzOjU2OiIvdG1wLzI0ZmY1OGM2Y2JiMmI5MDc2NzRlZjJhYmI4NWE4NWRjLWJsYWRlLWNvbXBpbGVkLnBocCI7czo0OiJsaW5lIjtpOjE2O30=',
                            ],
                            [
                                'jbboehr\\PHPStanLostInTranslation\\UsedTranslationRecord' => 'Tzo1NDoiamJib2VoclxQSFBTdGFuTG9zdEluVHJhbnNsYXRpb25cVXNlZFRyYW5zbGF0aW9uUmVjb3JkIjo0OntzOjM6ImtleSI7czoxNDoidmlhIGFwcCBmYWNhZGUiO3M6NjoibG9jYWxlIjtzOjE6IioiO3M6NDoiZmlsZSI7czo1NjoiL3RtcC8yNGZmNThjNmNiYjJiOTA3Njc0ZWYyYWJiODVhODVkYy1ibGFkZS1jb21waWxlZC5waHAiO3M6NDoibGluZSI7aToxODt9',
                            ],
                            [
                                'jbboehr\\PHPStanLostInTranslation\\UsedTranslationRecord' => 'Tzo1NDoiamJib2VoclxQSFBTdGFuTG9zdEluVHJhbnNsYXRpb25cVXNlZFRyYW5zbGF0aW9uUmVjb3JkIjo0OntzOjM6ImtleSI7czoyNzoidmlhIGFwcCBmdW5jdGlvbiB3aXRoIGNsYXNzIjtzOjY6ImxvY2FsZSI7czoxOiIqIjtzOjQ6ImZpbGUiO3M6NTY6Ii90bXAvMjRmZjU4YzZjYmIyYjkwNzY3NGVmMmFiYjg1YTg1ZGMtYmxhZGUtY29tcGlsZWQucGhwIjtzOjQ6ImxpbmUiO2k6MjA7fQ==',
                            ],
                            [
                                'jbboehr\\PHPStanLostInTranslation\\UsedTranslationRecord' => 'Tzo1NDoiamJib2VoclxQSFBTdGFuTG9zdEluVHJhbnNsYXRpb25cVXNlZFRyYW5zbGF0aW9uUmVjb3JkIjo0OntzOjM6ImtleSI7czoxODoib25seSB1c2VkIGluIGJsYWRlIjtzOjY6ImxvY2FsZSI7czoxOiIqIjtzOjQ6ImZpbGUiO3M6NTY6Ii90bXAvMjRmZjU4YzZjYmIyYjkwNzY3NGVmMmFiYjg1YTg1ZGMtYmxhZGUtY29tcGlsZWQucGhwIjtzOjQ6ImxpbmUiO2k6MjM7fQ==',
                            ],
                        ],
                        'filePath' => __DIR__ . '/../../e2e/src/blade.php',
                        'collectorType' => 'jbboehr\\PHPStanLostInTranslation\\UnusedTranslationStringCollector',
                    ]),
                    \PHPStan\Collectors\CollectedData::__set_state([
                        'data' => [
                            [
                                'jbboehr\\PHPStanLostInTranslation\\UsedTranslationRecord' => 'Tzo1NDoiamJib2VoclxQSFBTdGFuTG9zdEluVHJhbnNsYXRpb25cVXNlZFRyYW5zbGF0aW9uUmVjb3JkIjo0OntzOjM6ImtleSI7czoyMToiZXhpc3RzIGluIGFsbCBsb2NhbGVzIjtzOjY6ImxvY2FsZSI7czoyOiJlbiI7czo0OiJmaWxlIjtzOjc0OiIvaG9tZS9yaW4vQ29kZS9waHBzdGFuLWxvc3QtaW4tdHJhbnNsYXRpb24vZTJlL3NyYy9pbnZhbGlkLXJlcGxhY2VtZW50LnBocCI7czo0OiJsaW5lIjtpOjQ7fQ==',
                            ],
                        ],
                        'filePath' => __DIR__ . '/../../e2e/src/invalid-replacement.php',
                        'collectorType' => 'jbboehr\\PHPStanLostInTranslation\\UnusedTranslationStringCollector',
                    ]),
                    \PHPStan\Collectors\CollectedData::__set_state([
                        'data' => [
                            [
                                'jbboehr\\PHPStanLostInTranslation\\UsedTranslationRecord' => 'Tzo1NDoiamJib2VoclxQSFBTdGFuTG9zdEluVHJhbnNsYXRpb25cVXNlZFRyYW5zbGF0aW9uUmVjb3JkIjo0OntzOjM6ImtleSI7czo5OiI6Zm9vIDpGT08iO3M6NjoibG9jYWxlIjtzOjI6ImVuIjtzOjQ6ImZpbGUiO3M6NzQ6Ii9ob21lL3Jpbi9Db2RlL3BocHN0YW4tbG9zdC1pbi10cmFuc2xhdGlvbi9lMmUvc3JjL2ludmFsaWQtcmVwbGFjZW1lbnQucGhwIjtzOjQ6ImxpbmUiO2k6Nzt9',
                            ],
                        ],
                        'filePath' => __DIR__ . '/../../e2e/src/invalid-replacement.php',
                        'collectorType' => 'jbboehr\\PHPStanLostInTranslation\\UnusedTranslationStringCollector',
                    ]),
                    \PHPStan\Collectors\CollectedData::__set_state([
                        'data' => [
                            [
                                'jbboehr\\PHPStanLostInTranslation\\UsedTranslationRecord' => 'Tzo1NDoiamJib2VoclxQSFBTdGFuTG9zdEluVHJhbnNsYXRpb25cVXNlZFRyYW5zbGF0aW9uUmVjb3JkIjo0OntzOjM6ImtleSI7czoxMzoibWVzc2FnZXMu8CiMvCI7czo2OiJsb2NhbGUiO3M6MjoiamEiO3M6NDoiZmlsZSI7czo4MjoiL2hvbWUvcmluL0NvZGUvcGhwc3Rhbi1sb3N0LWluLXRyYW5zbGF0aW9uL2UyZS9zcmMvaW52YWxpZC1jaGFyYWN0ZXItZW5jb2RpbmdzLnBocCI7czo0OiJsaW5lIjtpOjM7fQ==',
                            ],
                        ],
                        'filePath' => __DIR__ . '/../../e2e/src/invalid-character-encodings.php',
                        'collectorType' => 'jbboehr\\PHPStanLostInTranslation\\UnusedTranslationStringCollector',
                    ]),
                    \PHPStan\Collectors\CollectedData::__set_state([
                        'data' => [
                            [
                                'jbboehr\\PHPStanLostInTranslation\\UsedTranslationRecord' => 'Tzo1NDoiamJib2VoclxQSFBTdGFuTG9zdEluVHJhbnNsYXRpb25cVXNlZFRyYW5zbGF0aW9uUmVjb3JkIjo0OntzOjM6ImtleSI7czoyNjoibWlzc2luZyB0cmFuc2xhdGlvbiBzdHJpbmciO3M6NjoibG9jYWxlIjtzOjE6IioiO3M6NDoiZmlsZSI7czo4MToiL2hvbWUvcmluL0NvZGUvcGhwc3Rhbi1sb3N0LWluLXRyYW5zbGF0aW9uL2UyZS9zcmMvbWlzc2luZy10cmFuc2xhdGlvbi1zdHJpbmcucGhwIjtzOjQ6ImxpbmUiO2k6Mzt9',
                            ],
                        ],
                        'filePath' => __DIR__ . '/../../e2e/src/missing-translation-string.php',
                        'collectorType' => 'jbboehr\\PHPStanLostInTranslation\\UnusedTranslationStringCollector',
                    ]),
                    \PHPStan\Collectors\CollectedData::__set_state([
                        'data' => [
                            [
                                'jbboehr\\PHPStanLostInTranslation\\UsedTranslationRecord' => 'Tzo1NDoiamJib2VoclxQSFBTdGFuTG9zdEluVHJhbnNsYXRpb25cVXNlZFRyYW5zbGF0aW9uUmVjb3JkIjo0OntzOjM6ImtleSI7czo2OiJmb29iYXIiO3M6NjoibG9jYWxlIjtzOjE0OiJpbnZhbGlkX2xvY2FsZSI7czo0OiJmaWxlIjtzOjY5OiIvaG9tZS9yaW4vQ29kZS9waHBzdGFuLWxvc3QtaW4tdHJhbnNsYXRpb24vZTJlL3NyYy9pbnZhbGlkLWxvY2FsZS5waHAiO3M6NDoibGluZSI7aTozO30=',
                            ],
                        ],
                        'filePath' => __DIR__ . '/../../e2e/src/invalid-locale.php',
                        'collectorType' => 'jbboehr\\PHPStanLostInTranslation\\UnusedTranslationStringCollector',
                    ]),
                ];
                $this->defaultLevelUsed = false;
                $this->projectConfigFile = __DIR__ . '/../../e2e/phpstan-e2e.neon';
                $this->savedResultCache = true;
                $this->peakMemoryUsageBytes = 67633152;
                $this->isResultCacheUsed = true;
                $this->changedProjectExtensionFilesOutsideOfAnalysedPaths = [
                    __DIR__ . '/../../src/ErrorFormatter/JsonErrorFormatter.php' => 'jbboehr\\PHPStanLostInTranslation\\ErrorFormatter\\JsonErrorFormatter',
                ];
                $this->fileSpecificErrors = [
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Unknown locale: fake',
                        'file' => __DIR__ . '/../../e2e/lang/fake.json',
                        'line' => -1,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/lang/fake.json',
                        'traitFilePath' => null,
                        'tip' => null,
                        'nodeLine' => -1,
                        'nodeType' => 'PHPStan\\Node\\CollectedDataNode',
                        'identifier' => 'lostInTranslation.invalidLocale.unknown',
                        'metadata' => [
                            'lostInTranslation::locale' => 'fake',
                        ],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Possibly unused translation string "this string is not used anywhere" for locale: ja',
                        'file' => __DIR__ . '/../../e2e/lang/ja.json',
                        'line' => 2,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/lang/ja.json',
                        'traitFilePath' => null,
                        'tip' => null,
                        'nodeLine' => -1,
                        'nodeType' => 'PHPStan\\Node\\CollectedDataNode',
                        'identifier' => 'lostInTranslation.possiblyUnusedTranslationString',
                        'metadata' => [],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Possibly unused translation string "messages.unused in ja, php" for locale: ja',
                        'file' => __DIR__ . '/../../e2e/lang/ja/messages.php',
                        'line' => 2,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/lang/ja/messages.php',
                        'traitFilePath' => null,
                        'tip' => null,
                        'nodeLine' => -1,
                        'nodeType' => 'PHPStan\\Node\\CollectedDataNode',
                        'identifier' => 'lostInTranslation.possiblyUnusedTranslationString',
                        'metadata' => [],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Invalid character encoding for key: "messages.\\xf0(\\x8c\\xbc"',
                        'file' => __DIR__ . '/../../e2e/lang/ja/messages.php',
                        'line' => 3,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/lang/ja/messages.php',
                        'traitFilePath' => null,
                        'tip' => null,
                        'nodeLine' => -1,
                        'nodeType' => 'PHPStan\\Node\\CollectedDataNode',
                        'identifier' => 'lostInTranslation.invalidCharacterEncoding',
                        'metadata' => [],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Invalid character encoding for value: "\\xf0(\\x8c\\xbc"',
                        'file' => __DIR__ . '/../../e2e/lang/ja/messages.php',
                        'line' => 3,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/lang/ja/messages.php',
                        'traitFilePath' => null,
                        'tip' => null,
                        'nodeLine' => -1,
                        'nodeType' => 'PHPStan\\Node\\CollectedDataNode',
                        'identifier' => 'lostInTranslation.invalidCharacterEncoding',
                        'metadata' => [],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Invalid value: 2',
                        'file' => __DIR__ . '/../../e2e/lang/zh.json',
                        'line' => 2,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/lang/zh.json',
                        'traitFilePath' => null,
                        'tip' => null,
                        'nodeLine' => -1,
                        'nodeType' => 'PHPStan\\Node\\CollectedDataNode',
                        'identifier' => 'lostInTranslation.translationLoaderError',
                        'metadata' => [],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Possibly unused translation string "exists in all localesz" for locale: zh',
                        'file' => __DIR__ . '/../../e2e/lang/zh.json',
                        'line' => 4,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/lang/zh.json',
                        'traitFilePath' => null,
                        'tip' => 'Did you mean "exists in all locales"?',
                        'nodeLine' => -1,
                        'nodeType' => 'PHPStan\\Node\\CollectedDataNode',
                        'identifier' => 'lostInTranslation.possiblyUnusedTranslationString',
                        'metadata' => [],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Invalid value: 3',
                        'file' => __DIR__ . '/../../e2e/lang/zh/messages.php',
                        'line' => 2,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/lang/zh/messages.php',
                        'traitFilePath' => null,
                        'tip' => null,
                        'nodeLine' => -1,
                        'nodeType' => 'PHPStan\\Node\\CollectedDataNode',
                        'identifier' => 'lostInTranslation.translationLoaderError',
                        'metadata' => [],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Missing translation string "blade at directive" for locales: fake, ja, zh',
                        'file' => __DIR__ . '/../../e2e/src/blade.php',
                        'line' => 3,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/src/blade.php',
                        'traitFilePath' => null,
                        'tip' => null,
                        'nodeLine' => 3,
                        'nodeType' => 'PhpParser\\Node\\Expr\\FuncCall',
                        'identifier' => null,
                        'metadata' => [
                            'template_file_path' => 'sample.blade.php',
                            'template_line' => 1,
                        ],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Missing translation string "blade double underscore" for locales: fake, ja, zh',
                        'file' => __DIR__ . '/../../e2e/src/blade.php',
                        'line' => 3,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/src/blade.php',
                        'traitFilePath' => null,
                        'tip' => null,
                        'nodeLine' => 3,
                        'nodeType' => 'PhpParser\\Node\\Expr\\FuncCall',
                        'identifier' => null,
                        'metadata' => [
                            'template_file_path' => 'sample.blade.php',
                            'template_line' => 2,
                        ],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Missing translation string "exists in all locales" for locales: zh',
                        'file' => __DIR__ . '/../../e2e/src/blade.php',
                        'line' => 3,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/src/blade.php',
                        'traitFilePath' => null,
                        'tip' => null,
                        'nodeLine' => 3,
                        'nodeType' => 'PhpParser\\Node\\Expr\\FuncCall',
                        'identifier' => null,
                        'metadata' => [
                            'template_file_path' => 'sample.blade.php',
                            'template_line' => 3,
                        ],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Missing translation string "only in ja" for locales: fake, ja, zh',
                        'file' => __DIR__ . '/../../e2e/src/blade.php',
                        'line' => 3,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/src/blade.php',
                        'traitFilePath' => null,
                        'tip' => null,
                        'nodeLine' => 3,
                        'nodeType' => 'PhpParser\\Node\\Expr\\FuncCall',
                        'identifier' => null,
                        'metadata' => [
                            'template_file_path' => 'sample.blade.php',
                            'template_line' => 4,
                        ],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Missing translation string "via app facade" for locales: fake, ja, zh',
                        'file' => __DIR__ . '/../../e2e/src/blade.php',
                        'line' => 3,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/src/blade.php',
                        'traitFilePath' => null,
                        'tip' => null,
                        'nodeLine' => 3,
                        'nodeType' => 'PhpParser\\Node\\Expr\\FuncCall',
                        'identifier' => null,
                        'metadata' => [
                            'template_file_path' => 'sample.blade.php',
                            'template_line' => 9,
                        ],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Missing translation string "via app function with class" for locales: fake, ja, zh',
                        'file' => __DIR__ . '/../../e2e/src/blade.php',
                        'line' => 3,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/src/blade.php',
                        'traitFilePath' => null,
                        'tip' => null,
                        'nodeLine' => 3,
                        'nodeType' => 'PhpParser\\Node\\Expr\\FuncCall',
                        'identifier' => null,
                        'metadata' => [
                            'template_file_path' => 'sample.blade.php',
                            'template_line' => 10,
                        ],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Missing translation string "via app function" for locales: fake, ja, zh',
                        'file' => __DIR__ . '/../../e2e/src/blade.php',
                        'line' => 3,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/src/blade.php',
                        'traitFilePath' => null,
                        'tip' => null,
                        'nodeLine' => 3,
                        'nodeType' => 'PhpParser\\Node\\Expr\\FuncCall',
                        'identifier' => null,
                        'metadata' => [
                            'template_file_path' => 'sample.blade.php',
                            'template_line' => 8,
                        ],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Disallowed dynamic translation string of type: string',
                        'file' => __DIR__ . '/../../e2e/src/dynamic-translation-string.php',
                        'line' => 5,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/src/dynamic-translation-string.php',
                        'traitFilePath' => null,
                        'tip' => null,
                        'nodeLine' => 5,
                        'nodeType' => 'PhpParser\\Node\\Expr\\MethodCall',
                        'identifier' => 'lostInTranslation.dynamicTranslationString',
                        'metadata' => [],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Disallowed dynamic translation string of type: \'bar\'|\'foo\'|Exception',
                        'file' => __DIR__ . '/../../e2e/src/dynamic-translation-string.php',
                        'line' => 8,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/src/dynamic-translation-string.php',
                        'traitFilePath' => null,
                        'tip' => null,
                        'nodeLine' => 8,
                        'nodeType' => 'PhpParser\\Node\\Expr\\MethodCall',
                        'identifier' => 'lostInTranslation.dynamicTranslationString',
                        'metadata' => [],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Invalid character encoding for key "messages.\\xf0(\\x8c\\xbc"',
                        'file' => __DIR__ . '/../../e2e/src/invalid-character-encodings.php',
                        'line' => 3,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/src/invalid-character-encodings.php',
                        'traitFilePath' => null,
                        'tip' => null,
                        'nodeLine' => 3,
                        'nodeType' => 'PhpParser\\Node\\Expr\\FuncCall',
                        'identifier' => 'lostInTranslation.invalidCharacterEncoding',
                        'metadata' => [
                            'lostInTranslation::key' => 'messages.(',
                        ],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Invalid character encoding for value "messages.\\xf0(\\x8c\\xbc" in locale "ja"',
                        'file' => __DIR__ . '/../../e2e/src/invalid-character-encodings.php',
                        'line' => 3,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/src/invalid-character-encodings.php',
                        'traitFilePath' => null,
                        'tip' => null,
                        'nodeLine' => 3,
                        'nodeType' => 'PhpParser\\Node\\Expr\\FuncCall',
                        'identifier' => 'lostInTranslation.invalidCharacterEncoding',
                        'metadata' => [
                            'lostInTranslation::key' => 'messages.(',
                            'lostInTranslation::locale' => 'ja',
                            'lostInTranslation::value' => '(',
                        ],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Translation choice does not cover all possible cases for number of type: 3',
                        'file' => __DIR__ . '/../../e2e/src/invalid-choice.php',
                        'line' => 3,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/src/invalid-choice.php',
                        'traitFilePath' => null,
                        'tip' => 'Locale: "en", Key: "{0} There are none|{1} There is one|[2] There are :count"',
                        'nodeLine' => 3,
                        'nodeType' => 'PhpParser\\Node\\Expr\\FuncCall',
                        'identifier' => 'lostInTranslation.invalidChoice.missingCase',
                        'metadata' => [
                            'lostInTranslation::key' => '{0} There are none|{1} There is one|[2] There are :count',
                            'lostInTranslation::locale' => 'en',
                            'lostInTranslation::value' => '{0} There are none|{1} There is one|[2] There are :count',
                        ],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Locale has no available translation strings: invalid_locale',
                        'file' => __DIR__ . '/../../e2e/src/invalid-locale.php',
                        'line' => 3,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/src/invalid-locale.php',
                        'traitFilePath' => null,
                        'tip' => null,
                        'nodeLine' => 3,
                        'nodeType' => 'PhpParser\\Node\\Expr\\FuncCall',
                        'identifier' => 'lostInTranslation.invalidLocale.noTranslations',
                        'metadata' => [
                            'lostInTranslation::locale' => 'invalid_locale',
                        ],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Missing translation string "foobar" for locales: invalid_locale',
                        'file' => __DIR__ . '/../../e2e/src/invalid-locale.php',
                        'line' => 3,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/src/invalid-locale.php',
                        'traitFilePath' => null,
                        'tip' => null,
                        'nodeLine' => 3,
                        'nodeType' => 'PhpParser\\Node\\Expr\\FuncCall',
                        'identifier' => 'lostInTranslation.missingTranslationString',
                        'metadata' => [
                            'lostInTranslation::key' => 'foobar',
                            'lostInTranslation::missingInLocales' => [
                                'invalid_locale',
                            ],
                        ],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Unknown locale: invalid_locale',
                        'file' => __DIR__ . '/../../e2e/src/invalid-locale.php',
                        'line' => 3,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/src/invalid-locale.php',
                        'traitFilePath' => null,
                        'tip' => null,
                        'nodeLine' => 3,
                        'nodeType' => 'PhpParser\\Node\\Expr\\FuncCall',
                        'identifier' => 'lostInTranslation.invalidLocale.unknown',
                        'metadata' => [
                            'lostInTranslation::locale' => 'invalid_locale',
                        ],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Unused translation replacement: "bar"',
                        'file' => __DIR__ . '/../../e2e/src/invalid-replacement.php',
                        'line' => 4,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/src/invalid-replacement.php',
                        'traitFilePath' => null,
                        'tip' => 'Locale: "en", Key: "exists in all locales"',
                        'nodeLine' => 4,
                        'nodeType' => 'PhpParser\\Node\\Expr\\FuncCall',
                        'identifier' => 'lostInTranslation.invalidReplacement.unused',
                        'metadata' => [
                            'lostInTranslation::key' => 'exists in all locales',
                            'lostInTranslation::locale' => 'en',
                            'lostInTranslation::value' => 'exists in all locales',
                        ],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Unused translation replacement: "foo"',
                        'file' => __DIR__ . '/../../e2e/src/invalid-replacement.php',
                        'line' => 4,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/src/invalid-replacement.php',
                        'traitFilePath' => null,
                        'tip' => 'Locale: "en", Key: "exists in all locales"',
                        'nodeLine' => 4,
                        'nodeType' => 'PhpParser\\Node\\Expr\\FuncCall',
                        'identifier' => 'lostInTranslation.invalidReplacement.unused',
                        'metadata' => [
                            'lostInTranslation::key' => 'exists in all locales',
                            'lostInTranslation::locale' => 'en',
                            'lostInTranslation::value' => 'exists in all locales',
                        ],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Replacement string matches multiple variants: "foo"',
                        'file' => __DIR__ . '/../../e2e/src/invalid-replacement.php',
                        'line' => 7,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/src/invalid-replacement.php',
                        'traitFilePath' => null,
                        'tip' => 'Locale: "en", Key: ":foo :FOO"',
                        'nodeLine' => 7,
                        'nodeType' => 'PhpParser\\Node\\Expr\\FuncCall',
                        'identifier' => 'lostInTranslation.invalidReplacement.multipleVariants',
                        'metadata' => [
                            'lostInTranslation::key' => ':foo :FOO',
                            'lostInTranslation::locale' => 'en',
                            'lostInTranslation::value' => ':foo :FOO',
                        ],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Likely missing translation string "foo.bar" for base locale: en',
                        'file' => __DIR__ . '/../../e2e/src/missing-translation-string-in-base-locale.php',
                        'line' => 3,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/src/missing-translation-string-in-base-locale.php',
                        'traitFilePath' => null,
                        'tip' => null,
                        'nodeLine' => 3,
                        'nodeType' => 'PhpParser\\Node\\Expr\\FuncCall',
                        'identifier' => 'lostInTranslation.missingBaseLocaleTranslationString',
                        'metadata' => [
                            'lostInTranslation::key' => 'foo.bar',
                            'lostInTranslation::locale' => 'en',
                        ],
                    ]),
                    \PHPStan\Analyser\Error::__set_state([
                        'message' => 'Missing translation string "missing translation string" for locales: fake, ja, zh',
                        'file' => __DIR__ . '/../../e2e/src/missing-translation-string.php',
                        'line' => 3,
                        'canBeIgnored' => true,
                        'filePath' => __DIR__ . '/../../e2e/src/missing-translation-string.php',
                        'traitFilePath' => null,
                        'tip' => null,
                        'nodeLine' => 3,
                        'nodeType' => 'PhpParser\\Node\\Expr\\FuncCall',
                        'identifier' => 'lostInTranslation.missingTranslationString',
                        'metadata' => [
                            'lostInTranslation::key' => 'missing translation string',
                            'lostInTranslation::missingInLocales' => [
                                'fake',
                                'ja',
                                'zh',
                            ],
                        ],
                    ]),
                ];
            })->bindTo($object, \PHPStan\Command\AnalysisResult::class)();

            return $object;
        })();
        // phpcs:enable
    }
}
