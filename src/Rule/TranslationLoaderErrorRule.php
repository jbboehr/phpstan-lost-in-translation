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

namespace jbboehr\PHPStanLostInTranslation\Rule;

use jbboehr\PHPStanLostInTranslation\CallRule\InvalidLocaleRule;
use jbboehr\PHPStanLostInTranslation\ShouldNotHappenException;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use jbboehr\PHPStanLostInTranslation\Utils;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<CollectedDataNode>
 */
final class TranslationLoaderErrorRule implements Rule
{
    public function __construct(
        private readonly TranslationLoader $loader,
        private readonly bool $invalidLocales = true,
    ) {
    }

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        try {
            $errors = $this->loader->getErrors();

            if ($this->invalidLocales) {
                foreach ($this->loader->getLocaleFiles() as $locale => $localeFiles) {
                    if (!$this->loader->isValidLocale($locale)) {
                        $file = $localeFiles[0];

                        $errors[] = RuleErrorBuilder::message(sprintf(
                            'Unknown locale: %s',
                            $locale,
                        ))
                            ->identifier(InvalidLocaleRule::IDENTIFIER_UNKNOWN_LOCALE)
                            ->metadata(Utils::metadata(locale: $locale))
                            ->file($file)
                            ->build();
                    }
                }
            }

            return $errors;
        } catch (\Throwable $e) {
            ShouldNotHappenException::rethrow($e);
        }
    }
}
