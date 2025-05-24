<?php declare(strict_types=1);

namespace jbboehr\PHPStanLostInTranslation\Tests\Rules;

use jbboehr\PHPStanLostInTranslation\LostInTranslationHelper;
use jbboehr\PHPStanLostInTranslation\Rules\TransFuncCallRule;
use jbboehr\PHPStanLostInTranslation\TranslationLoader;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\PrettyPrinterAbstract;
use PHPStan\Node\Printer\Printer;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<TransFuncCallRule>
 */
class TransFuncCallRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new TransFuncCallRule(
            new LostInTranslationHelper(
                new TranslationLoader(__DIR__ . '/../lang'),
                new Standard(),
                true,
            )
        );
    }

    public function testMethods(): void
    {
        $this->analyse([
            __DIR__ . '/data/trans-function.php',
        ], [
            [
                'Missing translation string "double underscore" for locales: zh, en, ja',
                3,
            ],
            [
                'Missing translation string "trans function" for locales: zh, en, ja',
                4,
            ],
        ]);
    }
}
