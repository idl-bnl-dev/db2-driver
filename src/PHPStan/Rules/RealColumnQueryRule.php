<?php

namespace IDL\DB2Driver\PHPStan\Rules;

use IDL\DB2Driver\PHPStan\ColumnAliasMap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
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
    /** Methods whose first argument is a column, or an array keyed by column. */
    private const METHODS = [
        'where', 'orwhere', 'wherenot', 'orwherenot', 'wherein', 'orwherein', 'wherenotin', 'orwherenotin',
        'wherenull', 'orwherenull', 'wherenotnull', 'orwherenotnull',
        'wherebetween', 'orwherebetween', 'wherenotbetween', 'orwherenotbetween',
        'wherelike', 'orwherelike', 'wherenotlike', 'orwherenotlike',
        'orderby', 'orderbydesc', 'groupby', 'value', 'pluck',
        'sum', 'avg', 'min', 'max', 'count',
    ];

    /** Methods that take columns as arguments or in arrays: every argument is checked. */
    private const SELECTS = ['select', 'addselect'];

    public function __construct(private ReflectionProvider $reflectionProvider) {}

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! ($node instanceof MethodCall || $node instanceof StaticCall)
            || ! $node->name instanceof Identifier
            || ! isset($node->getArgs()[0])) {
            return [];
        }

        $method = strtolower($node->name->name);
        $isSelect = in_array($method, self::SELECTS, true);

        if (! $isSelect && ! in_array($method, self::METHODS, true)) {
            return [];
        }

        $errors = [];

        foreach ($this->modelsOf($node, $scope) as $model) {
            $aliases = array_flip(ColumnAliasMap::aliasesOf($model));

            foreach ($this->columnsOf($node, $scope, $isSelect) as $column) {
                if (isset($aliases[$column])) {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        'Use the alias "%s" (whereAlias(), orderByAlias(), selectAliased()) instead of the real column "%s" on %s.',
                        $aliases[$column],
                        $column,
                        $model->getDisplayName()
                    ))->identifier('db2.realColumnQuery')->build();
                }
            }
        }

        return $errors;
    }

    /**
     * Literal column names in the call: the first argument (all for select), as a string or, for
     * an array, its keys (where) or values (select).
     *
     * @return list<string>
     */
    private function columnsOf(MethodCall|StaticCall $node, Scope $scope, bool $isSelect): array
    {
        $columns = [];

        foreach ($isSelect ? $node->getArgs() : [$node->getArgs()[0]] as $arg) {
            $type = $scope->getType($arg->value);

            foreach ($type->getConstantStrings() as $string) {
                $columns[] = $string->getValue();
            }

            foreach ($type->getConstantArrays() as $array) {
                foreach ($isSelect ? $array->getValueTypes() : $array->getKeyTypes() as $part) {
                    foreach ($part->getConstantStrings() as $string) {
                        $columns[] = $string->getValue();
                    }
                }
            }
        }

        return $columns;
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

        if ((new ObjectType(Builder::class))->isSuperTypeOf($type)->yes()) {
            return $this->classesOf($type->getTemplateType(Builder::class, 'TModel'));
        }

        // A relation forwards to the query builder of its related model.
        if ((new ObjectType(Relation::class))->isSuperTypeOf($type)->yes()) {
            return $this->classesOf($type->getTemplateType(Relation::class, 'TRelatedModel'));
        }

        return [];
    }

    /** @return list<ClassReflection> */
    private function classesOf(Type $type): array
    {
        return $type->getObjectClassReflections();
    }
}
