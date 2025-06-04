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

namespace jbboehr\PHPStanLostInTranslation\ErrorFormatter;

use jbboehr\PHPStanLostInTranslation\CallRule\InvalidCharacterEncodingRule;
use jbboehr\PHPStanLostInTranslation\CallRule\MissingTranslationStringInBaseLocaleRule;
use jbboehr\PHPStanLostInTranslation\CallRule\MissingTranslationStringRule;
use jbboehr\PHPStanLostInTranslation\Identifier;
use jbboehr\PHPStanLostInTranslation\ShouldNotHappenException;
use jbboehr\PHPStanLostInTranslation\Utils;
use Nette\Utils\Json;
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

            $json = Json::encode(array_merge($missing, $other), $this->pretty ? Json::PRETTY : 0);
            $output->writeRaw($json);

            return $analysisResult->hasErrors() ? 1 : 0;
        } catch (\Throwable $e) {
            // Seems to silence exceptions?
            ($this->criticalLogger)((string) $e);
            ShouldNotHappenException::rethrow($e);
        }
    }
}
