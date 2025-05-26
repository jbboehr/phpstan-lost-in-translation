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

use Illuminate\Foundation\Application;
use Illuminate\Support\Str;
use Illuminate\Translation\MessageSelector;
use Illuminate\Translation\Translator;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\IntegerRangeType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VerbosityLevel;

final class LostInTranslationHelper
{
    private readonly ?string $baseLocale;

    private ObjectType $translatorType;

    public function __construct(
        private readonly TranslationLoader $translationLoader,
        ?string $baseLocale = null,
        private readonly bool $reportLikelyUntranslatedInBaseLocale = true,
    ) {
        if (null === $baseLocale && class_exists(Application::class, false)) {
            $baseLocale = Application::getInstance()->currentLocale();
        }

        $this->baseLocale = $baseLocale;
        $this->translatorType = new ObjectType(\Illuminate\Contracts\Translation\Translator::class);
    }

    public function parseCallLike(Node $node, Scope $scope): ?TranslationCall
    {
        if ($node instanceof Node\Expr\MethodCall) {
            if (!($node->name instanceof Node\Identifier)) {
                return null;
            }

            $varType = $scope->getType($node->var);

            if (!$this->translatorType->isSuperTypeOf($varType)->yes()) {
                return null;
            }

            $className = $varType->getObjectClassNames()[0] ?? null; // meh
            $name = $node->name->toLowerString();

            if ($name === 'choice') {
                $isChoice = true;
            } elseif ($name === 'get') {
                $isChoice = false;
            } else {
                return null;
            }

            $args = $node->args;
        } elseif ($node instanceof Node\Expr\StaticCall) {
            if (!($node->name instanceof Node\Identifier) || !($node->class instanceof Node\Name\FullyQualified)) {
                return null;
            }

            $className = $node->class->toString();

            /** @phpstan-ignore-next-line class.notFound */
            if ($className !== \Illuminate\Support\Facades\Lang::class && $className !== \Lang::class) {
                return null;
            }

            $name = $node->name->toLowerString();

            if ($name === 'choice') {
                $isChoice = true;
            } elseif ($name === 'get') {
                $isChoice = false;
            } else {
                return null;
            }

            $args = $node->args;
        } elseif ($node instanceof Node\Expr\FuncCall) {
            if (!$node->name instanceof Node\Name\FullyQualified) {
                return null;
            }

            $className = null;
            $name = $node->name->toLowerString();

            if ($name === '__' || $name === 'trans') {
                $isChoice = false;
            } elseif ($name === 'trans_choice') {
                $isChoice = true;
            } else {
                return null;
            }

            $args = $node->args;
        } else {
            return null;
        }

        $key = $number = $locale = $replace = null;

        if ($isChoice) {
            switch (count($args)) {
                case 4:
                    if ($args[3] instanceof Node\Arg) {
                        $locale = $args[3]->value;
                    }
                    // fallthrough
                case 3:
                    if ($args[2] instanceof Node\Arg) {
                        $replace = $args[2]->value;
                    }
                    // fallthrough
                case 2:
                    if ($args[1] instanceof Node\Arg) {
                        $number = $args[1]->value;
                    }
                    // fallthrough
                case 1:
                    if ($args[0] instanceof Node\Arg) {
                        $key = $args[0]->value;
                    }
                    // fallthrough
            }
        } else {
            switch (count($args)) {
                case 3:
                    if ($args[2] instanceof Node\Arg) {
                        $locale = $args[2]->value;
                    }
                    // fallthrough
                case 2:
                    if ($args[1] instanceof Node\Arg) {
                        $replace = $args[1]->value;
                    }
                    // fallthrough
                case 1:
                    if ($args[0] instanceof Node\Arg) {
                        $key = $args[0]->value;
                    }
                    // fallthrough
            }
        }

        if ($key === null) {
            return null;
        }

        return new TranslationCall(
            className: $className,
            functionName: $name,
            file: $scope->getFile(), // @TODO this might be getting the compiled blade path...
            line: $node->getLine(),
            keyType: $scope->getType($key),
            replaceType: $replace !== null ? $scope->getType($replace) : null,
            localeType: $locale !== null ? $scope->getType($locale) : null,
            numberType: $number !== null ? $scope->getType($number) : null,
            isChoice: $isChoice,
        );
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function process(TranslationCall $call): array
    {
        $keyType = $call->keyType;
        $localeType = $call->localeType;
        $line = $call->line;
        $file = $call->file;
        $metadata = Utils::callToMetadata($call);
        $errors = [];

        $keyConstantStrings = array_map(function (ConstantStringType $constantStringType): string {
            return $constantStringType->getValue();
        }, $keyType->getConstantStrings());

        // Make sure they are stable
        sort($keyConstantStrings, SORT_NATURAL);

        if (count($keyConstantStrings) <= 0) {
            return [];
        }

        foreach ($keyConstantStrings as $keyConstantString) {
            $missingInLocales = [];

            if (null !== $localeType && count($localeType->getConstantStrings()) > 0) {
                $lookInLocales = [];
                foreach ($localeType->getConstantStrings() as $localeTypeConstantString) {
                    $lookInLocales[] = $localeTypeConstantString->getValue();

                    if (!$this->translationLoader->hasLocale($localeTypeConstantString->getValue())) {
                        // @TODO
                    }
                }
            } else {
                $lookInLocales = $this->translationLoader->getFoundLocales();
            }

            foreach ($lookInLocales as $locale) {
                $value = $this->translationLoader->get($locale, $keyConstantString);

                if (null === $value) {
                    $missingInLocales[] = $locale;
                }

                if ($call->replaceType !== null) {
                    $errors = array_merge(
                        $errors,
                        $this->analyzeReplacements($call, $locale, $keyConstantString, $value ?? $keyConstantString),
                    );
                }

                if ($call->isChoice) {
                    $errors = array_merge(
                        $errors,
                        $this->analyzeChoice($call, $locale, $keyConstantString, $value ?? $keyConstantString),
                    );
                }
            }

            if (null !== $this->baseLocale) {
                $missingInLocales = array_diff($missingInLocales, [$this->baseLocale]);

                if ($this->reportLikelyUntranslatedInBaseLocale && $this->isLikelyUntranslated($keyConstantString)) {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        'Likely missing translation string %s for base locale: %s',
                        json_encode($keyConstantString, JSON_THROW_ON_ERROR),
                        $this->baseLocale
                    ))
                        ->identifier('lostInTranslation.missingBaseLocaleTranslationString')
                        ->metadata($metadata)
                        ->line($line)
                        ->file($file)
                        ->build();
                }
            }

            if (count($missingInLocales) > 0) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Missing translation string %s for locales: %s',
                    json_encode($keyConstantString, JSON_THROW_ON_ERROR),
                    join(', ', $missingInLocales)
                ))
                    ->identifier('lostInTranslation.missingTranslationString')
                    ->metadata($metadata)
                    ->line($line)
                    ->file($file)
                    ->build();
            }
        }

        return $errors;
    }

    public function markUsed(TranslationCall $call): void
    {
        if (null !== $call->localeType && count($call->localeType->getConstantStrings()) > 0) {
            $locales = array_map(fn ($t) => $t->getValue(), $call->localeType->getConstantStrings());
        } else {
            $locales = ['*'];
        }

        foreach ($call->keyType->getConstantStrings() as $constantString) {
            foreach ($locales as $locale) {
                $this->translationLoader->markUsed($locale, $constantString->getValue());
            }
        }
    }

    /**
     * @return list<array{string, string, string}>
     */
    public function diffUsed(): array
    {
        return $this->translationLoader->diffUsed();
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

    /**
     * @return list<IdentifierRuleError>
     * @see MessageSelector::choose()
     */
    private function analyzeChoice(TranslationCall $call, string $locale, string $key, string $value): array
    {
        $numberType = $call->numberType;

        if (null === $numberType) {
            return [];
        }

        $segments = explode('|', $value);
        $errors = [];
        $unionType = null;

        foreach ($segments as $segment) {
            if (false === preg_match('/^[\{\[]([^\[\]\{\}]*)[\}\]](.*)/s', $segment, $matches, PREG_UNMATCHED_AS_NULL)) {
                $errors[] = RuleErrorBuilder::message(sprintf('Failed to parse translation choice'))
                    ->identifier('lostInTranslation.malformedTranslationChoice')
                    ->metadata(Utils::callToMetadata($call, ['lit::locale' => $locale, 'lit::key' => $key, 'lit::value' => $value]))
                    ->addTip(Utils::formatTipForKeyValue($locale, $key, $value))
                    ->line($call->line)
                    ->file($call->file)
                    ->build();
                continue;
            }

            /** not sure why this is failing */
            /** @phpstan-ignore-next-line smaller.alwaysFalse */
            if (count($matches) < 2) {
                // this is probably a normal translation string - we could raise an error but :shrug:
                continue;
            }

            [, $condition] = $matches;

            if (str_contains($condition, ',')) {
                [$from, $to] = explode(',', $condition, 2);
            } else {
                $from = $to = $condition;
            }

            if (!is_numeric($from) && $from !== '*') {
                $errors[] = RuleErrorBuilder::message(sprintf('Translation choice has non-numeric value: %s', Utils::e($from)))
                    ->identifier('lostInTranslation.nonNumericChoice')
                    ->metadata(Utils::callToMetadata($call, ['lit::locale' => $locale, 'lit::key' => $key, 'lit::value' => $value]))
                    ->addTip(Utils::formatTipForKeyValue($locale, $key, $value))
                    ->line($call->line)
                    ->file($call->file)
                    ->build();
                continue;
            } elseif (!is_numeric($to) && $to !== '*') {
                $errors[] = RuleErrorBuilder::message(sprintf('Translation choice has non-numeric value: %s', Utils::e($to)))
                    ->identifier('lostInTranslation.nonNumericChoice')
                    ->metadata(Utils::callToMetadata($call, ['lit::locale' => $locale, 'lit::key' => $key, 'lit::value' => $value]))
                    ->addTip(Utils::formatTipForKeyValue($locale, $key, $value))
                    ->line($call->line)
                    ->file($call->file)
                    ->build();
                continue;
            }

            if ($from === '*' && $to === '*') {
                continue;
            }

            // @TODO might want to add an option to ignore negative numbers, probably will cause a lot of false? positives
            // if ($from === '0') {
            //     $from = '*';
            // }

            if ($from === '*') {
                $segmentType = IntegerRangeType::fromInterval(null, (int) $to);
            } elseif ($to === '*') {
                $segmentType = IntegerRangeType::fromInterval((int) $from, null);
            } elseif ($from === $to) {
                $segmentType = new ConstantIntegerType((int) $from);
            } else {
                $segmentType = IntegerRangeType::fromInterval((int) $from, (int) $to);
            }

            if (null === $unionType) {
                $unionType = $segmentType;
            } else {
                $unionType = TypeCombinator::union($unionType, $segmentType);
            }
        }

        if (null !== $unionType && !$unionType->accepts($numberType, true)->yes()) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Translation choice does not cover all possible cases for number of type: %s',
                $numberType->describe(VerbosityLevel::precise())
            ))
                ->identifier('lostInTranslation.choiceMissingCase')
                ->metadata(Utils::callToMetadata($call, ['lit::locale' => $locale, 'lit::key' => $key, 'lit::value' => $value]))
                ->addTip(Utils::formatTipForKeyValue($locale, $key, $value))
                ->line($call->line)
                ->file($call->file)
                ->build();
        }

        return $errors;
    }

    /**
     * @note currently the logic is just if it has a group, proboably could be better
     */
    private function isLikelyUntranslated(string $key): bool
    {
        [, $group, $item] = $this->translationLoader->parseKey($key);

        if ($item === null) {
            $group = '*';
        }

        return $group !== '*';
    }
}
