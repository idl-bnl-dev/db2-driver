<?php

namespace IDL\DB2Driver\Tests\PHPStan;

use IDL\DB2Driver\PHPStan\Rules\RealColumnPropertyRule;
use PHPStan\Rules\Rule;

final class RealColumnPropertyRuleTest extends AliasRulesTestCase
{
    protected function getRule(): Rule
    {
        return new RealColumnPropertyRule;
    }

    public function test_it_flags_reading_and_writing_a_real_column(): void
    {
        $message = 'Use the alias "ordered_quantity" instead of the real column "UVCCDE" on IDL\DB2Driver\Tests\Support\Models\AliasedOrder.';

        $this->analyse([__DIR__.'/../Support/PHPStan/rules-usage.php'], [
            [$message, 10],
            [$message, 11],
        ]);
    }
}
