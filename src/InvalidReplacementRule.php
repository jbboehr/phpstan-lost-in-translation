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

namespace jbboehr\PHPStanLostInTranslation;

use Illuminate\Support\Str;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node\Expr\CallLike>
 */
final class InvalidReplacementRule implements Rule
{
    public function __construct(
        private readonly LostInTranslationHelper $helper,
    ) {
    }

    public function getNodeType(): string
    {
        return Node\Expr\CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        try {
            $call = $this->helper->parseCallLike($node, $scope);

            if (null === $call) {
                return [];
            }

            $errors = [];

            foreach ($call->possibleTranslations as $key => $items) {
                foreach ($items as [$locale, $value]) {
                    $errors = array_merge(
                        $errors,
                        $this->analyzeReplacements($call, $locale, $key, $value ?? $key),
                    );
                }
            }

            return $errors;
        } catch (\Throwable $e) {
            ShouldNotHappenException::rethrow($e);
        }
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function analyzeReplacements(TranslationCall $call, string $locale, string $key, string $value): array
    {
        if (null === $call->replaceType) {
            return [];
        }

        /** @see Translator::makeReplacements() */
        $errors = [];

        $replaceKeys = [];
        foreach ($call->replaceType->getConstantArrays() as $constantArray) {
            foreach ($constantArray->getKeyType()->getConstantStrings() as $constantString) {
                $replaceKeys[] = $constantString->getValue();
            }
        }

        // Make sure they are stably sorted
        sort($replaceKeys, SORT_NATURAL);

        foreach ($replaceKeys as $search) {
            $replaceVariantCount = (int) str_contains($value, ':' . Str::ucfirst($search))
                + (int) str_contains($value, ':' . Str::upper($search))
                + (int) str_contains($value, ':' . $search);

            if ($replaceVariantCount === 0) {
                $errors[] = RuleErrorBuilder::message(sprintf('Unused translation replacement: %s', Utils::e($search)))
                    ->identifier('lostInTranslation.unusedReplacement')
                    ->metadata(Utils::callToMetadata($call, ['lit::locale' => $locale, 'lit::key' => $key, 'lit::value' => $value]))
                    ->addTip(Utils::formatTipForKeyValue($locale, $key, $value))
                    ->line($call->line)
                    ->file($call->file)
                    ->build();
            } elseif ($replaceVariantCount > 1) {
                $errors[] = RuleErrorBuilder::message(sprintf('Replacement string matches multiple variants: %s', Utils::e($search)))
                    ->identifier('lostInTranslation.multipleReplaceVariants')
                    ->metadata(Utils::callToMetadata($call, ['lit::locale' => $locale, 'lit::key' => $key, 'lit::value' => $value]))
                    ->addTip(Utils::formatTipForKeyValue($locale, $key, $value))
                    ->line($call->line)
                    ->file($call->file)
                    ->build();
            }
        }

        return $errors;
    }
}
