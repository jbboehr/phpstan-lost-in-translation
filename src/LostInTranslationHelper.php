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

namespace jbboehr\PHPStanLostInTranslation;

use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use WeakMap;
use function sort;

/**
 * @final
 * @phpstan-type PossibleTranslationRecord array{string, ?string}
 * @phpstan-type PossibleTranslationRecordCollection array<string, list<PossibleTranslationRecord>>
 */
class LostInTranslationHelper
{
    private ObjectType $translatorType;

    /** @var WeakMap<Scope, WeakMap<Node, TranslationCall|object>> */
    private WeakMap $cache;

    private static object $nullMarker;

    public function __construct(
        private readonly TranslationLoader $translationLoader,
    ) {
        $this->translatorType = new ObjectType(\Illuminate\Contracts\Translation\Translator::class);
        $this->cache = new \WeakMap();

        if (!isset(self::$nullMarker)) {
            self::$nullMarker = new class {
            };
        }
    }

    public function parseCallLike(Node $node, Scope $scope): ?TranslationCall
    {
        /** @var ?\WeakMap<Node, TranslationCall|object> $scopeCache */
        $scopeCache = $this->cache[$scope] ?? null;
        if (null === $scopeCache) {
            $scopeCache = new \WeakMap();
            /** @phpstan-ignore-next-line offsetAssign.valueType */
            $this->cache[$scope] = $scopeCache;
        } else {
            $call = $scopeCache[$node] ?? null;
            if (null !== $call) {
                if ($call === self::$nullMarker) {
                    return null;
                } else {
                    assert($call instanceof TranslationCall);
                    return $call;
                }
            }
        }

        $call = $this->parseCallLikeUncached($node, $scope);

        $scopeCache[$node] = $call ?? self::$nullMarker;

        return $call;
    }

    public function parseCallLikeUncached(Node $node, Scope $scope): ?TranslationCall
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

        $key = self::findArgument($args, 'key', 0);
        $number = $isChoice ? self::findArgument($args, 'number', 1) : null;
        $replace = self::findArgument($args, 'replace', $isChoice ? 2 : 1);
        $locale = self::findArgument($args, 'locale', $isChoice ? 3 : 2);

        if ($key === null) {
            return null;
        }

        $keyType = $scope->getType($key);
        $localeType = $locale !== null ? $scope->getType($locale) : null;
        $file = $scope->getFile();

        assert(strlen($file) > 0);

        return new TranslationCall(
            className: $className,
            functionName: $name,
            file: $file,
            line: $node->getStartLine(),
            possibleTranslations: $this->gatherPossibleTranslations($keyType, $localeType),
            keyType: $keyType,
            replaceType: $replace !== null ? $scope->getType($replace) : null,
            localeType: $localeType,
            numberType: $number !== null ? $scope->getType($number) : null,
            isChoice: $isChoice,
        );
    }

    /**
     * @param array<int, Node\Arg|Node\VariadicPlaceholder> $args
     */
    private static function findArgument(array $args, string $name, int $position): ?Node\Expr
    {
        // PHP forbids positional arguments after named or unpacked arguments,
        // so an unnamed argument's array index is its parameter position.
        foreach ($args as $index => $arg) {
            if (!($arg instanceof Node\Arg) || $arg->unpack) {
                continue;
            }

            if (
                (null !== $arg->name && $name === $arg->name->toString())
                || (null === $arg->name && $position === $index)
            ) {
                return $arg->value;
            }
        }

        return null;
    }

    /**
     * @phpstan-return PossibleTranslationRecordCollection
     */
    private function gatherPossibleTranslations(Type $keyType, ?Type $localeType = null): array
    {
        if (null !== $localeType && count($localeType->getConstantStrings()) > 0) {
            $lookInLocales = [];

            foreach ($localeType->getConstantStrings() as $localeTypeConstantString) {
                $lookInLocales[] = $localeTypeConstantString->getValue();
            }
        } else {
            $lookInLocales = $this->translationLoader->getLocalesForImplicitLookup();
        }

        $keyConstantStrings = array_map(function (ConstantStringType $constantStringType): string {
            return $constantStringType->getValue();
        }, $keyType->getConstantStrings());

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
}
