<?php
declare(strict_types=1);

namespace Rakoitde\Tools\Migrations;

/**
 * CI4 Migration Helper Class
 *
 * - SnapshotDiffService: erzeugt ein simples, forge-freundliches Diff-Model zwischen zwei Snapshots.
 *
 * Hinweise:
 * - Snapshots sollten die Struktur aus createJsonInfo() besitzen: table, fields, indexes, foreignkeys
 * - "fields" ist ein assoc-Array name => object/array mit min. {name,type,max_length,nullable,default,primary_key}
 * - "indexes" enthält Einträge mit {name,type,fields[]}
 * - "foreignkeys" enthält Einträge mit {constraint_name,column_name[],foreign_table_name,foreign_column_name[],on_delete,on_update}
 * - Der Builder erzeugt Strings für UP/DOWN; das Einfügen übernimmt MigrationFileEditor
 *
 */


use RuntimeException;

/**
 * Ersetzt gezielt die Bodies von up()/down() in einer Migration.
 * Fällt bei Bedarf auf sichere Regex-Variante zurück, nutzt ansonsten token_get_all.
 */
final class MigrationFileEditor
{
    /**
     * @throws RuntimeException
     */
    public function replaceUpDown(string $filePath, string $upCode, string $downCode): void
    {
        if (!is_file($filePath)) {
            throw new RuntimeException("Migration file not found: {$filePath}");
        }
        $code = file_get_contents($filePath);
        if ($code === false) {
            throw new RuntimeException("Unable to read file: {$filePath}");
        }

        $new = $this->replaceMethodBody($code, 'up', $upCode);
        $new = $this->replaceMethodBody($new, 'down', $downCode);

        $ok = file_put_contents($filePath, $new, LOCK_EX);
        if ($ok === false) {
            throw new RuntimeException("Unable to write file: {$filePath}");
        }
    }

    private function replaceMethodBody(string $code, string $method, string $body): string
    {
        // Bevorzugt: Regex nur auf die Methode, non-greedy, Klammer-Balancing vermeiden wir durch sU und \{.*?\}
        $pattern = '/(public\s+function\s+' . preg_quote($method, '/') . '\s*\(\)\s*\{)(.*?)(\})/s';
        if (preg_match($pattern, $code)) {
            $replacement = "\$1\n" . rtrim($body) . "\n    \$3"; // 4 Spaces Einrückung in body einkalkulieren
            return (string)preg_replace($pattern, $replacement, $code, 1);
        }

        // Fallback: Methode existiert nicht → anhängen
        $insertion = \PHP_EOL . '    public function ' . $method . "()\n    {\n" . rtrim($body) . "\n    }\n";
        $patternClassEnd = '/\}\s*$/';
        if (preg_match($patternClassEnd, $code)) {
            return (string)preg_replace($patternClassEnd, rtrim($insertion) . "\n}\n", $code, 1);
        }

        // letzter Fallback: einfach anhängen
        return rtrim($code) . "\n" . $insertion;
    }
}