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

namespace jbboehr\PHPStanLostInTranslation\Tests\Blade;

use Composer\InstalledVersions;
use jbboehr\PHPStanLostInTranslation\Blade\BladeDiagnosticCollector;
use jbboehr\PHPStanLostInTranslation\Blade\BladeDiagnosticRule;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\CollectedData;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\FileRuleError;
use PHPStan\Rules\LineRuleError;
use PHPStan\Rules\MetadataRuleError;
use PHPStan\Rules\TipRuleError;

final class BladeDiagnosticRuleTest extends \PHPUnit\Framework\TestCase
{
    public function testRebuildsCollectedDiagnosticsWithoutLosingContext(): void
    {
        $diagnostics = [
            [
                'message' => 'Unused translation replacement: "unused"',
                'identifier' => 'lostInTranslation.invalidReplacement.unused',
                'metadata' => [
                    'lostInTranslation::key' => 'messages.example',
                    'template_file_path' => 'resources/views/example.blade.php',
                    'template_line' => 19,
                ],
                'tip' => 'Locale: "en", Key: "messages.example", Value: "Example"',
                'file' => __FILE__,
                'line' => 215,
            ],
            [
                'message' => 'Missing translation string: "messages.missing"',
                'identifier' => 'lostInTranslation.missingTranslationString',
                'metadata' => [
                    'lostInTranslation::key' => 'messages.missing',
                    'template_file_path' => 'resources/views/other.blade.php',
                    'template_line' => 27,
                ],
                'tip' => null,
                'file' => __FILE__,
                'line' => 216,
            ],
        ];

        $phpStanVersionRange = InstalledVersions::getVersionRanges('phpstan/phpstan');

        if (str_starts_with($phpStanVersionRange, '1.')) {
            /** @phpstan-ignore phpstanApi.constructor */
            $collectedData = [new CollectedData($diagnostics, __FILE__, BladeDiagnosticCollector::class)];
        } else {
            $collectedData = [
                __FILE__ => [
                    BladeDiagnosticCollector::class => [$diagnostics],
                ],
            ];
        }

        // CollectedDataNode is internal, and PHPStan 1 and 2 assign different
        // shapes to its otherwise untyped constructor array.
        $node = (new \ReflectionClass(CollectedDataNode::class))->newInstance($collectedData, false);
        $errors = (new BladeDiagnosticRule())->processNode($node, $this->createStub(Scope::class));

        $this->assertCount(2, $errors);
        $error = $errors[0];
        $this->assertSame('lostInTranslation.invalidReplacement.unused', $error->getIdentifier());
        $this->assertSame('Unused translation replacement: "unused"', $error->getMessage());
        $this->assertInstanceOf(MetadataRuleError::class, $error);
        $this->assertSame([
            'lostInTranslation::key' => 'messages.example',
            'template_file_path' => 'resources/views/example.blade.php',
            'template_line' => 19,
        ], $error->getMetadata());
        $this->assertInstanceOf(TipRuleError::class, $error);
        $this->assertSame('Locale: "en", Key: "messages.example", Value: "Example"', $error->getTip());
        $this->assertInstanceOf(FileRuleError::class, $error);
        $this->assertSame(__FILE__, $error->getFile());
        $this->assertInstanceOf(LineRuleError::class, $error);
        $this->assertSame(215, $error->getLine());

        $secondError = $errors[1];
        $this->assertSame('lostInTranslation.missingTranslationString', $secondError->getIdentifier());
        $this->assertSame('Missing translation string: "messages.missing"', $secondError->getMessage());
        $this->assertNotInstanceOf(TipRuleError::class, $secondError);
        $this->assertInstanceOf(LineRuleError::class, $secondError);
        $this->assertSame(216, $secondError->getLine());
    }
}
