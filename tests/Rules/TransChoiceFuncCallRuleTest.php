<?php declare(strict_types=1);

namespace jbboehr\PHPStanLostInTranslation\Tests\Rules;

use jbboehr\PHPStanLostInTranslation\LostInTranslationHelper;
use jbboehr\PHPStanLostInTranslation\Rules\TransChoiceFuncCallRule;
use jbboehr\PHPStanLostInTranslation\TranslationLoader;
use PhpParser\PrettyPrinterAbstract;
use PHPStan\Node\Printer\Printer;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<TransChoiceFuncCallRule>
 */
class TransChoiceFuncCallRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new TransChoiceFuncCallRule(
            new LostInTranslationHelper(
                new TranslationLoader(__DIR__ . '/../lang'),
                $this->getContainer()->getByType(PrettyPrinterAbstract::class),
                true,
            )
        );
    }

    public function testMethods(): void
    {
        $this->analyse([
            __DIR__ . '/data/trans-choice-function.php',
        ], [
            [
                'Missing translation string "trans choice function" for locales: zh, en, ja',
                3,
            ],
        ]);
    }
}
