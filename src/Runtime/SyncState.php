<?php

namespace Rakoitde\Tools\Runtime;

final class SyncState
{
    public function __construct(private \CodeIgniter\Database\BaseConnection $db)
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS sync_state (table_name VARCHAR(191) PRIMARY KEY,last_watermark VARCHAR(191) NULL,watermark_type VARCHAR(32) NOT NULL DEFAULT 'datetime',last_run_at DATETIME NULL,rows_upserted BIGINT DEFAULT 0,rows_deleted BIGINT DEFAULT 0)");
    }
    public function get(string $t): array
    {
        $r = $this->db->table('sync_state')->where('table_name', $t)->get()->getRowArray();
        return $r ?? ['table_name' => $t, 'last_watermark' => null, 'watermark_type' => 'datetime', 'last_run_at' => null, 'rows_upserted' => 0, 'rows_deleted' => 0];
    }
    public function update(string $t, array $d): void
    {
        $d['table_name'] = $t;
        $this->db->table('sync_state')->replace($d);
    }
}
