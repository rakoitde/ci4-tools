<?php

namespace Rakoitde\Tools\Domain;

final class Table
{
    public string $source;
    public string $target;
    /** @var Column[] */ public array $columns = [];
    public ?array $where = null;
    public ?int $batchSize = 1000;
    public ?string $primaryKey = 'id';
    public bool $preservePrimaryKey = false;
    public string $syncMode = 'incremental';
    public ?string $incrementalBy = null;
    public string $watermarkType = 'datetime';
    public array $uniqueKey = [];
    public string $conflictPolicy = 'upsert';
    public string $deleteMode = 'none';
    public ?array $softDelete = null;
    public ?string $sourceDeleteCondition = null;
    public ?string $changeLogTable = null;
    public ?string $changeLogSince = null;
    public array $foreignKeys = [];
    public static function make(string $s): self
    {
        $o = new self();
        $o->source = $s;
        $o->target = $s;
        return $o;
    }
    public function target(string $t): self
    {
        $this->target = $t;
        return $this;
    }
    public function batchSize(int $n): self
    {
        $this->batchSize = $n;
        return $this;
    }
    public function where(array $w): self
    {
        $this->where = $w;
        return $this;
    }
    public function primaryKey(string $n): self
    {
        $this->primaryKey = $n;
        return $this;
    }
    public function preservePrimaryKey(bool $y = true): self
    {
        $this->preservePrimaryKey = $y;
        return $this;
    }
    public function columns(Column ...$c): self
    {
        $this->columns = $c;
        return $this;
    }
    public function syncMode(string $m): self
    {
        $this->syncMode = $m;
        return $this;
    }
    public function incrementalBy(string $c): self
    {
        $this->incrementalBy = $c;
        return $this;
    }
    public function incrementalWatermarkFormat(string $t): self
    {
        $this->watermarkType = $t;
        return $this;
    }
    public function uniqueKey(array $c): self
    {
        $this->uniqueKey = $c;
        return $this;
    }
    public function conflictPolicy(string $p): self
    {
        $this->conflictPolicy = $p;
        return $this;
    }
    public function deletes(string $m, array $s = null): self
    {
        $this->deleteMode = $m;
        $this->softDelete = $s;
        return $this;
    }
    public function sourceDeleteCondition(string $c): self
    {
        $this->sourceDeleteCondition = $c;
        return $this;
    }
    public function changeLogTable(string $t): self
    {
        $this->changeLogTable = $t;
        return $this;
    }
    public function changeLogSince(string $c): self
    {
        $this->changeLogSince = $c;
        return $this;
    }
    public function foreignKey(string $sc, string $rt, string $rc = 'id'): self
    {
        $this->foreignKeys[] = ['sourceCol' => $sc, 'refTable' => $rt, 'refCol' => $rc];
        return $this;
    }
    public function getTarget(): string
    {
        return $this->target;
    }
}
