<?php

namespace App\Services\KnowledgeBase;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * 为知识文档的解析和索引阶段提供独立锁，删除操作同时持有全部阶段锁。
 */
class KnowledgeDocumentPipelineLock
{
    /**
     * 锁覆盖最长的 RAPTOR 任务，并为队列超时清理预留余量。
     */
    private const int LOCK_TTL_SECONDS = 1200;

    /**
     * 在文档解析锁内执行操作。
     */
    public function runParsing(string $documentId, int $waitSeconds, Closure $callback): mixed
    {
        return $this->runStage($documentId, 'parse', $waitSeconds, $callback);
    }

    /**
     * 在文档向量索引锁内执行操作。
     */
    public function runVectorIndexing(string $documentId, int $waitSeconds, Closure $callback): mixed
    {
        return $this->runStage($documentId, 'vector', $waitSeconds, $callback);
    }

    /**
     * 在文档 RAPTOR 索引锁内执行操作。
     */
    public function runRaptorIndexing(string $documentId, int $waitSeconds, Closure $callback): mixed
    {
        return $this->runStage($documentId, 'raptor', $waitSeconds, $callback);
    }

    /**
     * 同时持有文档全部阶段锁，供删除操作排空正在运行的流水线。
     */
    public function runExclusively(string $documentId, int $waitSeconds, Closure $callback): mixed
    {
        return $this->runStage($documentId, 'parse', $waitSeconds, fn () => $this->runStage(
            $documentId,
            'vector',
            $waitSeconds,
            fn () => $this->runStage($documentId, 'raptor', $waitSeconds, $callback),
        ));
    }

    /**
     * 在指定文档阶段的跨进程锁内执行操作。
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    private function runStage(string $documentId, string $stage, int $waitSeconds, Closure $callback): mixed
    {
        return Cache::lock($this->key($documentId, $stage), self::LOCK_TTL_SECONDS)
            ->block($waitSeconds, $callback);
    }

    /**
     * 生成文档流水线阶段锁键。
     */
    private function key(string $documentId, string $stage): string
    {
        return 'knowledge-document-pipeline:'.$documentId.':'.$stage;
    }
}
