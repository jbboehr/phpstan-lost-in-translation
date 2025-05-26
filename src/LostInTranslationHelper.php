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

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ObjectType;

final class LostInTranslationHelper
{
    private ObjectType $translatorType;

    public function __construct(
        private readonly TranslationLoader $translationLoader,
    ) {
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
     * @param TranslationCall $call
     * @return array<string, list<array{string, ?string}>>
     */
    public function gatherPossibleTranslations(TranslationCall $call): array
    {
        if (null !== $call->localeType && count($call->localeType->getConstantStrings()) > 0) {
            $lookInLocales = [];

            foreach ($call->localeType->getConstantStrings() as $localeTypeConstantString) {
                $lookInLocales[] = $localeTypeConstantString->getValue();
            }
        } else {
            $lookInLocales = $this->translationLoader->getFoundLocales();
        }

        $keyConstantStrings = array_map(function (ConstantStringType $constantStringType): string {
            return $constantStringType->getValue();
        }, $call->keyType->getConstantStrings());

        // Make sure they are stably sorted
        sort($keyConstantStrings, SORT_NATURAL);

        $rv = [];

        foreach ($keyConstantStrings as $keyConstantString) {
            foreach ($lookInLocales as $locale) {
                $value = $this->translationLoader->get($locale, $keyConstantString);

                $rv[$keyConstantString][] = [$locale, $value];
            }
        }

        return $rv;
    }

    public function getBaseLocale(): ?string
    {
        return $this->translationLoader->getBaseLocale();
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
     * @note currently the logic is just if it has a group, proboably could be better
     */
    public function isLikelyUntranslated(string $key): bool
    {
        [, $group, $item] = $this->translationLoader->parseKey($key);

        if ($item === null) {
            $group = '*';
        }

        return $group !== '*';
    }
}
