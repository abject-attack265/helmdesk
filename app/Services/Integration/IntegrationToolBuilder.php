<?php

namespace App\Services\Integration;

use App\Data\Integration\IntegrationToolContext;
use App\Data\Integration\IntegrationToolSourceRuntimeData;
use NeuronAI\Tools\Tool;

/**
 * 按运行时来源规格构建集成工具。
 */
class IntegrationToolBuilder
{
    /**
     * 注入 provider 注册表。
     */
    public function __construct(
        private IntegrationProviderRegistry $registry,
    ) {}

    /**
     * 构建工具集，并向各 provider 传递可选的联系人运行时上下文。
     *
     * @param  list<IntegrationToolSourceRuntimeData>  $toolSources
     * @return list<Tool>
     */
    public function build(array $toolSources, ?IntegrationToolContext $context = null): array
    {
        return array_map(
            static fn (array $entry): Tool => $entry['tool'],
            $this->buildWithSources($toolSources, $context),
        );
    }

    /**
     * 按工具来源规格构建工具，并保留每个工具所属的集成来源快照。
     *
     * @param  list<IntegrationToolSourceRuntimeData>  $toolSources
     * @return list<array{tool: Tool, source: IntegrationToolSourceRuntimeData}>
     */
    public function buildWithSources(array $toolSources, ?IntegrationToolContext $context = null): array
    {
        $entries = [];

        foreach ($toolSources as $source) {
            foreach ($this->registry->for($source->provider)->buildRuntimeTools($source, $context) as $tool) {
                $entries[] = [
                    'tool' => $tool,
                    'source' => $source,
                ];
            }
        }

        return $entries;
    }
}
