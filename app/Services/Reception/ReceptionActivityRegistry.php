<?php

namespace App\Services\Reception;

use App\Data\Reception\ReceptionActivityStateData;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

/**
 * 保存并聚合接待方活动租约。
 *
 * 同一会话可同时存在多个人工页面、AI debounce 与 AI turn。人工页面请求
 * 携带递增顺序号，保证释放请求不会被迟到的续期覆盖。
 */
class ReceptionActivityRegistry
{
    /** 聚合状态与人工释放墓碑的缓存生命周期。 */
    private const int STATE_TTL_SECONDS = 600;

    /** 人工释放墓碑保留时长，用于拒绝迟到且顺序号较小的续期。 */
    private const int RELEASE_TOMBSTONE_MILLISECONDS = 60000;

    /**
     * 建立或续期一个无需顺序控制的活动来源。
     */
    public function renew(
        string $conversationId,
        string $activityId,
        int $holdMilliseconds,
    ): void {
        $this->withLock($conversationId, function () use ($conversationId, $activityId, $holdMilliseconds): void {
            $state = $this->readState($conversationId);
            $state['activities'] = $this->pruneExpiredActivities($state['activities']);
            $state['activities'][$activityId] = [
                'active' => true,
                'expires_at' => $this->nowMilliseconds() + $holdMilliseconds,
                'sequence' => null,
            ];
            $state['revision'] = $this->nextRevision($state['revision']);
            $this->saveState($conversationId, $state);
        });
    }

    /**
     * 释放一个无需顺序控制的活动来源。
     */
    public function release(string $conversationId, string $activityId): void
    {
        $this->withLock($conversationId, function () use ($conversationId, $activityId): void {
            $state = $this->readState($conversationId);
            $state['activities'] = $this->pruneExpiredActivities($state['activities']);
            unset($state['activities'][$activityId]);
            $state['revision'] = $this->nextRevision($state['revision']);
            $this->saveState($conversationId, $state);
        });
    }

    /**
     * 按顺序号建立或续期人工页面活动；返回请求是否生效。
     */
    #[\NoDiscard('调用方必须跳过顺序号过期的实时事件。')]
    public function renewOrdered(
        string $conversationId,
        string $activityId,
        int $holdMilliseconds,
        int $sequence,
    ): bool {
        return $this->withLock($conversationId, function () use ($conversationId, $activityId, $holdMilliseconds, $sequence): bool {
            $state = $this->readState($conversationId);
            $state['activities'] = $this->pruneExpiredActivities($state['activities']);
            $current = $state['activities'][$activityId] ?? null;
            if (! $this->acceptsSequence($current, $sequence)) {
                return false;
            }

            $state['activities'][$activityId] = [
                'active' => true,
                'expires_at' => $this->nowMilliseconds() + $holdMilliseconds,
                'sequence' => $sequence,
            ];
            $state['revision'] = $this->nextRevision($state['revision']);
            $this->saveState($conversationId, $state);

            return true;
        });
    }

    /**
     * 按顺序号释放人工页面活动；返回请求是否生效。
     */
    #[\NoDiscard('调用方必须跳过顺序号过期的实时事件。')]
    public function releaseOrdered(string $conversationId, string $activityId, int $sequence): bool
    {
        return $this->withLock($conversationId, function () use ($conversationId, $activityId, $sequence): bool {
            $state = $this->readState($conversationId);
            $state['activities'] = $this->pruneExpiredActivities($state['activities']);
            $current = $state['activities'][$activityId] ?? null;
            if (! $this->acceptsSequence($current, $sequence)) {
                return false;
            }

            $state['activities'][$activityId] = [
                'active' => false,
                'expires_at' => $this->nowMilliseconds() + self::RELEASE_TOMBSTONE_MILLISECONDS,
                'sequence' => $sequence,
            ];
            $state['revision'] = $this->nextRevision($state['revision']);
            $this->saveState($conversationId, $state);

            return true;
        });
    }

