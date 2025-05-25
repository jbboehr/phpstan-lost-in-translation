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
 * @implements Rule<CollectedDataNode>
 */
final class PossiblyUnusedTranslationRule implements Rule
{
    public function __construct(
        private readonly LostInTranslationHelper $helper,
        private readonly bool $reportPossiblyUnusedTranslations = false,
    ) {
    }

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        /** @TODO we could probably do this by unregistered in the phpstan config */
        if (!$this->reportPossiblyUnusedTranslations) {
            return [];
        }

        /** @var array<string, list<TranslationCall>> $data */
        $data = $node->get(LostInTranslationCollector::class);

        $errors = [];

        foreach ($data as $results) {
            foreach ($results as $result) {
                $this->helper->markUsed($result);
            }
        }

        $possiblyUnused = $this->helper->diffUsed();

        foreach ($possiblyUnused as $item) {
            [$locale, $key] = $item;

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Possibly unused translation string %s for locale: %s',
                json_encode($key, JSON_THROW_ON_ERROR),
                join(', ', [$locale])
            ))
                ->identifier('lostInTranslation.possiblyUnusedTranslationString')
                ->build();
        }

        return $errors;
    }
}
