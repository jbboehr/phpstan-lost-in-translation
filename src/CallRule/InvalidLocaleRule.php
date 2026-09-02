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

use jbboehr\PHPStanLostInTranslation\Identifier;
use jbboehr\PHPStanLostInTranslation\TranslationCall;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use jbboehr\PHPStanLostInTranslation\Utils;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @internal
 */
final class InvalidLocaleRule implements CallRuleInterface
{
    public const IDENTIFIER_NO_TRANSLATIONS = Identifier::INVALID_LOCALE_NO_TRANSLATIONS;
    public const IDENTIFIER_UNKNOWN_LOCALE = Identifier::INVALID_LOCALE_UNKNOWN;

    public function __construct(
        private readonly TranslationLoader $loader,
    ) {
    }

    public function processCall(TranslationCall $call): array
    {
        if ([] === $call->explicitLocales) {
            return [];
        }

        $errors = [];

        foreach ($call->explicitLocales as $locale) {
            if (!$this->loader->hasLocale($locale)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Locale has no available translation strings: %s',
                    $locale,
                ))
                    ->identifier(self::IDENTIFIER_NO_TRANSLATIONS)
                    ->metadata(Utils::metadata(locale: $locale))
                    ->line($call->line)
                    ->file($call->file)
                    ->build();
            }

            if (!$this->loader->isValidLocale($locale)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Unknown locale: %s',
                    $locale,
                ))
                    ->identifier(self::IDENTIFIER_UNKNOWN_LOCALE)
                    ->metadata(Utils::metadata(locale: $locale))
                    ->line($call->line)
                    ->file($call->file)
                    ->build();
            }
        }

        return $errors;
    }
}
