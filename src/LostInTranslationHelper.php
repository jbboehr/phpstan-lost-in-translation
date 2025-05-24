<?php declare(strict_types=1);

namespace jbboehr\PHPStanLostInTranslation;

use Illuminate\Foundation\Application;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\VariadicPlaceholder;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\PrettyPrinterAbstract;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\VerbosityLevel;

final class LostInTranslationHelper
{
    private readonly PrettyPrinterAbstract $printer;

    private readonly ?string $baseLocale;

    public function __construct(
        private readonly TranslationLoader $translationLoader,
        ?PrettyPrinterAbstract $printer = null,
        private readonly bool $allowDynamicTranslationStrings = true,
        ?string $baseLocale = null,
        private readonly bool $reportLikelyUntranslatedInBaseLocale = true,
    ) {
        $this->printer = $printer ?? new Standard();

        if (null === $baseLocale && class_exists(Application::class, false)) {
            $baseLocale = Application::getInstance()->currentLocale();
        }

        $this->baseLocale = $baseLocale;
    }

    /**
     * @param array<Arg|VariadicPlaceholder> $args
     * @return list<IdentifierRuleError>
     */
    public function processArgs3(array $args, Scope $scope): array
    {
        $key = $locale = null;

        switch (count($args)) {
            case 3:
                if ($args[2] instanceof Arg) {
                    $locale = $args[2]->value;
                }
                // fallthrough
            case 2:
                // fallthrough
            case 1:
                if ($args[0] instanceof Arg) {
                    $key = $args[0]->value;
                }
                // fallthrough
        }

        if (null === $key) {
            return [];
        }

        return $this->process($key, $locale, $scope);
    }

    /**
     * @param array<Arg|VariadicPlaceholder> $args
     * @return list<IdentifierRuleError>
     */
    public function processArgs4(array $args, Scope $scope): array
    {
        $key = $number = $locale = null;

        switch (count($args)) {
            case 4:
                if ($args[3] instanceof Arg) {
                    $locale = $args[3]->value;
                }
                // fallthrough
            case 3:
                // fallthrough
            case 2:
                if ($args[1] instanceof Arg) {
                    $number = $args[1]->value;
                }
                // fallthrough
            case 1:
                if ($args[0] instanceof Arg) {
                    $key = $args[0]->value;
                }
                // fallthrough
        }

        if (null === $key) {
            return [];
        }

        return $this->process($key, $locale, $scope);
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function process(
        Expr $keyExpr,
        ?Expr $localeExpr,
        Scope $scope,
    ): array {
        $keyType = $scope->getType($keyExpr);
        $localeType = $localeExpr !== null ? $scope->getType($localeExpr) : null;
        $errors = [];

        $keyConstantStrings = $keyType->getConstantStrings();

        if (count($keyConstantStrings) <= 0) {
            if (!$this->allowDynamicTranslationStrings) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Disallowed dynamic translation string "%s" of type %s',
                    $this->printer->prettyPrint([$keyExpr]),
                    $keyType->describe(VerbosityLevel::precise())
                ))
                    ->identifier('lostInTranslation.dynamicTranslationString')
                    ->line($keyExpr->getLine())
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
                        ->line($keyExpr->getLine())
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
                    ->line($keyExpr->getLine())
                    ->build();
            }
        }

        return $errors;
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
