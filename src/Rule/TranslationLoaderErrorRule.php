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
        private readonly bool $strictLocales = false,
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
                    if (!Utils::checkLocaleExists($locale, $this->strictLocales)) {
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
