<?php declare(strict_types=1);

namespace jbboehr\PHPStanLostInTranslation;

use Illuminate\Foundation\Application;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\VerbosityLevel;

final class LostInTranslationHelper
{
    private readonly ?string $baseLocale;

    private ObjectType $translatorType;

    public function __construct(
        private readonly TranslationLoader $translationLoader,
        private readonly bool $allowDynamicTranslationStrings = true,
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

        $key = $number = $locale = null;

        if ($isChoice) {
            switch (count($args)) {
                case 4:
                    if ($args[3] instanceof Node\Arg) {
                        $locale = $args[3]->value;
                    }
                    // fallthrough
                case 3:
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
            localeType: $locale !== null ? $scope->getType($locale) : null,
            isChoice: $isChoice,
        );
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function process(TranslationCall $result): array
    {
        $keyType = $result->keyType;
        $localeType = $result->localeType;
        $line = $result->line;
        $file = $result->file;
        $errors = [];

        $keyConstantStrings = $keyType->getConstantStrings();

        if (count($keyConstantStrings) <= 0) {
            if (!$this->allowDynamicTranslationStrings) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Disallowed dynamic translation string of type: %s',
                    $keyType->describe(VerbosityLevel::precise())
                ))
                    ->identifier('lostInTranslation.dynamicTranslationString')
                    ->line($line)
                    ->file($file)
                    ->build();
            }

            return $errors;
        }

        foreach ($keyConstantStrings as $keyConstantString) {
            $missingInLocales = [];

            foreach ($this->translationLoader->getFoundLocales() as $locale) {
                if (!$this->translationLoader->has($locale, $keyConstantString->getValue())) {
                    $missingInLocales[] = $locale;
                }
            }

            if (null !== $this->baseLocale) {
                $missingInLocales = array_diff($missingInLocales, [$this->baseLocale]);

                if ($this->reportLikelyUntranslatedInBaseLocale && $this->isLikelyUntranslated($keyConstantString->getValue())) {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        'Likely missing translation string %s for base locale: %s',
                        json_encode($keyConstantString->getValue(), JSON_THROW_ON_ERROR),
                        $this->baseLocale
                    ))
                        ->identifier('lostInTranslation.missingBaseLocaleTranslationString')
                        ->line($line)
                        ->file($file)
                        ->build();
                }
            }

            if (count($missingInLocales) > 0) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Missing translation string %s for locales: %s',
                    json_encode($keyConstantString->getValue(), JSON_THROW_ON_ERROR),
                    join(', ', $missingInLocales)
                ))
                    ->identifier('lostInTranslation.missingTranslationString')
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
     * @return list<array{string, string}>
     */
    public function diffUsed(): array
    {
        return $this->translationLoader->diffUsed();
    }

    /**
     * @note currently the logic is just if it has a group, proboably could be better
     */
    private function isLikelyUntranslated(string $key): bool
    {
        [$namespace, $group, $item] = $this->translationLoader->parseKey($key);

        if ($item === null) {
            $item = $group;
            $group = '*';
        }

        return $group !== '*';
    }
}
