<?php declare(strict_types=1);

namespace jbboehr\PHPStanLostInTranslation\Rules;

use jbboehr\PHPStanLostInTranslation\LostInTranslationHelper;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<Node\Expr\FuncCall>
 */
final class TransFuncCallRule implements Rule
{
    public function __construct(
        private readonly LostInTranslationHelper $lostInTranslationHelper
    ) {
    }

    public function getNodeType(): string
    {
        return Node\Expr\FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        /** @phpstan-ignore-next-line function.alreadyNarrowedType */
        assert($node instanceof Node\Expr\FuncCall);

        if (!$node->name instanceof Node\Name\FullyQualified) {
            return [];
        }

        $name = $node->name->toLowerString();
        if ($name !== '__' && $name !== 'trans') {
            return [];
        }

        return $this->lostInTranslationHelper->processArgs3($node->args, $scope);
    }
}
