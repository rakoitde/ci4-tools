<?php

namespace Rakoitde\Tools\Domain;

final class Column
{
    public string $source;
    public string $target;
    public array $steps = [];
    public ?array $bindings = null;
    public ?array $inlineMap = null;
    public $inlineDefault = null;
    public ?string $providerClass = null;
    public array $providerOptions = [];
    public ?array $foreign = null;

    public static function make(string $s, ?string $t = null): self
    {
        $o = new self();
        $o->source = $s;
        $o->target = $t ?? $s;
        return $o;
    }

    public function trim(): self
    {
        $this->steps[] = ['op' => 'trim'];
        return $this;
    }

    public function lower(): self
    {
        $this->steps[] = ['op' => 'lower'];
        return $this;
    }

    public function upper(): self
    {
        $this->steps[] = ['op' => 'upper'];
        return $this;
    }

    public function toInt(): self
    {
        $this->steps[] = ['op' => 'toInt'];
        return $this;
    }

    public function toDateTime(?string $f = null): self
    {
        $this->steps[] = ['op' => 'toDateTime', 'fmt' => $f];
        return $this;
    }

    public function hash(string $a = 'sha256'): self
    {
        $this->steps[] = ['op' => 'hash', 'algo' => $a];
        return $this;
    }

    public function value($c): self
    {
        $this->steps[] = ['op' => 'value', 'value' => $c];
        return $this;
    }

    public function nullIfEmpty(): self
    {
        $this->steps[] = ['op' => 'nullIfEmpty'];
        return $this;
    }

    public function bindings(array $m): self
    {
        $this->bindings = $m;
        return $this;
    }

    public function concat(string ...$parts): self
    {
        $this->steps[] = ['op' => 'concat', 'parts' => $parts];
        return $this;
    }

    public function lookupInline(array $inlineMap, $inlineDefault = null): self
    {
        $this->inlineMap     = $inlineMap;
        $this->inlineDefault = $inlineDefault;
        return $this;
    }

    public function lookupProvider(string $providerClass, array $providerOptions = []): self
    {
        $this->providerClass   = $providerClass;
        $this->providerOptions = $providerOptions;
        return $this;
    }

    /**
     * Register a query-builder based lookup step.
     *
     * @param callable $findQuery     fn(BaseConnection $db, array $row, array $context): BaseBuilder
     * @param null|callable $insertQuery fn(BaseConnection $db, array $row, array $context): array|BaseBuilder|int|string
     * @param string $idColumn        Column expected in SELECT (default 'id')
     */
    public function lookupUsingQueryBuilder(callable $findQuery, ?callable $insertQuery = null, string $idColumn = 'id'): self
    {
        $this->steps[] = [
            'op'       => 'lookupQb',
            'find'     => $findQuery,
            'insert'   => $insertQuery,
            'idColumn' => $idColumn,
        ];
        return $this;
    }

    // In Column.php (Builder):
    public function lookupTableUpsert(string $table, string $textColumn, string $idColumn = 'id', array $options = []): self
    {
        $this->steps[] = [
            'op' => 'lookupTableUpsert',
            'table' => $table,
            'textColumn' => $textColumn,
            'idColumn' => $idColumn,
            'createIfMissing' => $options['createIfMissing'] ?? true,
            'inputIsArray' => $options['inputIsArray'] ?? false,
            'joinWith' => $options['joinWith'] ?? ' ',
            'extraInsertColumns' => $options['extraInsertColumns'] ?? [],
        ];
        return $this;
    }


    public function foreign(string $rt, string $rc = 'id'): self
    {
        $this->foreign = ['table' => $rt, 'col' => $rc];
        return $this;
    }

    /**
     * Sync a many-to-many pivot from this column's value (array/JSON/CSV).
     *
     * @param string   $pivotTable
     * @param string   $leftIdColumn
     * @param string   $rightIdColumn
     * @param callable $itemFindQuery    fn(BaseConnection $db, $token, array $row, array $context): BaseBuilder
     * @param array    $options {
     *   @var string   $leftIdSelectColumn  Column selected by itemFindQuery (default 'id')
     *   @var string   $rightIdSource       Source row key for RIGHT id (e.g. 'id'), ignored if rightIdResolver given
     *   @var callable $rightIdResolver     fn(BaseConnection $db, array $row, array $context): int|string|null
     *   @var bool     $clearBeforeInsert   Delete pivot rows for RIGHT id before inserting (default false)
     *   @var bool     $deduplicate         Avoid duplicates (default true)
     * }
     */
    public function syncManyToMany(
        string $pivotTable,
        string $leftIdColumn,
        string $rightIdColumn,
        callable $itemFindQuery,
        array $options = []
    ): self {
        $this->steps[] = [
            'op'                 => 'syncManyToMany',
            'pivotTable'         => $pivotTable,
            'leftIdColumn'       => $leftIdColumn,
            'rightIdColumn'      => $rightIdColumn,
            'itemFindQuery'      => $itemFindQuery,
            'leftIdSelectColumn' => $options['leftIdSelectColumn'] ?? 'id',
            'rightIdSource'      => $options['rightIdSource']      ?? null,
            'rightIdResolver'    => $options['rightIdResolver']    ?? null,
            'clearBeforeInsert'  => (bool)($options['clearBeforeInsert'] ?? false),
            'deduplicate'        => (bool)($options['deduplicate'] ?? true),
            'syncValueToTarget'  => (bool)($options['syncValueToTarget'] ?? false),
        ];
        return $this;
    }

    /**
     * Sync a one-to-many child table from this column's value (scalar/array/JSON/CSV).
     *
     * options:
     *  - rightIdSource       ?string
     *  - rightIdResolver     ?callable(BaseConnection $db, array $row, array $ctx): int|string|null
     *  - clearBeforeInsert   bool
     *  - deduplicate         bool
     *  - syncValueToTarget   bool   (default false)
     *  - valueNormalizer     ?callable($token, array $row, array $ctx): scalar|null
     *  - extraInsertColumns  array|callable(BaseConnection $db, $token, array $row, array $ctx): array
     */
    public function syncOneToMany(
        string $childTable,
        string $parentIdColumn,
        string $valueColumn,
        array $options = []
    ): self {
        $this->steps[] = [
            'op'                => 'syncOneToMany',
            'childTable'        => $childTable,
            'parentIdColumn'    => $parentIdColumn,
            'valueColumn'       => $valueColumn,
            'rightIdSource'     => $options['rightIdSource']      ?? null,
            'rightIdResolver'   => $options['rightIdResolver']    ?? null,
            'clearBeforeInsert' => (bool)($options['clearBeforeInsert'] ?? false),
            'deduplicate'       => (bool)($options['deduplicate'] ?? true),
            'syncValueToTarget' => (bool)($options['syncValueToTarget'] ?? false),
            'valueNormalizer'   => $options['valueNormalizer']    ?? null,
            'extraInsertColumns'=> $options['extraInsertColumns'] ?? [],
        ];
        return $this;
    }

}
