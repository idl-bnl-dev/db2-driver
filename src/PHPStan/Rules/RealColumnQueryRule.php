<?php

namespace IDL\DB2Driver\PHPStan\Rules;

use IDL\DB2Driver\PHPStan\ColumnAliasMap;
use Illuminate\Database\Eloquent\Builder;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * Optional rule (rules.neon): a query on a HasColumnAliases model must name a column through its
 * alias (whereAlias(), orderByAlias(), selectAliased()), not as a literal real column.
 *
 * @implements Rule<CallLike>
 */
final class RealColumnQueryRule implements Rule
{
    private const METHODS = [
        'where', 'orwhere', 'wherein', 'wherenotin', 'wherenull', 'wherenotnull', 'wherebetween',
        'orderby', 'orderbydesc', 'groupby', 'value', 'pluck',
    ];

    public function __construct(private ReflectionProvider $reflectionProvider) {}

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! ($node instanceof MethodCall || $node instanceof StaticCall)
            || ! $node->name instanceof Identifier
            || ! in_array(strtolower($node->name->name), self::METHODS, true)
            || ! isset($node->getArgs()[0])) {
            return [];
        }

        $errors = [];

        foreach ($this->modelsOf($node, $scope) as $model) {
            $aliases = array_flip(ColumnAliasMap::aliasesOf($model));

            foreach ($scope->getType($node->getArgs()[0]->value)->getConstantStrings() as $column) {
                if (isset($aliases[$column->getValue()])) {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        'Use the alias "%s" (whereAlias(), orderByAlias(), selectAliased()) instead of the real column "%s" on %s.',
                        $aliases[$column->getValue()],
                        $column->getValue(),
                        $model->getDisplayName()
                    ))->identifier('db2.realColumnQuery')->build();
                }
            }
        }

        return $errors;
    }

    /** @return list<ClassReflection> */
    private function modelsOf(MethodCall|StaticCall $node, Scope $scope): array
    {
        if ($node instanceof StaticCall) {
            if (! $node->class instanceof Name) {
                return [];
            }

            $class = $scope->resolveName($node->class);

            return $this->reflectionProvider->hasClass($class) ? [$this->reflectionProvider->getClass($class)] : [];
        }

        $type = $scope->getType($node->var);

        if (! (new ObjectType(Builder::class))->isSuperTypeOf($type)->yes()) {
            return [];
        }

        return $this->classesOf($type->getTemplateType(Builder::class, 'TModel'));
    }

    /** @return list<ClassReflection> */
    private function classesOf(Type $type): array
    {
        return $type->getObjectClassReflections();
    }
}
