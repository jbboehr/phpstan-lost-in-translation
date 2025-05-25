<?php declare(strict_types=1);

namespace jbboehr\PHPStanLostInTranslation\Tests;

use jbboehr\PHPStanLostInTranslation\LostInTranslationHelper;
use jbboehr\PHPStanLostInTranslation\LostInTranslationRule;
use jbboehr\PHPStanLostInTranslation\LostInTranslationCollector;
use jbboehr\PHPStanLostInTranslation\PossiblyUnusedTranslationRule;
use jbboehr\PHPStanLostInTranslation\TranslationLoader;
use PHPStan\Rules\Rule;

/**
 * @extends RuleTestCase<PossiblyUnusedTranslationRule>
 */
class PossiblyUnusedTranslationRuleTest extends RuleTestCase
{
    public function createLostInTranslationHelper(): LostInTranslationHelper
    {
        return new LostInTranslationHelper(
            new TranslationLoader(__DIR__ . '/lang-unused'),
            allowDynamicTranslationStrings: true,
            baseLocale: 'en',
            reportLikelyUntranslatedInBaseLocale: true,
        );
    }

    protected function getRule(): Rule
    {
        return new PossiblyUnusedTranslationRule(
            $this->getLostInTranslationHelper(),
            reportPossiblyUnusedTranslations: true,
        );
    }

    public function testPossiblyUnusedTranslations(): void
    {
        $this->analyse([
            __DIR__ . '/data/possibly-unused.php',
        ], [
            [
                'Possibly unused translation string "unused_in_en" for locale: en',
                -1
            ],
            [
                'Possibly unused translation string "unused_in_ja" for locale: ja',
                -1
            ],
            [
                'Possibly unused translation string "used_in_en" for locale: ja',
                -1
            ],
        ]);
    }

    public function getCollectors(): array
    {
        return [
            new LostInTranslationCollector(
                $this->getLostInTranslationHelper(),
                reportPossiblyUnusedTranslations: true,
            ),
        ];
    }
}
