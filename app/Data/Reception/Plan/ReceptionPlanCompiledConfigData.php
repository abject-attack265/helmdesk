<?php

namespace App\Data\Reception\Plan;

use LogicException;
use Spatie\LaravelData\Data;

/**
 * 接待方案版本的运行时编译产物（reception_plan_versions.compiled_config）。
 * 由 CompileReceptionPlanAction 在发布时产出，LoadReceptionRuntimeAction 在接待运行时消费；
 * 通过 ReceptionPlanVersion::compiledConfig() 类型化访问，禁止直接读原始数组。
 */
class ReceptionPlanCompiledConfigData extends Data
{
    /**
     * @param  string  $reception_instruction  接待 agent system prompt 的版本化基底（Persona + 全局指引）
     * @param  list<CompiledKnowledgeBaseData>  $knowledge_bases  方案级知识库快照
     * @param  list<CompiledIntegrationGrantData>  $integration_grants  方案级集成授权快照
     */
    public function __construct(
        public string $reception_instruction,
        public array $knowledge_bases,
        public array $integration_grants,
    ) {}

    /**
     * 从版本 compiled_config JSON 列恢复编译产物；缺少必需键说明写入侧有缺陷，尽早失败。
     * 只读取必需键，多余键自然忽略。
     *
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        foreach (['reception_instruction', 'knowledge_bases', 'integration_grants'] as $key) {
            if (! array_key_exists($key, $raw) || ($key !== 'reception_instruction' && ! is_array($raw[$key]))) {
                throw new LogicException("Reception plan compiled config [{$key}] is required.");
            }
        }

        return new self(
            reception_instruction: (string) $raw['reception_instruction'],
            knowledge_bases: array_map(CompiledKnowledgeBaseData::fromArray(...), array_values($raw['knowledge_bases'])),
            integration_grants: array_map(CompiledIntegrationGrantData::fromArray(...), array_values($raw['integration_grants'])),
        );
    }
}
