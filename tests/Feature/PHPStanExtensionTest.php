<?php

/**
 * Runs PHPStan over a fixture in tests/Support/PHPStan and asserts on what it reports,
 * the way a consuming project would see the extension.
 *
 * @return array<int, string> messages by line number
 */
function analyseAliasFixture(string $config, string $fixture = 'typed-model.php'): array
{
    $root = dirname(__DIR__, 2);
    $output = shell_exec(sprintf(
        'cd %s && php vendor/bin/phpstan analyse -c %s --no-progress --error-format=json 2>/dev/null',
        escapeshellarg($root),
        escapeshellarg("tests/Support/PHPStan/{$config}")
    ));

    $json = json_decode(substr((string) $output, (int) strpos((string) $output, '{')), true);
    $messages = [];

    foreach ($json['files'] as $path => $file) {
        if (str_ends_with($path, $fixture)) {
            foreach ($file['messages'] as $message) {
                $messages[$message['line']][] = $message['message'];
            }
        }
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

describe('alias rules (rules.neon)', function () {
    beforeEach(function () {
        static $messages = null;

        $this->messages = $messages ??= analyseAliasFixture('phpstan-rules.neon', 'rules-usage.php');
    });

    it('flags reading and writing a real column', function () {
        expect(implode(' ', $this->messages[10]))->toContain('Use the alias "ordered_quantity" instead of the real column "UVCCDE"')
            ->and(implode(' ', $this->messages[11]))->toContain('Use the alias "ordered_quantity" instead of the real column "UVCCDE"');
    });

    it('accepts the alias, columns without an alias and models without aliases', function () {
        foreach ([12, 13, 14] as $line) {
            expect(implode(' ', $this->messages[$line] ?? []))->not->toContain('Use the alias');
        }
    });

    it('flags a real column in builder and static queries', function () {
        expect(implode(' ', $this->messages[16]))->toContain('"ordered_quantity"')->toContain('real column "UVCCDE"')
            ->and(implode(' ', $this->messages[17]))->toContain('"order_status"')->toContain('real column "STATUS"')
            ->and(implode(' ', $this->messages[20]))->toContain('"order_status"')->toContain('real column "STATUS"');
    });

    it('accepts alias scopes, other columns and models without aliases in queries', function () {
        foreach ([18, 19, 21] as $line) {
            expect(implode(' ', $this->messages[$line] ?? []))->not->toContain('Use the alias');
        }
    });
});
