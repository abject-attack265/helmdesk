<?php

namespace App\Data\Reception\Form;

use App\Data\Reception\Config\ReceptionStrategyConfigData;
use App\Data\Reception\ServiceScenario\PlanIntegrationGrantData;
use App\Enums\ReceptionPersonaTone;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/** 接待方案详情页提交的完整配置。 */
class FormUpdateReceptionPlanData extends Data
{
    /**
     * 封装基础信息、接待设置、知识库和集成授权。
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public string $persona_display_name,
        public string $persona_tone,
        public string $global_instructions,
        /** @var array<string, mixed> */
        public array $strategy_config,
        /** @var list<string> */
        public array $knowledge_base_ids = [],
        /** @var list<PlanIntegrationGrantData> */
        #[DataCollectionOf(PlanIntegrationGrantData::class)]
        public array $integration_grants = [],
    ) {}

    /**
     * 返回接待方案编辑表单的校验规则。
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        $toneValues = array_map(static fn (ReceptionPersonaTone $t) => $t->value, ReceptionPersonaTone::cases());

        return array_merge([
            'name' => ['required', 'string', 'max:100', 'regex:/\S/'],
            'description' => ['nullable', 'string', 'max:500'],
            'persona_display_name' => ['required', 'string', 'max:100', 'regex:/\S/'],
            'persona_tone' => ['required', 'string', Rule::in($toneValues)],
            'global_instructions' => ['required', 'string', 'max:20000', 'regex:/\S/'],
            'knowledge_base_ids' => ['array'],
            'knowledge_base_ids.*' => ['string', 'ulid'],
            'integration_grants' => ['array'],
            'integration_grants.*.integration_id' => ['required', 'string', 'ulid'],
            'integration_grants.*.tool_names' => ['array'],
            'integration_grants.*.tool_names.*' => ['string'],
        ], ReceptionStrategyConfigData::formRules());
    }

    /**
     * 校验接待策略字段的跨字段约束。
     */
    public static function withValidator(Validator $validator): void
    {
        ReceptionStrategyConfigData::validateForm($validator);
    }
}
