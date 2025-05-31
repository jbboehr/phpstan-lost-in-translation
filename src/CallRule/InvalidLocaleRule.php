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

final class InvalidLocaleRule implements CallRuleInterface
{
    public function __construct(
        private readonly TranslationLoader $loader,
        private readonly bool $strictLocales = false,
    ) {
    }

    public function processCall(TranslationCall $call): array
    {
        if (null === $call->localeType) {
            return [];
        }

        $localeType = $call->localeType;
        $errors = [];

        foreach ($localeType->getConstantStrings() as $localeConstantString) {
            $locale = $localeConstantString->getValue();

            if (!$this->loader->hasLocale($locale)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Locale has no available translation strings: %s',
                    $locale,
                ))
                    ->identifier('lostInTranslation.noLocaleTranslations')
                    ->metadata(Utils::callToMetadata($call, ['lit::locale' => $locale]))
                    ->line($call->line)
                    ->file($call->file)
                    ->build();
            }

            if (!Utils::checkLocaleExists($locale, $this->strictLocales)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Unknown locale: %s',
                    $locale,
                ))
                    ->identifier('lostInTranslation.unknownLocale')
                    ->metadata(Utils::callToMetadata($call, ['lit::locale' => $locale]))
                    ->line($call->line)
                    ->file($call->file)
                    ->build();
            }
        }

        return $errors;
    }
}
