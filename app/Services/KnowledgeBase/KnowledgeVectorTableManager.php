<?php

namespace App\Services\KnowledgeBase;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

class KnowledgeVectorTableManager
{
    private array $ensuredDimensions = [];

    public function ensureVectorTable(int $dimension): string
    {
        if ($dimension <= 0) {
            throw new \InvalidArgumentException('vector dimension must be positive');
        }

        $tableName = 'knowledge_node_vectors_'.$dimension;
        if (isset($this->ensuredDimensions[$dimension])) {
            return $tableName;
        }

        $connection = $this->connection();
        $connection->statement(sprintf(
            'CREATE VIRTUAL TABLE IF NOT EXISTS %s USING vec0(node_id TEXT PRIMARY KEY, embedding FLOAT[%d])',
            $tableName,
            $dimension,
        ));
        $connection->table('knowledge_vector_tables')->insertOrIgnore([
            'dimension' => $dimension,
            'table_name' => $tableName,
            'created_at' => now(),
        ]);

        $this->ensuredDimensions[$dimension] = true;

        return $tableName;
    }

    public function upsertVector(int $dimension, string $nodeId, array $embedding): void
    {
        if (count($embedding) !== $dimension) {
            throw new \InvalidArgumentException(sprintf(
                'embedding length %d does not match expected dimension %d',
                count($embedding),
                $dimension,
            ));
        }

        $tableName = $this->ensureVectorTable($dimension);
        $connection = $this->connection();
        $payload = json_encode($embedding, JSON_THROW_ON_ERROR);

        $connection->statement('DELETE FROM '.$tableName.' WHERE node_id = ?', [$nodeId]);
        $connection->statement(
            'INSERT INTO '.$tableName.' (node_id, embedding) VALUES (?, ?)',
            [$nodeId, $payload],
        );
    }

    public function knnSearch(int $dimension, array $embedding, int $k): array
    {
        if ($k <= 0) {
            return [];
        }
        if (count($embedding) !== $dimension) {
            throw new \InvalidArgumentException(sprintf(
                'embedding length %d does not match expected dimension %d',
                count($embedding),
                $dimension,
            ));
        }

        $tableName = $this->ensureVectorTable($dimension);
        $rows = $this->connection()->select(
            'SELECT node_id, distance FROM '.$tableName.' WHERE embedding MATCH ? AND k = ? ORDER BY distance',
            [json_encode($embedding, JSON_THROW_ON_ERROR), $k],
        );

        return array_map(
            static fn (object $row): array => [
                'node_id' => (string) $row->node_id,
                'distance' => (float) $row->distance,
            ],
            $rows,
        );
    }

    public function deleteVectors(int $dimension, array $nodeIds): void
    {
        if ($nodeIds === []) {
            return;
        }

        $tableName = $this->ensureVectorTable($dimension);
        $placeholders = implode(',', array_fill(0, count($nodeIds), '?'));
        $this->connection()->statement(
            'DELETE FROM '.$tableName.' WHERE node_id IN ('.$placeholders.')',
            $nodeIds,
        );
    }

    public function resetAllTables(): void
    {
        $connection = $this->connection();
        foreach ($connection->table('knowledge_vector_tables')->pluck('table_name') as $tableName) {
            $connection->statement('DROP TABLE '.$tableName);
        }
        $connection->table('knowledge_vector_tables')->delete();
        $this->ensuredDimensions = [];
    }

    private function connection(): ConnectionInterface
    {
        return DB::connection('sqlite_rag');
    }
}
