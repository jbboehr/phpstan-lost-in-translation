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
                if ($this->loader->isBaseLocale($locale) && null === $value && self::isLikelyUntranslated($key)) {
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

    private const GROUP_REGEX = '~^(.+::)?(?:[\w][\w\d]*)(?:[_-](?:[\w][\w\d]*))*(?:\.(?:[\w][\w\d]*)(?:[_-](?:[\w][\w\d]*))*)+$~';

    public static function isLikelyUntranslated(string $key): bool
    {
        return 1 === preg_match(self::GROUP_REGEX, $key);
    }
}
