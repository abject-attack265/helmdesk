<?php

namespace App\Services\KnowledgeBase;

use Closure;
use Illuminate\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * 串行处理知识库删除及业务引用写入。
 */
class KnowledgeBaseReferenceLock
{
    /** 锁覆盖知识库内容清理和关联配置保存。 */
    private const int LOCK_TTL_SECONDS = 3600;

    /** 等待其他知识库操作完成的最长秒数。 */
    private const int WAIT_SECONDS = 15;

    /**
     * 按固定顺序锁定知识库并执行操作。
     *
     * @template TResult
     *
     * @param  list<string>  $knowledgeBaseIds
     * @param  Closure(Closure(): void): TResult  $callback
     * @return TResult
     */
    public function run(array $knowledgeBaseIds, Closure $callback): mixed
    {
        $ids = array_values(array_unique($knowledgeBaseIds));
        sort($ids, SORT_STRING);

        return $this->runAt($ids, 0, [], $callback);
    }

    /**
     * 逐个获取知识库锁并在回调完成后释放。
     *
     * @template TResult
     *
     * @param  list<string>  $knowledgeBaseIds
     * @param  list<Lock>  $locks
     * @param  Closure(Closure(): void): TResult  $callback
     * @return TResult
     */
    private function runAt(array $knowledgeBaseIds, int $index, array $locks, Closure $callback): mixed
    {
        if ($index >= count($knowledgeBaseIds)) {
            return $callback(function () use ($locks): void {
                foreach ($locks as $lock) {
                    if (! $lock->refresh(self::LOCK_TTL_SECONDS)) {
                        throw new LockTimeoutException;
                    }
                }
            });
        }

        $knowledgeBaseId = $knowledgeBaseIds[$index];
        /** @var Lock $lock */
        $lock = Cache::lock($this->key($knowledgeBaseId), self::LOCK_TTL_SECONDS);

        return $lock->block(
            self::WAIT_SECONDS,
            fn (): mixed => $this->runAt(
                $knowledgeBaseIds,
                $index + 1,
                [...$locks, $lock],
                $callback,
            ),
        );
    }

    /**
     * 生成知识库引用锁键。
     */
    private function key(string $knowledgeBaseId): string
    {
        return 'knowledge-base-reference:'.$knowledgeBaseId;
    }
}
