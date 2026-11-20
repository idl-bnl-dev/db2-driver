<?php

namespace IDL\DB2Driver\Tests\PHPStan;

use IDL\DB2Driver\PHPStan\Rules\RealColumnQueryRule;
use PHPStan\Rules\Rule;

final class RealColumnQueryRuleTest extends AliasRulesTestCase
{
    protected function getRule(): Rule
    {
        return new RealColumnQueryRule($this->createReflectionProvider());
    }

    public function test_it_flags_or_variants_arrays_selects_aggregates_and_relations(): void
    {
        $order = 'IDL\DB2Driver\Tests\Support\Models\AliasedOrder';
        $message = fn (string $alias, string $column) => sprintf('Use the alias "%s" (whereAlias(), orderByAlias(), selectAliased()) instead of the real column "%s" on %s.', $alias, $column, $order);

        $this->analyse([__DIR__.'/../Support/PHPStan/rules-usage.php'], [
            [$message('ordered_quantity', 'UVCCDE'), 16],
            [$message('order_status', 'STATUS'), 17],
            [$message('order_status', 'STATUS'), 20],
            [$message('order_status', 'STATUS'), 26],
            [$message('ordered_quantity', 'UVCCDE'), 27],
            [$message('order_status', 'STATUS'), 28],
            [$message('ordered_quantity', 'UVCCDE'), 29],
            [$message('order_status', 'STATUS'), 30],
            [$message('ordered_quantity', 'UVCCDE'), 31],
            [$message('order_status', 'STATUS'), 32],
        ]);
    }
}
