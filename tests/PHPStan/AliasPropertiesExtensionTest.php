<?php

namespace IDL\DB2Driver\Tests\PHPStan;

use IDL\DB2Driver\Tests\Support\PHPStan\AliasPropertyTypeRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/** @extends RuleTestCase<AliasPropertyTypeRule> */
final class AliasPropertiesExtensionTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new AliasPropertyTypeRule;
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__.'/../Support/PHPStan/phpstan-snake.neon'];
    }

    public function test_it_types_aliases_from_the_cast_of_the_real_column(): void
    {
        require_once __DIR__.'/../Support/PHPStan/typed-model.php';

        $this->analyse([__DIR__.'/../Support/PHPStan/typed-model.php'], [
            ['float', 24],
            ['mixed', 25],
            ['float', 26],
            ['int', 27],
            ['string', 28],
            ['bool', 29],
            ['array', 30],
            ['Illuminate\Support\Carbon', 31],
            ['mixed', 32],
            ['mixed', 33],
            ['float', 34],
            ['float', 35],
            ['*ERROR*', 36],
        ]);
    }
}
