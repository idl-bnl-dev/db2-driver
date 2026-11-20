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

    public function test_it_flags_a_real_column_in_builder_and_static_queries(): void
    {
        $order = 'IDL\DB2Driver\Tests\Support\Models\AliasedOrder';

        $this->analyse([__DIR__.'/../Support/PHPStan/rules-usage.php'], [
            [sprintf('Use the alias "ordered_quantity" (whereAlias(), orderByAlias(), selectAliased()) instead of the real column "UVCCDE" on %s.', $order), 16],
            [sprintf('Use the alias "order_status" (whereAlias(), orderByAlias(), selectAliased()) instead of the real column "STATUS" on %s.', $order), 17],
            [sprintf('Use the alias "order_status" (whereAlias(), orderByAlias(), selectAliased()) instead of the real column "STATUS" on %s.', $order), 20],
        ]);
    }
}
