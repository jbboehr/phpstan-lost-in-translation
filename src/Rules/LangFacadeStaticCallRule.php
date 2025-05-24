<?php declare(strict_types=1);

namespace jbboehr\PHPStanLostInTranslation\Rules;

use jbboehr\PHPStanLostInTranslation\LostInTranslationHelper;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<Node\Expr\StaticCall>
 */
final class LangFacadeStaticCallRule implements Rule
{
    public function __construct(
        private readonly LostInTranslationHelper $lostInTranslationHelper
    ) {
    }

    public function getNodeType(): string
    {
        return Node\Expr\StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        /** @phpstan-ignore-next-line function.alreadyNarrowedType */
        assert($node instanceof Node\Expr\StaticCall);

        if (!$node->name instanceof Node\Identifier || !$node->class instanceof Node\Name\FullyQualified) {
            return [];
        }

        $className = $node->class->toString();
        $methodName = $node->name->toString();

        /** @phpstan-ignore-next-line class.notFound */
        if ($className !== \Illuminate\Support\Facades\Lang::class && $className !== \Lang::class) {
            return [];
        }

        return $this->lostInTranslationHelper->processArgs3($node->args, $scope);
    }
}
