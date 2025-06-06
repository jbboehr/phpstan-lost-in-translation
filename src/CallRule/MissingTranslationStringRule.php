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

final class MissingTranslationStringRule implements CallRuleInterface
{
    public const IDENTIFIER = 'lostInTranslation.missingTranslationString';

    public function __construct(
        private readonly TranslationLoader $loader,
    ) {
    }

    public function processCall(TranslationCall $call): array
    {
        $errors = [];
        $baseLocale = $this->loader->getBaseLocale();

        foreach ($call->possibleTranslations as $key => $items) {
            $missingInLocales = [];

            foreach ($items as [$locale, $value]) {
                if (null === $value && $locale !== $baseLocale) {
                    $missingInLocales[] = $locale;
                }
            }

            if (count($missingInLocales) > 0) {
                $builder = RuleErrorBuilder::message(sprintf(
                    'Missing translation string %s for locales: %s',
                    Utils::e($key),
                    join(', ', $missingInLocales),
                ))
                    ->identifier(self::IDENTIFIER)
                    ->metadata(Utils::metadata(key: $key, missingInLocales: $missingInLocales))
                    ->line($call->line)
                    ->file($call->file);

                if (strlen($key) > 0) {
                    $similarKey = $this->loader->searchForSimilarKeys($key);

                    if (null !== $similarKey) {
                        $builder->addTip(sprintf("Did you mean this similar key: %s", Utils::e($similarKey)));
                    }
                }

                $errors[] = $builder->build();
            }
        }

        return $errors;
    }
}
