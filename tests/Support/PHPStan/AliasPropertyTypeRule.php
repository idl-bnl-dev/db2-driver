<?php

namespace IDL\DB2Driver\Tests\Support\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\PropertyFetch;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Test helper: reports the type PHPStan infers for every property fetch, so the property
 * extension can be exercised in-process (and therefore show up in coverage).
 *
 * @implements Rule<PropertyFetch>
 */
final class AliasPropertyTypeRule implements Rule
{
    public function getNodeType(): string
    {
        return PropertyFetch::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        return [RuleErrorBuilder::message($scope->getType($node)->describe(\PHPStan\Type\VerbosityLevel::precise()))->identifier('test.type')->build()];
    }
}
