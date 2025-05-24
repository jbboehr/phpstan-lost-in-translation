<?php declare(strict_types=1);

namespace jbboehr\PHPStanLostInTranslation\Tests\Blade;

use Illuminate\View\FileViewFinder;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use TomasVotruba\Bladestan\Rules\BladeRule;

/**
 * @extends RuleTestCase<BladeRule>
 */
class BladeRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return $this->getContainer()->getByType(BladeRule::class);
    }

    public function testMethods(): void
    {
        if (!\Composer\InstalledVersions::isInstalled('tomasvotruba/bladestan')) {
            self::markTestSkipped('This test requires Bladestan');
        }

        $this->getContainer()->getByType(FileViewFinder::class)
            ->addLocation(__DIR__ . '/resources/views');

        $this->analyse([
            __DIR__ . '/data/sample.php',
        ], [
            [
                'Missing translation string "blade at directive" for locales: zh, en, ja',
                3,
            ],
            [
                'Missing translation string "blade double underscore" for locales: zh, en, ja',
                3,
            ],
            [
                'Missing translation string "only in ja" for locales: zh, en',
                3,
            ],
        ]);
    }

    public static function getAdditionalConfigFiles(): array
    {
         return array_merge(parent::getAdditionalConfigFiles(), [
             __DIR__ . '/config.neon',
         ]);
    }
}
