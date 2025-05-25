<?php declare(strict_types=1);

namespace jbboehr\PHPStanLostInTranslation\Tests;

use jbboehr\PHPStanLostInTranslation\LostInTranslationHelper;
use jbboehr\PHPStanLostInTranslation\LostInTranslationRule;
use jbboehr\PHPStanLostInTranslation\LostInTranslationCollector;
use jbboehr\PHPStanLostInTranslation\TranslationLoader;
use jbboehr\PHPStanLostInTranslation\Utils;
use PHPStan\Rules\Rule;

/**
 * @extends RuleTestCase<LostInTranslationRule>
 */
class LostInTranslationRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return $this->createLostInTranslationRule();
    }

    public function testLanguageFacade(): void
    {
        $this->analyse([
            __DIR__ . '/data/lang-facade.php',
        ], [
            [
                'Missing translation string "lang facade" for locales: zh, ja',
                3,
            ],
        ]);
    }

    public function testTransChoiceFunction(): void
    {
        $this->analyse([
            __DIR__ . '/data/trans-choice-function.php',
        ], [
            [
                'Missing translation string "trans choice function" for locales: zh, ja',
                3,
            ],
        ]);
    }

    public function testTransFunction(): void
    {
        $this->analyse([
            __DIR__ . '/data/trans-function.php',
        ], [
            [
                'Missing translation string "double underscore" for locales: zh, ja',
                3,
            ],
            [
                'Missing translation string "trans function" for locales: zh, ja',
                4,
            ],
        ]);
    }

    public function testTranslatorMethod(): void
    {
        $this->analyse([
            __DIR__ . '/data/translator.php',
        ], [
            [
                'Missing translation string "contract basic" for locales: zh, ja',
                4,
            ],

            [
                'Missing translation string "translator basic" for locales: zh, ja',
                7,
            ],
            [
                'Missing translation string "translator basic" for locales: zh, ja',
                8,
            ],
            [
                'Missing translation string "bar" for locales: zh, ja',
                14,
            ],
            [
                'Missing translation string "foo" for locales: zh, ja',
                14,
            ],
            [
                "Likely missing translation string \"messages.in_ja_and_zh\" for base locale: en",
                19
            ],
        ]);
    }

    public function testMalformedReplacement(): void
    {
        $this->analyse([
            __DIR__ . '/data/malformed-replacement.php',
        ], [
            [
                'Unused translation replacement: "bar"',
                4,
                Utils::formatTipForKeyValue('en', 'exists in all locales', 'exists in all locales'),
            ],
            [
                'Unused translation replacement: "foo"',
                4,
                Utils::formatTipForKeyValue('en', 'exists in all locales', 'exists in all locales'),
            ],
            [
                'Replacement string matches multiple variants: "foo"',
                7,
                Utils::formatTipForKeyValue('en', ':foo :FOO', ':foo :FOO'),
            ]
        ]);
    }
}
