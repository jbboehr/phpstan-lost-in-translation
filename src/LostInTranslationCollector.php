<?php declare(strict_types=1);

namespace jbboehr\PHPStanLostInTranslation;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Type\ObjectType;

/**
 * @implements Collector<Node\Expr\CallLike, TranslationCall>
 */
final class LostInTranslationCollector implements Collector
{
    public function __construct(
        private readonly LostInTranslationHelper $helper,
        private readonly bool $reportPossiblyUnusedTranslations = false,
    ) {
    }

    public function getNodeType(): string
    {
        return Node\Expr\CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): ?TranslationCall
    {
        try {
            /** @TODO we could probably do this by unregistered in the phpstan config */
            if (!$this->reportPossiblyUnusedTranslations) {
                return null;
            }

            return $this->helper->parseCallLike($node, $scope);
        } catch (\Throwable $e) {
            ShouldNotHappenException::rethrow($e);
        }
    }
}
