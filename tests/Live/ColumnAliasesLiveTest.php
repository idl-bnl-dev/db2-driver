<?php

use IDL\DB2Driver\Eloquent\HasColumnAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

uses()->group('live');

/**
 * Column aliases against a real IBM i: unquoted `as` labels come back in the driver's case
 * (this suite runs with PDO::CASE_LOWER, a default setup returns them upper-cased).
 */
beforeEach(function () {
    $this->table = $this->tempTable('A');

    $this->db()->getSchemaBuilder()->create($this->table, function (Blueprint $table) {
        $table->increments('id');
        $table->string('uvccde', 20)->nullable();
        $table->string('stat', 10)->nullable();
    });

    $this->orderClass = get_class(new class extends Model
    {
        use HasColumnAliases;

        public static string $liveTable = '';

        protected $connection = 'db2';

        public $timestamps = false;

        protected $guarded = [];

        protected $casts = ['uvccde' => 'integer'];

        protected array $columnAliases = ['ordered_quantity' => 'uvccde', 'orderStatus' => 'stat'];

        public function getTable()
        {
            return static::$liveTable;
        }
    });
    $this->orderClass::$liveTable = $this->table;
    $this->orderClass::flushColumnAliasCache();
});

afterEach(function () {
    if (isset($this->app)) {
        $this->db()->getSchemaBuilder()->dropIfExists($this->table);
        $this->orderClass::flushColumnAliasCache();
    }
});

it('round-trips writes, scopes and selectAliased on a real database', function () {
    $class = $this->orderClass;

    $class::create(['ordered_quantity' => 5, 'orderStatus' => 'OPEN']);
    $class::create(['ordered_quantity' => 0, 'orderStatus' => 'DONE']);

    $row = $class::whereAlias('ordered_quantity', '>', 0)->orderByAlias('ordered_quantity')->selectAliased()->first();

    expect($row->ordered_quantity)->toBe(5)
        ->and($row->order_status)->toBe('OPEN');

    $row->ordered_quantity = 9;
    $row->save();

    expect($class::whereAlias('ordered_quantity', 9)->count())->toBe(1)
        ->and($class::selectAliased(['ordered_quantity'])->whereAlias('orderStatus', 'OPEN')->first()->ordered_quantity)->toBe(9);
});
