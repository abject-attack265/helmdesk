<?php

namespace App\Data\Reception\Plan;

use App\Models\ReceptionPlanIntegrationGrant;
use LogicException;
use Spatie\LaravelData\Data;

/**
 * 接待方案版本 compiled_config 中的集成授权快照项。
 * 编译时由方案的 integration 授权固化，运行时据此反查当前可用的集成并挂载工具：
 * tool_names 为白名单快照（空=该集成全部已启用工具），运行时再与「当前 is_enabled 且未下线」求交集。
 */
class CompiledIntegrationGrantData extends Data
{
    /**
     * @param  list<string>  $tool_names  工具名白名单快照（空=该集成全部已启用工具）
     */
    public function __construct(
        public string $integration_id,
        public string $integration_name,
        public array $tool_names,
    ) {}

    /**
     * 编译时从授权模型（含 integration 关联）固化快照。
     */
    public static function fromGrant(ReceptionPlanIntegrationGrant $grant): self
    {
        $whitelist = is_array($grant->tool_whitelist) ? $grant->tool_whitelist : [];

        return new self(
            integration_id: (string) $grant->integration_id,
            integration_name: (string) ($grant->integration?->name ?? ''),
            tool_names: array_values(array_filter(
                array_map(static fn ($name): string => (string) $name, $whitelist),
                static fn (string $name): bool => $name !== '',
            )),
        );
    }

    /**
     * 从版本 compiled_config 中恢复快照项。
     *
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        if (! isset($raw['integration_id']) || ! is_string($raw['integration_id']) || $raw['integration_id'] === '') {
            throw new LogicException('Compiled integration grant snapshot must contain integration_id.');
        }

        $rawToolNames = isset($raw['tool_names']) && is_array($raw['tool_names']) ? $raw['tool_names'] : [];

        return new self(
            integration_id: $raw['integration_id'],
            integration_name: (string) ($raw['integration_name'] ?? ''),
            tool_names: array_values(array_filter(
                array_map(static fn ($name): string => (string) $name, $rawToolNames),
                static fn (string $name): bool => $name !== '',
            )),
        );
    }
}