    /**
     * 聚合会话当前有效的活动租约并清理过期来源。
     */
    public function current(string $conversationId): ReceptionActivityStateData
    {
        return $this->withLock($conversationId, function () use ($conversationId): ReceptionActivityStateData {
            $state = $this->readState($conversationId);
            $activities = $this->pruneExpiredActivities($state['activities']);
            if ($activities !== $state['activities']) {
                $state['activities'] = $activities;
                $state['revision'] = $this->nextRevision($state['revision']);
                $this->saveState($conversationId, $state);
            }

            $now = $this->nowMilliseconds();
            $longestHoldMilliseconds = 0;

            foreach ($activities as $activity) {
                if (! $activity['active']) {
                    continue;
                }

                $longestHoldMilliseconds = max(
                    $longestHoldMilliseconds,
                    $activity['expires_at'] - $now,
                );
            }

            return $longestHoldMilliseconds > 0
                ? new ReceptionActivityStateData(
                    active: true,
                    hold_ms: $longestHoldMilliseconds,
                    revision: $state['revision'],
                )
                : ReceptionActivityStateData::inactive($state['revision']);
        });
    }

    /**
     * 判断传入顺序号是否晚于当前活动记录。
     *
     * @param  array{active: bool, expires_at: int, sequence: ?int}|null  $current
     */
    private function acceptsSequence(?array $current, int $sequence): bool
    {
        if ($current === null || $current['sequence'] === null) {
            return true;
        }

        return $sequence > $current['sequence'];
    }

    /**
     * 读取会话活动集合及其聚合状态版本。
     *
     * @return array{revision: int, activities: array<string, array{active: bool, expires_at: int, sequence: ?int}>}
     */
    private function readState(string $conversationId): array
    {
        return $this->cache()->get($this->stateKey($conversationId), [
            'revision' => 0,
            'activities' => [],
        ]);
    }

    /**
     * 保存会话活动集合及其聚合状态版本。
     *
     * @param  array{revision: int, activities: array<string, array{active: bool, expires_at: int, sequence: ?int}>}  $state
     */
    private function saveState(string $conversationId, array $state): void
    {
        $this->cache()->put($this->stateKey($conversationId), $state, self::STATE_TTL_SECONDS);
    }

    /**
     * 移除已经超过租约期限的活动来源。
     *
     * @param  array<string, array{active: bool, expires_at: int, sequence: ?int}>  $activities
     * @return array<string, array{active: bool, expires_at: int, sequence: ?int}>
     */
    private function pruneExpiredActivities(array $activities): array
    {
        $now = $this->nowMilliseconds();

        return array_filter(
            $activities,
            static fn (array $activity): bool => $activity['expires_at'] > $now,
        );
    }

    /**
     * 生成晚于当前状态和当前时间的活动版本。
     */
    private function nextRevision(int $currentRevision): int
    {
        return max($currentRevision + 1, $this->nowMilliseconds());
    }

    /**
     * 在会话级缓存锁内串行读写聚合状态。
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withLock(string $conversationId, callable $callback): mixed
    {
        return $this->cache()->lock($this->lockKey($conversationId), 5)->block(5, $callback);
    }

    /**
     * 返回默认缓存仓库。
     */
    private function cache(): CacheRepository
    {
        return Cache::store();
    }

    /**
     * 返回当前毫秒时间戳。
     */
    private function nowMilliseconds(): int
    {
        return now()->getPreciseTimestamp(3);
    }

    /**
     * 返回会话聚合活动状态缓存键。
     */
    private function stateKey(string $conversationId): string
    {
        return 'reception:activity:aggregate:'.$conversationId;
    }

    /**
     * 返回会话聚合活动状态锁键。
     */
    private function lockKey(string $conversationId): string
    {
        return 'reception:activity:lock:'.$conversationId;
    }
}
