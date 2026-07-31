<?php

namespace App\Services\Reception;

use App\Data\Integration\IntegrationToolContext;
use App\Data\Reception\Plan\CompiledKnowledgeBaseData;
use App\Data\Reception\Runtime\ReceptionRuntimeData;
use App\Data\Reception\Runtime\ReceptionToolEventDefinitionData;
use App\Services\Integration\IntegrationToolBuilder;
use App\Services\KnowledgeBase\KnowledgeSearchToolFactory;
use Illuminate\Support\Facades\Log;
use NeuronAI\Tools\Tool;

/**
 * 按接待运行时配置组装出口、知识库与集成工具，并生成可展示工具的事件定义。
 */
class ReceptionToolsetBuilder
{
    private const array RESERVED_TOOL_NAMES = [
        'respond',
        'handoff_to_human',
        'knowledge_search',
    ];

    /**
     * 注入知识库与集成工具构建器。
     */
    public function __construct(
        private readonly KnowledgeSearchToolFactory $knowledgeSearchTools,
        private readonly IntegrationToolBuilder $integrationTools,
    ) {}

    /**
     * 按运行时配置组装工具集；knowledge_search 的命中数经回调喂给接地探针。
     */
    public function build(
        Tool $respondTool,
        Tool $handoffTool,
        ReceptionRuntimeData $runtime,
        ReceptionGroundingProbe $groundingProbe,
    ): ReceptionToolset {
        $toolset = [$respondTool];
        $eventDefinitions = [];
        $usedToolNames = array_fill_keys(self::RESERVED_TOOL_NAMES, true);

        $knowledgeBaseIds = $runtime->knowledgeBaseIds();
        if ($knowledgeBaseIds !== []) {
            $knowledgeSearchTool = $this->knowledgeSearchTools->buildKnowledgeSearchTool(
                $knowledgeBaseIds,
                static function (int $hitCount) use ($groundingProbe): void {
                    $groundingProbe->recordSearch($hitCount);
                },
            );
            $toolset[] = $knowledgeSearchTool;
            $eventDefinitions[] = new ReceptionToolEventDefinitionData(
                tool_name: $knowledgeSearchTool->getName(),
                source_type: 'knowledge_base',
                source_names: array_map(
                    static fn (CompiledKnowledgeBaseData $knowledgeBase): string => $knowledgeBase->name,
                    $runtime->knowledge_bases,
                ),
                description: $knowledgeSearchTool->getDescription(),
            );
        }

        $context = new IntegrationToolContext(
            contact_external_id: $runtime->contact_external_id,
            conversation_id: $runtime->conversation_id,
            email: $runtime->contact_email,
        );
        foreach ($this->integrationTools->buildWithSources($runtime->integration_tool_sources, $context) as $entry) {
            $toolName = $entry['tool']->getName();
            if (isset($usedToolNames[$toolName])) {
                Log::warning('[reception] 集成工具名称冲突，已跳过', [
                    'integration_id' => $entry['source']->id,
                    'integration_name' => $entry['source']->name,
                    'tool' => $toolName,
                ]);

                continue;
            }

            $usedToolNames[$toolName] = true;
            $toolset[] = $entry['tool'];
            $eventDefinitions[] = new ReceptionToolEventDefinitionData(
                tool_name: $toolName,
                source_type: 'integration',
                source_names: [$entry['source']->name],
                description: $entry['tool']->getDescription(),
            );
        }

        $toolset[] = $handoffTool;

        return new ReceptionToolset(
            tools: $toolset,
            event_definitions: $eventDefinitions,
        );
    }
}
