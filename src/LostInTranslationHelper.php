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

        $keyType = $scope->getType($key);
        $localeType = $locale !== null ? $scope->getType($locale) : null;
        $file = $scope->getFile(); // @TODO this might be getting the compiled blade path...

        assert(strlen($file) > 0);

        return new TranslationCall(
            className: $className,
            functionName: $name,
            file: $file,
            line: $node->getLine(),
            possibleTranslations: $this->gatherPossibleTranslations($keyType, $localeType),
            keyType: $keyType,
            replaceType: $replace !== null ? $scope->getType($replace) : null,
            localeType: $localeType,
            numberType: $number !== null ? $scope->getType($number) : null,
            isChoice: $isChoice,
        );
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
            $lookInLocales = $this->translationLoader->getFoundLocales();
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
