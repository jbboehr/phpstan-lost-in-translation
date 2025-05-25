<?php declare(strict_types=1);

namespace jbboehr\PHPStanLostInTranslation;

use Illuminate\Foundation\Application;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\VerbosityLevel;

/**
 * @implements Rule<CollectedDataNode|Node\Expr\CallLike>
 */
final class LostInTranslationRule implements Rule
{
    public function __construct(
        private readonly LostInTranslationHelper $helper,
        private readonly bool $useCollector = false,
    ) {
    }

    public function getNodeType(): string
    {
        if ($this->useCollector) {
            return CollectedDataNode::class;
        } else {
            return Node\Expr\CallLike::class;
        }
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($node instanceof CollectedDataNode) {
            /** @var array<string, list<TranslationCall>> $data */
            $data = $node->get(LostInTranslationCollector::class);

            $errors = [];

            foreach ($data as $results) {
                foreach ($results as $result) {
                    $errors = array_merge(
                        $errors,
                        $this->helper->process($result)
                    );
                }
            }

            return $errors;
        } else {
            $result = $this->helper->parseCallLike($node, $scope);

            if (null === $result) {
                return [];
            }

            return $this->helper->process($result);
        }
    }
}
