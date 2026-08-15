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

final class ConfigurationReferenceTest extends \PHPUnit\Framework\TestCase
{
    private const REFERENCE_START = '<!-- configuration-reference:start -->';

    private const REFERENCE_END = '<!-- configuration-reference:end -->';

    public function testConfigurationReferenceMatchesSchemaAndDefaults(): void
    {
        $projectRoot = dirname(__DIR__, 3);
        $extensionLines = file($projectRoot . '/extension.neon', FILE_IGNORE_NEW_LINES);
        $readmeLines = file($projectRoot . '/README.md', FILE_IGNORE_NEW_LINES);

        self::assertIsArray($extensionLines);
        self::assertIsArray($readmeLines);

        $schemaKeys = self::nestedKeys(
            $extensionLines,
            'parametersSchema:',
            '    lostInTranslation: structure([',
        );
        $defaultKeys = self::nestedKeys(
            $extensionLines,
            'parameters:',
            '    lostInTranslation:',
        );

        $referenceStart = array_search(self::REFERENCE_START, $readmeLines, true);
        $referenceEnd = array_search(self::REFERENCE_END, $readmeLines, true);

        self::assertIsInt($referenceStart, 'README configuration reference start marker is missing');
        self::assertIsInt($referenceEnd, 'README configuration reference end marker is missing');
        self::assertGreaterThan($referenceStart, $referenceEnd);

        $documentedKeys = self::nestedKeys(
            array_slice($readmeLines, $referenceStart + 1, $referenceEnd - $referenceStart - 1),
            'parameters:',
            '    lostInTranslation:',
        );

        sort($schemaKeys, SORT_STRING);
        sort($defaultKeys, SORT_STRING);
        sort($documentedKeys, SORT_STRING);

        self::assertSame($schemaKeys, $defaultKeys, 'Configuration schema and default keys differ');
        self::assertSame($schemaKeys, $documentedKeys, 'Public configuration reference is incomplete or stale');
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private static function nestedKeys(array $lines, string $section, string $opening): array
    {
        $sectionIndex = array_search($section, $lines, true);

        self::assertIsInt($sectionIndex, sprintf('Configuration section %s is missing', $section));
        self::assertSame($opening, $lines[$sectionIndex + 1] ?? null);

        $keys = [];

        for ($index = $sectionIndex + 2; isset($lines[$index]); ++$index) {
            $line = $lines[$index];

            if (1 === preg_match('/^ {8}([A-Za-z][A-Za-z0-9]*):/', $line, $matches)) {
                $keys[] = $matches[1];
                continue;
            }

            if ('' !== trim($line) && !str_starts_with($line, '        ')) {
                break;
            }
        }

        self::assertNotSame([], $keys, sprintf('Configuration section %s has no keys', $section));
        self::assertSame($keys, array_values(array_unique($keys)), sprintf(
            'Configuration section %s contains duplicate keys',
            $section,
        ));

        return $keys;
    }
}
