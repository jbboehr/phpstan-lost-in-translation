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

namespace jbboehr\PHPStanLostInTranslation\CallRule;

use jbboehr\PHPStanLostInTranslation\TranslationCall;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use jbboehr\PHPStanLostInTranslation\Utils;
use PHPStan\Rules\RuleErrorBuilder;

final class MissingTranslationStringInBaseLocaleRule implements CallRuleInterface
{
    public const IDENTIFIER = 'lostInTranslation.missingBaseLocaleTranslationString';

    public function __construct(
        private readonly TranslationLoader $loader,
    ) {
    }

    public function processCall(TranslationCall $call): array
    {
        $errors = [];
        $baseLocale = $this->loader->getBaseLocale();

        foreach ($call->possibleTranslations as $key => $items) {
            foreach ($items as [$locale, $value]) {
                if ($locale === $baseLocale && null === $value && self::isLikelyUntranslated($key)) {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        'Likely missing translation string %s for base locale: %s',
                        json_encode($key, JSON_THROW_ON_ERROR),
                        $baseLocale,
                    ))
                        ->identifier(self::IDENTIFIER)
                        ->metadata(Utils::metadata(key: $key, locale: $locale))
                        ->line($call->line)
                        ->file($call->file)
                        ->build();
                }
            }
        }

        return $errors;
    }

    private const GROUP_REGEX = '~^(.+::)?((?:[\w][\w\d]*)(?:[_-](?:[\w][\w\d]*))*)(?:\.((?:[\w][\w\d]*)(?:[_-](?:[\w][\w\d]*))*))$~';

    private static function isLikelyUntranslated(string $key): bool
    {
        return 1 === preg_match(self::GROUP_REGEX, $key);
    }
}
