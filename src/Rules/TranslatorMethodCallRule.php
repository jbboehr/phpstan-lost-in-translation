<?php declare(strict_types=1);

namespace jbboehr\PHPStanLostInTranslation\Rules;

use jbboehr\PHPStanLostInTranslation\LostInTranslationHelper;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Type\ObjectType;

/**
 * @implements Rule<Node\Expr\MethodCall>
 */
final class TranslatorMethodCallRule implements Rule
{
    private ObjectType $translator_type;

    public function __construct(
        private readonly LostInTranslationHelper $lostInTranslationHelper
    ) {
        $this->translator_type = new ObjectType(\Illuminate\Contracts\Translation\Translator::class);
    }

    public function getNodeType(): string
    {
        return Node\Expr\MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        /** @phpstan-ignore-next-line function.alreadyNarrowedType */
        assert($node instanceof Node\Expr\MethodCall);

        if (!$node->name instanceof Node\Identifier || $node->name->toLowerString() !== 'get' || count($node->args) <= 0) {
            return [];
        }

        if (!$this->translator_type->isSuperTypeOf($scope->getType($node->var))->yes()) {
            return [];
        }

        return $this->lostInTranslationHelper->processArgs3($node->args, $scope);
    }
}
