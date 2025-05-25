<?php declare(strict_types=1);

namespace jbboehr\PHPStanLostInTranslation\Tests;

use jbboehr\PHPStanLostInTranslation\LostInTranslationHelper;
use jbboehr\PHPStanLostInTranslation\LostInTranslationRule;
use jbboehr\PHPStanLostInTranslation\LostInTranslationCollector;
use jbboehr\PHPStanLostInTranslation\TranslationLoader;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase as BaseRuleTestCase;

/**
 * @template T of Rule
 * @extends BaseRuleTestCase<T>
 */
abstract class RuleTestCase extends BaseRuleTestCase
{
    protected ?LostInTranslationHelper $lostInTranslationHelper = null;

    public function createLostInTranslationHelper(): LostInTranslationHelper
    {
        return new LostInTranslationHelper(
            new TranslationLoader(__DIR__ . '/lang'),
            allowDynamicTranslationStrings: true,
            baseLocale: 'en',
            reportLikelyUntranslatedInBaseLocale: true,
        );
    }

    public function getLostInTranslationHelper(): LostInTranslationHelper
    {
        if (null === $this->lostInTranslationHelper) {
            $this->lostInTranslationHelper = $this->createLostInTranslationHelper();
        }

        return $this->lostInTranslationHelper;
    }

    public function createLostInTranslationRule(): LostInTranslationRule
    {
        return new LostInTranslationRule($this->getLostInTranslationHelper());
    }

    public function getCollectors(): array
    {
        return [
            new LostInTranslationCollector($this->getLostInTranslationHelper()),
        ];
    }
}
