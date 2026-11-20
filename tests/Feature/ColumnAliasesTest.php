<?php

use IDL\DB2Driver\Eloquent\HasColumnAliases;
use IDL\DB2Driver\Exceptions\ColumnAliasException;
use IDL\DB2Driver\Tests\Support\Models\AliasedOrder;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->db = db2();
    Model::setConnectionResolver(new ConnectionResolver(['db2' => $this->db]));
    AliasedOrder::flushColumnAliasCache();
});

afterEach(function () {
    Model::unsetConnectionResolver();
    AliasedOrder::flushColumnAliasCache();
});

describe('attributes', function () {
    it('reads the real column through its alias, keeping casts', function () {
        $order = new AliasedOrder;
        $order->setRawAttributes(['UVCCDE' => '5', 'STATUS' => 'OPEN']);

        expect($order->ordered_quantity)->toBe(5.0)
            ->and($order->order_status)->toBe('OPEN')
            ->and($order->UVCCDE)->toBe(5.0);
    });

    it('writes and fills through aliases', function () {
        $order = new AliasedOrder;
        $order->order_status = 'NEW';
        $order->fill(['ordered_quantity' => 3, 'unrelated_key' => 'dropped']);

        expect($order->getAttributes())->toBe(['STATUS' => 'NEW', 'UVCCDE' => 3])
            ->and($order->ordered_quantity)->toBe(3.0);
    });

    it('resolves aliases in the configured case style', function () {
        config()->set('db2-driver.alias_case', 'camel');

        $order = new AliasedOrder;
        $order->setRawAttributes(['UVCCDE' => '2']);

        expect($order->orderedQuantity)->toBe(2.0)
            ->and($order->ordered_quantity)->toBe(2.0);
    });

    it('accepts aliases untouched when the case style is none', function () {
        config()->set('db2-driver.alias_case', 'none');

        $order = new AliasedOrder;
        $order->setRawAttributes(['UVCCDE' => '2']);

        expect($order->ordered_quantity)->toBe(2.0);
    });

    it('rejects an unknown case style', function () {
        config()->set('db2-driver.alias_case', 'kebab');

        new AliasedOrder;
    })->throws(ColumnAliasException::class, 'Unknown db2-driver.alias_case [kebab]');

    it('reads a value selected under the alias itself', function () {
        $order = new AliasedOrder;
        $order->setRawAttributes(['ordered_quantity' => '7']);

        expect($order->ordered_quantity)->toBe(7.0);
    });

    it('finds a selected alias that Db2 returned upper-cased', function () {
        $order = new AliasedOrder;
        $order->setRawAttributes(['ORDERED_QUANTITY' => '7']);

        expect($order->ordered_quantity)->toBe(7.0);
    });

    it('prefers the real column over a case-folded label', function () {
        $order = new AliasedOrder;
        $order->setRawAttributes(['UVCCDE' => '5', 'ORDERED_QUANTITY' => '7']);

        expect($order->ordered_quantity)->toBe(5.0);
    });

    it('reads the new value after writing to an alias that was selected under its own name', function () {
        $order = new AliasedOrder;
        $order->setRawAttributes(['ordered_quantity' => '7']);
        $order->ordered_quantity = 10;

        expect($order->ordered_quantity)->toBe(10.0)
            ->and($order->getAttributes())->toBe(['UVCCDE' => 10]);
    });

    it('does not recurse on a model that declares no $columnAliases', function () {
        $model = new class extends Model
        {
            use HasColumnAliases;
        };

        expect($model->columnAliases())->toBe([])
            ->and($model->getAttribute('anything'))->toBeNull();
    });

    it('takes the aliases from an overridden columnAliases()', function () {
        $model = new class extends Model
        {
            use HasColumnAliases;

            public function columnAliases(): array
            {
                return ['dynamic_alias' => 'DYN'];
            }
        };
        $model->setRawAttributes(['DYN' => 'x']);

        expect($model->dynamic_alias)->toBe('x');
    });

    it('passes non-string keys through', function () {
        $order = new AliasedOrder;

        expect($order->getAttribute(0))->toBeNull();
    });
});

