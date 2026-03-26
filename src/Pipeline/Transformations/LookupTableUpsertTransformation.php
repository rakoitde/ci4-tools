<?php

namespace Rakoitde\Tools\Pipeline\Transformations;

use Rakoitde\Tools\Pipeline\TransformationInterface;
use CodeIgniter\Database\BaseConnection;

/**
 * Looks up a text value in a target table and returns its ID.
 * If not found and createIfMissing=true, inserts a new row and returns the new ID.
 *
 * Context must provide:
 *  - 'db' => \CodeIgniter\Database\BaseConnection
 *
 * Typical config:
 *   new LookupTableUpsertTransformation(
 *     table: 'persons',
 *     textColumn: 'first_name',
 *     idColumn: 'id',
 *     createIfMissing: true,
 *     extraInsertColumns: ['created_at' => date('Y-m-d H:i:s')]
 *   )
 *
 * If inputIsArray=true, the incoming $value is expected to be an array of parts;
 * they will be joined using $joinWith before lookup (e.g. ['first','last'] => 'first last').
 */
final class LookupTableUpsertTransformation implements TransformationInterface
{
    public function __construct(
        private ?string $table,
        private ?string $textColumn,
        private string $idColumn = 'id',
        private bool $inputIsArray = false,
        private string $joinWith = ' ',
        private bool $createIfMissing = true,
        private array $extraInsertColumns = []
    ) {
        if (!$this->table || !$this->textColumn) {
            throw new \InvalidArgumentException('LookupTableUpsert requires table and textColumn.');
        }
    }

    public function apply($value, array $row = [], array $context = [])
    {
        /** @var BaseConnection|null $db */
        $db = $context['db'] ?? null;
        if (!$db) {
            throw new \RuntimeException('LookupTableUpsert requires $context["db"] (CodeIgniter DB connection).');
        }

        $text = $this->normalizeText($value, $row);

        // Try find existing
        $existing = $db->table($this->table)
            ->select($this->idColumn)
            ->where($this->textColumn, $text)
            ->get()
            ->getRowArray();

        if ($existing && isset($existing[$this->idColumn])) {
            return $existing[$this->idColumn];
        }

        if (!$this->createIfMissing) {
            // Not found and creation not allowed → return null or original text based on your policy
            return null;
        }

        // Create new row and return new ID
        $insertData = array_merge(
            [$this->textColumn => $text],
            $this->extraInsertColumns
        );

        $db->transStart();
        $db->table($this->table)->insert($insertData);
        $newId = $db->insertID();
        $db->transComplete();

        return $newId ?: null;
    }

    private function normalizeText($value, array $row): string
    {
        if ($this->inputIsArray && is_array($value)) {
            return trim(implode($this->joinWith, array_map('strval', $value)));
        }
        return trim((string) $value);
    }
}
