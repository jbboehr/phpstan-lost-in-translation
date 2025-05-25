<?php declare(strict_types=1);

namespace jbboehr\PHPStanLostInTranslation\Tests;

use jbboehr\PHPStanLostInTranslation\LostInTranslationHelper;
use jbboehr\PHPStanLostInTranslation\LostInTranslationRule;
use jbboehr\PHPStanLostInTranslation\TranslationLoader;
use PHPStan\Rules\Rule;

/**
 * @extends RuleTestCase<LostInTranslationRule>
 */
class DynamicTranslationStringTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return $this->createLostInTranslationRule();
    }

    public function createLostInTranslationHelper(): LostInTranslationHelper
    {
        return new LostInTranslationHelper(
            new TranslationLoader(__DIR__ . '/lang'),
            allowDynamicTranslationStrings: false,
            baseLocale: 'en',
            reportLikelyUntranslatedInBaseLocale: true,
        );
    }

    public function testDynamicTranslationString(): void
    {
        $this->analyse([
            __DIR__ . '/data/dynamic.php',
        ], [
            [
                'Disallowed dynamic translation string of type: string',
                5,
            ],
            [
                "Disallowed dynamic translation string of type: 'bar'|'foo'|Exception",
                8,
            ],
        ]);
    }
}
