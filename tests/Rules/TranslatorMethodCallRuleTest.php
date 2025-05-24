<?php declare(strict_types=1);

namespace jbboehr\PHPStanLostInTranslation\Tests\Rules;

use jbboehr\PHPStanLostInTranslation\LostInTranslationHelper;
use jbboehr\PHPStanLostInTranslation\Rules\TranslatorMethodCallRule;
use jbboehr\PHPStanLostInTranslation\TranslationLoader;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\PrettyPrinterAbstract;
use PHPStan\Node\Printer\Printer;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<TranslatorMethodCallRule>
 */
class TranslatorMethodCallRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new TranslatorMethodCallRule(
            new LostInTranslationHelper(
                new TranslationLoader(__DIR__ . '/../lang'),
                new Standard(),
                false,
            )
        );
    }

    public function testMethods(): void
    {
        $this->analyse([
            __DIR__ . '/data/translator.php',
        ], [
            [
                'Missing translation string "contract basic" for locales: zh, en, ja',
                4,
            ],

            [
                'Missing translation string "translator basic" for locales: zh, en, ja',
                7,
            ],
            [
                'Missing translation string "translator basic" for locales: zh, en, ja',
                8,
            ],
            [
                'Disallowed dynamic translation string "$dynamic" of type string',
                11,
            ],
            [
                'Missing translation string "foo" for locales: zh, en, ja',
                14,
            ],
            [
                'Missing translation string "bar" for locales: zh, en, ja',
                14,
            ],
            [
                "Disallowed dynamic translation string \"\$craycray\" of type 'bar'|'foo'|Exception",
                17,
            ],
        ]);
    }
}
