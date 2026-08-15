<?php
/**
 * Copyright (c) anno Domini nostri Jesu Christi MMXXVI John Boehr & contributors
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

namespace jbboehr\PHPStanLostInTranslation\DocumentationTests;

use jbboehr\Akashi\Source\MarkdownSource;
use jbboehr\PHPStanLostInTranslation\CallRule\CallRuleCollection;
use jbboehr\PHPStanLostInTranslation\CallRule\DynamicTranslationStringRule;
use jbboehr\PHPStanLostInTranslation\CallRule\InvalidCharacterEncodingRule;
use jbboehr\PHPStanLostInTranslation\CallRule\InvalidChoiceRule;
use jbboehr\PHPStanLostInTranslation\CallRule\InvalidLocaleRule;
use jbboehr\PHPStanLostInTranslation\CallRule\InvalidReplacementRule;
use jbboehr\PHPStanLostInTranslation\CallRule\MissingTranslationStringInBaseLocaleRule;
use jbboehr\PHPStanLostInTranslation\CallRule\MissingTranslationStringRule;
use jbboehr\PHPStanLostInTranslation\LostInTranslationHelper;
use jbboehr\PHPStanLostInTranslation\Rule\LostInTranslationRule;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<LostInTranslationRule>
 */
final class DocumentationExamplesTest extends RuleTestCase
{
    private const EXPECTED_DIAGNOSTICS = [
        'dynamic-translation' => [
            'lostInTranslation.dynamicTranslationString@5',
            'lostInTranslation.dynamicTranslationString@8',
        ],
        'invalid-character-encoding-call' => [
            'lostInTranslation.invalidCharacterEncoding@3',
            'lostInTranslation.invalidCharacterEncoding@3',
        ],
        'invalid-choice' => [
            'lostInTranslation.invalidChoice.missingCase@3',
        ],
        'invalid-locale' => [
            'lostInTranslation.invalidLocale.noTranslations@3',
            'lostInTranslation.invalidLocale.unknown@3',
            'lostInTranslation.missingTranslationString@3',
        ],
        'invalid-replacements' => [
            'lostInTranslation.invalidReplacement.multipleVariants@7',
            'lostInTranslation.invalidReplacement.unused@4',
            'lostInTranslation.invalidReplacement.unused@4',
        ],
        'missing-base-translation' => [
            'lostInTranslation.missingBaseLocaleTranslationString@3',
        ],
        'missing-translation' => [
            'lostInTranslation.missingTranslationString@3',
        ],
    ];

    private ?TranslationLoader $translationLoader = null;

    protected function getRule(): Rule
    {
        $loader = $this->getTranslationLoader();

        return new LostInTranslationRule(
            new LostInTranslationHelper(
                $loader,
                self::getContainer()->getByType(ReflectionProvider::class),
            ),
            CallRuleCollection::createFromArray([
                new DynamicTranslationStringRule(),
                new InvalidCharacterEncodingRule(),
                new InvalidChoiceRule(translationLoader: $loader),
                new InvalidLocaleRule($loader),
                new InvalidReplacementRule(),
                new MissingTranslationStringInBaseLocaleRule($loader),
                new MissingTranslationStringRule($loader),
            ]),
        );
    }

    public function testMarkedDocumentationExamplesMatchDocumentedDiagnostics(): void
    {
        $projectRoot = dirname(__DIR__, 3);
        $corpus = MarkdownSource::forProject($projectRoot)
            ->includeFile('README.md')
            ->includeDirectory('docs/usage')
            ->withMarkerName('akashi-example')
            ->load();
        $temporaryDirectory = sys_get_temp_dir()
            . '/phpstan-lost-in-translation-akashi-'
            . bin2hex(random_bytes(8));

        self::assertTrue(mkdir($temporaryDirectory, 0700));

        $createdFiles = [];
        $foundMarkers = [];

        try {
            foreach ($corpus as $example) {
                $marker = $example->explicitMarkerId?->value;
                if (null === $marker) {
                    continue;
                }

                self::assertArrayHasKey($marker, self::EXPECTED_DIAGNOSTICS, sprintf(
                    'Documentation marker %s has no diagnostic expectation',
                    $marker,
                ));

                $foundMarkers[] = $marker;
                $path = $temporaryDirectory . '/' . $marker . '.php';
                self::assertSame(
                    strlen($example->code->source),
                    file_put_contents($path, $example->code->source, LOCK_EX),
                );
                $createdFiles[] = $path;

                $extensionDiagnostics = [];
                $unexpectedDiagnostics = [];

                foreach ($this->gatherAnalyserErrors([$path]) as $error) {
                    $identifier = $error->getIdentifier();

                    if (null !== $identifier && str_starts_with($identifier, 'lostInTranslation.')) {
                        $extensionDiagnostics[] = sprintf(
                            '%s@%s',
                            $identifier,
                            $error->getLine() ?? 'unknown',
                        );
                    } else {
                        $unexpectedDiagnostics[] = sprintf(
                            '%s: %s',
                            $identifier ?? '(no identifier)',
                            $error->getMessage(),
                        );
                    }
                }

                self::assertSame([], $unexpectedDiagnostics, sprintf(
                    'Documentation example %s at line %d produced unrelated PHPStan diagnostics',
                    $marker,
                    $example->codeOrigin()->firstCodeLine,
                ));

                $expectedDiagnostics = self::EXPECTED_DIAGNOSTICS[$marker];
                sort($expectedDiagnostics, SORT_STRING);
                sort($extensionDiagnostics, SORT_STRING);

                self::assertSame($expectedDiagnostics, $extensionDiagnostics, sprintf(
                    'Documentation example %s at line %d produced different extension diagnostics',
                    $marker,
                    $example->codeOrigin()->firstCodeLine,
                ));
            }
        } finally {
            foreach ($createdFiles as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }

            if (is_dir($temporaryDirectory)) {
                rmdir($temporaryDirectory);
            }
        }

        sort($foundMarkers, SORT_STRING);
        $expectedMarkers = array_keys(self::EXPECTED_DIAGNOSTICS);
        sort($expectedMarkers, SORT_STRING);

        self::assertSame($expectedMarkers, $foundMarkers);
    }

    private function getTranslationLoader(): TranslationLoader
    {
        return $this->translationLoader ??= new TranslationLoader(
            langPath: __DIR__ . '/../lang',
            baseLocale: 'en',
            fuzzySearch: false,
        );
    }
}
