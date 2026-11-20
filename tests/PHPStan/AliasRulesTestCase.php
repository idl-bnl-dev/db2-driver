<?php

namespace IDL\DB2Driver\Tests\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/** @extends RuleTestCase<Rule> */
abstract class AliasRulesTestCase extends RuleTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__.'/../Support/PHPStan/phpstan-snake.neon'];
    }
}

