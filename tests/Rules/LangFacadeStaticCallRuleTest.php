<?php declare(strict_types=1);

namespace jbboehr\PHPStanLostInTranslation\Tests\Rules;

use jbboehr\PHPStanLostInTranslation\LostInTranslationHelper;
use jbboehr\PHPStanLostInTranslation\Rules\LangFacadeStaticCallRule;
use jbboehr\PHPStanLostInTranslation\TranslationLoader;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\PrettyPrinterAbstract;
use PHPStan\Node\Printer\Printer;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<LangFacadeStaticCallRule>
 */
class LangFacadeStaticCallRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new LangFacadeStaticCallRule(
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
            __DIR__ . '/data/lang-facade.php',
        ], [
            [
                'Missing translation string "lang facade" for locales: zh, ja',
                3,
            ],
        ]);
    }
}