describe('toArray', function () {
    it('keeps real columns by default', function () {
        $order = new AliasedOrder;
        $order->setRawAttributes(['UVCCDE' => '5', 'OTHER' => 'a']);

        expect($order->toArray())->toBe(['UVCCDE' => 5.0, 'OTHER' => 'a']);
    });

    it('returns aliases when exposed, keeping column order', function () {
        $order = new AliasedOrder;
        $order->exposeAliases = true;
        $order->setRawAttributes(['OTHER' => 'a', 'UVCCDE' => '5']);

        expect($order->toArray())->toBe(['OTHER' => 'a', 'ordered_quantity' => 5.0]);
    });

    it('exposes the alias as declared, not in the configured case style', function () {
        config()->set('db2-driver.alias_case', 'camel');

        $order = new AliasedOrder;
        $order->exposeAliases = true;
        $order->setRawAttributes(['UVCCDE' => '5']);

        expect($order->toArray())->toBe(['ordered_quantity' => 5.0]);
    });
});

describe('scopes', function () {
    it('filters and orders by alias using the real column', function () {
        AliasedOrder::whereAlias('ordered_quantity', '>', 0)
            ->whereAlias('order_status', 'OPEN')
            ->orderByAlias('ordered_quantity', 'desc')
            ->get();

        $query = end($this->db->selects);

        expect($query['query'])->toContain('UVCCDE')->toContain('STATUS')
            ->and($query['query'])->not->toContain('ordered_quantity')
            ->and($query['bindings'])->toBe([0, 'OPEN']);
    });

    it('selects every declared alias as AS', function () {
        AliasedOrder::selectAliased()->get();

        expect(end($this->db->selects)['query'])
            ->toContain('UVCCDE as ordered_quantity')
            ->toContain('STATUS as order_status');
    });

    it('selects only the requested aliases', function () {
        AliasedOrder::selectAliased(['order_status'])->get();

        expect(end($this->db->selects)['query'])
            ->toContain('STATUS as order_status')
            ->not->toContain('UVCCDE');
    });

    it('refuses an unknown alias in every scope', function (string $scope) {
        AliasedOrder::{$scope}($scope === 'selectAliased' ? ['UVCCDE'] : 'UVCCDE');
    })->with(['whereAlias', 'orderByAlias', 'selectAliased'])
        ->throws(ColumnAliasException::class, 'Unknown column alias [UVCCDE]');

    it('refuses an alias that is not a plain identifier', function () {
        AliasedOrder::selectAliased(['x, (select 1)']);
    })->throws(ColumnAliasException::class, 'Cannot select [x, (select 1)] as an alias');
});

describe('collisions', function () {
    it('rejects an alias that shadows a real column', function () {
        new class extends Model
        {
            use HasColumnAliases;

            protected array $columnAliases = ['status' => 'STATUS', 'other' => 'status'];
        };
    })->throws(ColumnAliasException::class, 'shadows a real column or cast');

    it('rejects an alias that shadows a cast', function () {
        new class extends Model
        {
            use HasColumnAliases;

            protected $casts = ['amount' => 'float'];

            protected array $columnAliases = ['amount' => 'AMT'];
        };
    })->throws(ColumnAliasException::class, 'shadows a real column or cast');

    it('rejects an alias that shadows an accessor', function () {
        new class extends Model
        {
            use HasColumnAliases;

            protected array $columnAliases = ['accessor' => 'ACC'];

            public function getAccessorAttribute(): string
            {
                return 'x';
            }
        };
    })->throws(ColumnAliasException::class, 'shadows an accessor');

    it('rejects two aliases that normalise to the same name', function () {
        new class extends Model
        {
            use HasColumnAliases;

            protected array $columnAliases = ['order_status' => 'A', 'orderStatus' => 'B'];
        };
    })->throws(ColumnAliasException::class, 'is declared twice');
});
