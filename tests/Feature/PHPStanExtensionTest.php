<?php

/**
 * Runs PHPStan over tests/Support/PHPStan/typed-model.php and asserts on what it reports,
 * the way a consuming project would see the extension.
 *
 * @return array<int, string> messages by line number
 */
function analyseAliasFixture(string $config): array
{
    $root = dirname(__DIR__, 2);
    $output = shell_exec(sprintf(
        'cd %s && php vendor/bin/phpstan analyse -c %s --no-progress --error-format=json 2>/dev/null',
        escapeshellarg($root),
        escapeshellarg("tests/Support/PHPStan/{$config}")
    ));

    $json = json_decode(substr((string) $output, (int) strpos((string) $output, '{')), true);
    $messages = [];

    foreach (array_values($json['files'])[0]['messages'] ?? [] as $message) {
        $messages[$message['line']][] = $message['message'];
    }

    return $messages;
}

describe('property extension', function () {
    it('types aliases from the cast of the real column', function () {
        $messages = analyseAliasFixture('phpstan-snake.neon');

        expect($messages[24])->toBe(['Dumped type: float'])
            ->and($messages[25])->toBe(['Dumped type: mixed'])
            ->and($messages[26])->toBe(['Dumped type: float'])
            ->and($messages[27])->toBe(['Dumped type: int'])
            ->and($messages[28])->toBe(['Dumped type: string'])
            ->and($messages[29])->toBe(['Dumped type: bool'])
            ->and($messages[30])->toBe(['Dumped type: array'])
            ->and($messages[31])->toBe(['Dumped type: Illuminate\Support\Carbon'])
            ->and($messages[32])->toBe(['Dumped type: mixed'])
            ->and($messages[33])->toBe(['Dumped type: mixed']);
    });

    it('allows writing an alias and reading it in the other case style', function () {
        $messages = analyseAliasFixture('phpstan-snake.neon');

        expect($messages)->not->toHaveKey(34)
            ->and(implode(' ', $messages[35]))->not->toContain('undefined property');
    });

    it('still reports properties that are no alias', function () {
        $messages = analyseAliasFixture('phpstan-snake.neon');

        expect(implode(' ', $messages[36]))->toContain('undefined property')->toContain('$not_an_alias');
    });

    it('follows the configured case style', function () {
        $messages = analyseAliasFixture('phpstan-none.neon');

        expect($messages[24])->toBe(['Dumped type: float'])
            ->and(implode(' ', $messages[35]))->toContain('undefined property')->toContain('$orderedQuantity');
    });
});
