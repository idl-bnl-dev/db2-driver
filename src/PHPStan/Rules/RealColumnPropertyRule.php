<?php

namespace IDL\DB2Driver\PHPStan\Rules;

use IDL\DB2Driver\PHPStan\ColumnAliasMap;
use PhpParser\Node;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Optional rule (rules.neon): reading or writing a real column on a HasColumnAliases model must go
 * through its alias.
 *
 * @implements Rule<PropertyFetch>
 */
final class RealColumnPropertyRule implements Rule
{
    public function getNodeType(): string
    {
        return PropertyFetch::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier) {
            return [];
        }

        $errors = [];

        foreach ($scope->getType($node->var)->getObjectClassReflections() as $classReflection) {
            $alias = array_search($node->name->name, ColumnAliasMap::aliasesOf($classReflection), true);

            if ($alias !== false) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Use the alias "%s" instead of the real column "%s" on %s.',
                    $alias,
                    $node->name->name,
                    $classReflection->getDisplayName()
                ))->identifier('db2.realColumnProperty')->build();
            }
        }

        return $errors;
    }
}
