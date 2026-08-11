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

namespace jbboehr\PHPStanLostInTranslation\ErrorFormatter;

use jbboehr\PHPStanLostInTranslation\CallRule\InvalidCharacterEncodingRule;
use jbboehr\PHPStanLostInTranslation\CallRule\MissingTranslationStringInBaseLocaleRule;
use jbboehr\PHPStanLostInTranslation\CallRule\MissingTranslationStringRule;
use jbboehr\PHPStanLostInTranslation\Identifier;
use jbboehr\PHPStanLostInTranslation\ShouldNotHappenException;
use jbboehr\PHPStanLostInTranslation\Utils;
use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\Output;

/**
 * @phpstan-type MissingType array<string, array<string, null>>
 * @phpstan-import-type MetadataType from Identifier
 */
final class JsonErrorFormatter implements ErrorFormatter
{
    private readonly \Closure $criticalLogger;

    /**
     * @param bool $pretty
     * @phpstan-param \Closure(string): void $criticalLogger
     */
    public function __construct(
        private readonly bool $pretty = true,
        ?\Closure $criticalLogger = null,
    ) {
        $this->criticalLogger = $criticalLogger ?? static function (string $message) {
            error_log($message);
        };
    }

    public function formatErrors(AnalysisResult $analysisResult, Output $output): int
    {
        try {
            $missing = [
                MissingTranslationStringRule::IDENTIFIER => [],
                MissingTranslationStringInBaseLocaleRule::IDENTIFIER => [],
            ];
            $other = [];

            foreach ($analysisResult->getFileSpecificErrors() as $fileSpecificError) {
                $id = $fileSpecificError->getIdentifier();

                if (null === $id || !str_starts_with($id, 'lostInTranslation.')) {
                    continue;
                }

                /** @phpstan-var MetadataType $metadata */
                $metadata = $fileSpecificError->getMetadata();
                $key = $metadata[Identifier::METADATA_KEY] ?? null;
                $locale = $metadata[Identifier::METADATA_LOCALE] ?? '*';

                if (null === $key) {
                    continue;
                }

                switch ($id) {
                    case MissingTranslationStringRule::IDENTIFIER:
                        /** @var list<string> $missingInLocales */
                        $missingInLocales = $metadata[Identifier::METADATA_MISSING_IN_LOCALES] ?? [];

                        foreach ($missingInLocales as $missingInLocale) {
                            $missing[$id][$missingInLocale][$key] = null;
                        };
                        break;

                    case MissingTranslationStringInBaseLocaleRule::IDENTIFIER:
                        $missing[$id][$locale][$key] = null;
                        break;

                    case InvalidCharacterEncodingRule::IDENTIFIER:
                        $other[$id][] = substr(Utils::e($key), 1, -1);
                        break;

                    default:
                        $other[$id][] = $key;
                        break;
                }
            }

            $json = json_encode(
                array_merge($missing, $other),
                \JSON_THROW_ON_ERROR
                    | \JSON_UNESCAPED_SLASHES
                    | \JSON_UNESCAPED_UNICODE
                    | \JSON_PRESERVE_ZERO_FRACTION
                    | ($this->pretty ? \JSON_PRETTY_PRINT : 0),
            );
            $output->writeRaw($json);

            return $analysisResult->hasErrors() ? 1 : 0;
        } catch (\Throwable $e) {
            // Seems to silence exceptions?
            ($this->criticalLogger)((string) $e);
            ShouldNotHappenException::rethrow($e);
        }
    }
}
