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

namespace jbboehr\PHPStanLostInTranslation\Tests;

use jbboehr\PHPStanLostInTranslation\Identifier;

final class PublicApiContractTest extends \PHPUnit\Framework\TestCase
{
    private const PUBLIC_TYPES = [
        Identifier::class,
    ];

    public function testIdentifierExposesTheStableDiagnosticCatalogue(): void
    {
        $constants = (new \ReflectionClass(Identifier::class))->getConstants();
        $diagnosticIdentifiers = array_filter(
            $constants,
            static fn (mixed $value): bool => is_string($value) && str_starts_with($value, 'lostInTranslation.'),
        );

        self::assertSame([
            'DYNAMIC_TRANSLATION_STRING' => 'lostInTranslation.dynamicTranslationString',
            'INVALID_CHARACTER_ENCODING' => 'lostInTranslation.invalidCharacterEncoding',
            'INVALID_CHOICE_MALFORMED' => 'lostInTranslation.invalidChoice.malformed',
            'INVALID_CHOICE_MISSING_CASE' => 'lostInTranslation.invalidChoice.missingCase',
            'INVALID_CHOICE_MISSING_PLURAL_FORM' => 'lostInTranslation.invalidChoice.missingPluralForm',
            'INVALID_CHOICE_NON_NUMERIC' => 'lostInTranslation.invalidChoice.nonNumeric',
            'INVALID_LOCALE_NO_TRANSLATIONS' => 'lostInTranslation.invalidLocale.noTranslations',
            'INVALID_LOCALE_UNKNOWN' => 'lostInTranslation.invalidLocale.unknown',
            'INVALID_REPLACEMENT_MULTIPLE_VARIANTS' => 'lostInTranslation.invalidReplacement.multipleVariants',
            'INVALID_REPLACEMENT_UNUSED' => 'lostInTranslation.invalidReplacement.unused',
            'MISSING_BASE_LOCALE_TRANSLATION_STRING' => 'lostInTranslation.missingBaseLocaleTranslationString',
            'MISSING_TRANSLATION_STRING' => 'lostInTranslation.missingTranslationString',
            'POSSIBLY_UNUSED_TRANSLATION_STRING' => 'lostInTranslation.possiblyUnusedTranslationString',
            'TRANSLATION_LOADER_ERROR' => 'lostInTranslation.translationLoaderError',
            'TRANSLATION_LOADER_ERROR_CONFLICTING_KEY' => 'lostInTranslation.translationLoaderError.conflictingKey',
            'TRANSLATION_LOADER_ERROR_CONFLICTING_LOCALE' => 'lostInTranslation.translationLoaderError.conflictingLocale',
        ], $diagnosticIdentifiers);
    }

    public function testEverySourceTypeDeclaresItsCompatibilityStatus(): void
    {
        $sourceDirectory = new \RecursiveDirectoryIterator(__DIR__ . '/../src');
        $files = new \RecursiveIteratorIterator($sourceDirectory);
        $violations = [];

        foreach ($files as $file) {
            if (!($file instanceof \SplFileInfo) || !$file->isFile() || 'php' !== $file->getExtension()) {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen(__DIR__ . '/../src/'));
            $type = 'jbboehr\\PHPStanLostInTranslation\\'
                . str_replace('/', '\\', substr($relativePath, 0, -4));

            if (!class_exists($type) && !interface_exists($type) && !trait_exists($type) && !enum_exists($type)) {
                $violations[] = sprintf('%s is not autoloadable from its PSR-4 path', $type);
                continue;
            }

            $docComment = (new \ReflectionClass($type))->getDocComment() ?: '';
            $isApi = str_contains($docComment, '@api');
            $isInternal = str_contains($docComment, '@internal');

            if ($isApi === $isInternal) {
                $violations[] = sprintf('%s must declare exactly one of @api or @internal', $type);
            } elseif (in_array($type, self::PUBLIC_TYPES, true) !== $isApi) {
                $violations[] = sprintf('%s declares the wrong compatibility status', $type);
            }
        }

        self::assertSame([], $violations);
    }
}
