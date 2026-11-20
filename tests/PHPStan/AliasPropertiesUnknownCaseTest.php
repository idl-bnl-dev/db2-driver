<?php

namespace IDL\DB2Driver\Tests\PHPStan;

use IDL\DB2Driver\Tests\Support\PHPStan\AliasPropertyTypeRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/** @extends RuleTestCase<AliasPropertyTypeRule> */
final class AliasPropertiesUnknownCaseTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new AliasPropertyTypeRule;
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__.'/../Support/PHPStan/phpstan-unknown.neon'];
    }

    public function test_an_unknown_case_style_falls_back_to_exact_alias_names(): void
    {
        require_once __DIR__.'/../Support/PHPStan/typed-model.php';

        $this->analyse([__DIR__.'/../Support/PHPStan/typed-model.php'], [
            ['float|null', 24],
            ['mixed', 25],
            ['float|null', 26],
            ['int|null', 27],
            ['string|null', 28],
            ['bool|null', 29],
            ['array|null', 30],
            ['Illuminate\Support\Carbon|null', 31],
            ['mixed', 32],
            ['mixed', 33],
            ['float|null', 34],
            ['*ERROR*', 35],
            ['*ERROR*', 36],
            ['Carbon\CarbonImmutable|null', 37],
        ]);
    }
}
