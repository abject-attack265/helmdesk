<?php

namespace App\Actions\Reception;

use App\Actions\Reception\Plan\ResolvePlanIntegrationToolSourcesAction;
use App\Data\AiRuntime\RuntimeModelCandidateData;
use App\Data\Integration\IntegrationToolSourceRuntimeData;
use App\Data\Reception\Config\ReceptionStrategyConfigData;
use App\Data\Reception\HumanServiceStatusData;
use App\Data\Reception\Plan\CompiledIntegrationGrantData;
use App\Data\Reception\Plan\ReceptionPlanCompiledConfigData;
use App\Data\Reception\Runtime\ReceptionRuntimeData;
use App\Enums\AiModelPurpose;
use App\Enums\AttributeType;
use App\Enums\ConversationInboxStatus;
use App\Enums\IdentityType;
use App\Enums\ReceptionLanguage;
use App\Enums\ReceptionRuntimeUnavailableReason;
use App\Models\AiModel;
use App\Models\AttributeDefinition;
use App\Models\ContactAttributeValue;
use App\Models\Conversation;
use App\Services\AiRuntime\AiModelPool;
use App\Services\LocalePreference;
use App\Services\Reception\ChannelActivePlanVersionResolver;
use App\Services\Reception\ChannelTeammateAvailability;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 加载接待会话所需的模型、提示词、知识库和集成工具。
 */
class LoadReceptionRuntimeAction
{
    use AsAction;

    /**
     * 注入人工可用状态、集成工具、模型池和方案版本解析服务。
     */
    public function __construct(
        private readonly ChannelTeammateAvailability $teammateAvailability,
        private readonly ResolvePlanIntegrationToolSourcesAction $resolveIntegrationToolSources,
        private readonly AiModelPool $modelPool,
        private readonly ChannelActivePlanVersionResolver $activePlanVersionResolver,
    ) {}

    /**
     * 加载指定会话当前有效的接待运行时配置。
     */
    public function handle(string $conversationId): ReceptionRuntimeData
    {
        $conversation = Conversation::query()->find($conversationId);
        if ($conversation === null) {
            throw new NotFoundHttpException;
        }

        if ($conversation->inbox_status !== ConversationInboxStatus::AiHandling) {
            return ReceptionRuntimeData::unavailable(ReceptionRuntimeUnavailableReason::TakenOver);
        }

        // 每轮按渠道解析「所绑方案的最新已发布版本」，跨轮跟随最新配置；
        // 轮内以本次取到的快照为准，保证单轮连贯。
        $conversation->loadMissing('channel', 'contact.identities');
        $channel = $conversation->channel;
        if ($channel === null) {
            return ReceptionRuntimeData::unavailable(ReceptionRuntimeUnavailableReason::NoPlan);
        }

        $version = $this->activePlanVersionResolver->currentVersionForChannel($channel);
        if ($version === null) {
            return ReceptionRuntimeData::unavailable(ReceptionRuntimeUnavailableReason::NoPlan);
        }

        // 刷新会话级「当前驱动版本」，让收件箱展示反映正在生效的版本（逐消息精确审计另由 AI 消息上的版本快照承担）。
        if ((string) $conversation->reception_plan_version_id !== (string) $version->id) {
            $conversation->reception_plan_version_id = $version->id;
            $conversation->save();
        }

        $status = $this->teammateAvailability->serviceStatus($channel, locale: LocalePreference::DEFAULT_LARAVEL_LOCALE);

        return $this->buildRuntime(
            compiled: $version->compiledConfig(),
            strategyConfig: $version->strategyConfig(),
            conversation: $conversation,
            language: ReceptionLanguage::from($conversation->visitor_locale),
            teammateStatus: $status,
            receptionPlanVersionId: (string) $version->id,
        );
    }

    /**
     * 从方案配置、会话上下文和人工服务状态组装运行时配置。
     */
    private function buildRuntime(
        ReceptionPlanCompiledConfigData $compiled,
        ReceptionStrategyConfigData $strategyConfig,
        Conversation $conversation,
        ReceptionLanguage $language,
        HumanServiceStatusData $teammateStatus,
        string $receptionPlanVersionId,
    ): ReceptionRuntimeData {
        $modelCandidates = $this->resolveModelCandidates(AiModelPurpose::ReceptionChat);
        if ($modelCandidates === []) {
            return ReceptionRuntimeData::unavailable(ReceptionRuntimeUnavailableReason::NoModel);
        }

        $locale = LocalePreference::DEFAULT_LARAVEL_LOCALE;
        $runtimeInstructions = [
            $compiled->reception_instruction,
            $this->singleAgentOperatingInstruction($compiled->knowledge_bases !== []),
            $this->teammateAvailability->runtimeInstruction($teammateStatus, $locale),
        ];
        $importantInstruction = $this->importantContactInstruction($conversation, $strategyConfig);
        if ($importantInstruction !== null) {
            $runtimeInstructions[] = $importantInstruction;
        }
        $profileInstruction = $this->contactProfileInstruction($conversation);
        if ($profileInstruction !== null) {
            $runtimeInstructions[] = $profileInstruction;
        }
        $attributesInstruction = $this->contactAttributesInstruction($conversation);
        if ($attributesInstruction !== null) {
            $runtimeInstructions[] = $attributesInstruction;
        }
        // 语言指令放最后、紧邻生成位置，借近因效应强化遵循。
        $runtimeInstructions[] = $this->visitorLanguageInstruction($language);
        $systemPrompt = trim(implode("\n\n", $runtimeInstructions));

        return new ReceptionRuntimeData(
            available: true,
            system_prompt: $systemPrompt,
            model_candidates: $modelCandidates,
            knowledge_bases: $compiled->knowledge_bases,
            integration_tool_sources: $this->resolveIntegrationToolSources($compiled->integration_grants),
            ai_unavailable_notice: $strategyConfig->ai_unavailable_notice,
            quote_visitor_message_enabled: $strategyConfig->quote_visitor_message_enabled,
            contact_external_id: $this->resolveContactExternalId($conversation),
            conversation_id: (string) $conversation->id,
            contact_email: $this->resolveContactEmail($conversation),
            reception_plan_version_id: $receptionPlanVersionId,
        );
    }

    /**
     * 返回当前会话联系人的主邮箱。
     */
    private function resolveContactEmail(Conversation $conversation): ?string
    {
        $email = $conversation->contact?->primary_email;

        return is_string($email) && $email !== '' ? $email : null;
    }

    /**
     * 取当前会话联系人的外部 ID（业务方传入的应用级单一标识）；无则 null。
     */
    private function resolveContactExternalId(Conversation $conversation): ?string
    {
        $identity = $conversation->contact?->identities
            ?->first(static fn ($identity): bool => $identity->type === IdentityType::ExternalId);

        $value = $identity?->value;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * 根据当前会话的联系人资料生成身份提示词。
     */
    private function contactProfileInstruction(Conversation $conversation): ?string
    {
        $contact = $conversation->contact;
        if ($contact === null) {
            return null;
        }

        $lines = [];

        if (filled($contact->name)) {
            $lines[] = '- 姓名：'.$contact->name;
        }

        if (filled($contact->primary_email)) {
            $lines[] = '- 邮箱：'.$contact->primary_email;
        }

        if (filled($contact->primary_phone)) {
            $lines[] = '- 电话：'.$contact->primary_phone;
        }

        $externalId = $this->resolveContactExternalId($conversation);
        if ($externalId !== null) {
            $lines[] = '- 外部 ID：'.$externalId;
        }

        if ($lines === []) {
            return null;
        }

        return implode("\n", [
            '[已知联系人信息]',
            '系统已识别当前联系人的以下身份信息，可直接参考，不要再向访客重复询问：',
            ...$lines,
        ]);
    }

    /**
     * 生成重点客户接待提示词。
     */
    private function importantContactInstruction(Conversation $conversation, ReceptionStrategyConfigData $strategyConfig): ?string
    {
        if (! $conversation->contact?->is_important) {
            return null;
        }

        $instructions = [];

        if ($strategyConfig->important_contact_ai_careful_reply_enabled) {
            $instructions[] = '回复时保持更高谨慎度，避免未经确认的承诺、补偿、退款、合同或法务判断。';
        }

        if ($strategyConfig->important_contact_ai_handoff_hint_enabled) {
            $instructions[] = '当问题涉及投诉、合同、退款、账号风险、工具失败或低置信度判断时，优先调用 handoff_to_human。';
        }

        if ($instructions === []) {
            return null;
        }

        return implode("\n", [
            '[重点客户接待要求]',
            '当前联系人被标记为重点客户。',
            ...$instructions,
            '不要主动告知访客其重点客户标记。',
        ]);
    }

    /**
     * 生成已知联系人属性提示词：只下发 is_ai_readable=true 的属性值，避免 AI 重复追问已知信息。
     */
    private function contactAttributesInstruction(Conversation $conversation): ?string
    {
        if ($conversation->contact_id === null) {
            return null;
        }

        $values = ContactAttributeValue::query()

            ->where('contact_id', $conversation->contact_id)
            ->with('definition')
            ->get()
            ->filter(fn (ContactAttributeValue $value): bool => $value->definition !== null
                && ! $value->definition->trashed()
                && $value->definition->is_ai_readable)
            ->sortBy(fn (ContactAttributeValue $value): int => $value->definition->display_order)
            ->values();

        $lines = [];
        foreach ($values as $value) {
            $display = $this->formatAttributeValue($value->definition, $value->value());
            if ($display === null) {
                continue;
            }
            $lines[] = '- '.$value->definition->name.'：'.$display;
        }

        if ($lines === []) {
            return null;
        }

        return implode("\n", [
            '[已知联系人属性]',
            '系统已记录该联系人的以下属性，可直接参考，不要再向访客重复询问：',
            ...$lines,
        ]);
    }

    /**
     * 把属性值格式化成提示词中的可读文本（选项 code 映射回 label，布尔输出中文是/否）。
     */
    private function formatAttributeValue(AttributeDefinition $definition, mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        $optionLabels = collect($definition->config['options'] ?? [])->pluck('label', 'code');

        return match ($definition->type) {
            AttributeType::Boolean => $value === true ? '是' : '否',
            AttributeType::SingleSelect => is_string($value) ? (string) ($optionLabels[$value] ?? $value) : null,
            AttributeType::MultiSelect => is_array($value)
                ? implode('、', array_map(static fn (mixed $code): string => (string) ($optionLabels[$code] ?? $code), $value))
                : null,
            default => is_scalar($value) ? (string) $value : null,
        };
    }

    /**
     * 按集成授权快照返回当前可用的工具来源。
     *
     * @param  list<CompiledIntegrationGrantData>  $grants
     * @return list<IntegrationToolSourceRuntimeData>
     */
    private function resolveIntegrationToolSources(array $grants): array
    {
        if ($grants === []) {
            return [];
        }

        return $this->resolveIntegrationToolSources->handle($grants);
    }

    /**
     * 从全局用途池解析该用途下的模型候选列表（运行时逐个尝试、失败轮询）。
     *
     * @return list<RuntimeModelCandidateData>
     */
    private function resolveModelCandidates(AiModelPurpose $purpose): array
    {
        return $this->modelPool->modelsForPurpose($purpose)
            ->map(static fn (AiModel $model): RuntimeModelCandidateData => RuntimeModelCandidateData::fromModel($model))
            ->all();
    }

    /**
     * 构造与当前挂载工具一致的单 Agent 操作规则。
     *
     * 规则在每轮运行时生成，使进行中会话立即使用当前工具集。
     */
    private function singleAgentOperatingInstruction(bool $hasKnowledgeBases): string
    {
        $lines = [
            '[操作规则]',
            '- 只有纯问候、寒暄、道谢这类社交性消息，才直接回复、不调用任何工具。',
        ];

        if ($hasKnowledgeBases) {
            $lines[] = '- 访客只要提出任何问题、诉求，或描述了某个情况/麻烦，本轮第一步就必须先调用 knowledge_search，再决定怎么处理——哪怕你觉得自己已经知道答案，也要先查。知识库沉淀了本应用的业务事实与人工坐席的处理口径，常包含你训练数据里没有的、针对本业务的特定做法；命中后优先参照其内容与话术作答，未命中再凭你自己的判断处理。';
            $lines[] = '- 涉及具体操作步骤 / 使用方法（怎么操作、在哪里设置、按什么路径点选、如何开启或关闭某项功能等）时，必须以 knowledge_search 命中的内容为唯一依据：命中则严格照知识库描述回答，不自行增补或推演步骤；未命中、或知识库没有对应说明时，绝不可凭你自己的训练知识、或对同类产品的印象编造操作路径——你对本应用所服务的具体产品并无内置了解，改为如实告知访客你需要帮其确认，或调用 handoff_to_human 转人工。宁可不给步骤，也不要给一套可能并不存在的步骤。';
            $lines[] = '- 需要查资料时调用 knowledge_search 检索知识库；需要查询或办理业务时调用相应业务工具；拿到结果后用自然语言回复访客。';
        } else {
            $lines[] = '- 涉及具体操作步骤 / 使用方法（怎么操作、在哪里设置、按什么路径点选、如何开启或关闭某项功能等）时，绝不可凭你自己的训练知识、或对同类产品的印象编造操作路径——你对本应用所服务的具体产品并无内置了解，改为如实告知访客你需要帮其确认，或调用 handoff_to_human 转人工。宁可不给步骤，也不要给一套可能并不存在的步骤。';
            $lines[] = '- 需要查询或办理业务时调用相应业务工具；拿到结果后用自然语言回复访客。';
        }

        $toolNames = $hasKnowledgeBases ? 'knowledge_search / respond / handoff_to_human' : 'respond / handoff_to_human';

        return implode("\n", [
            ...$lines,
            '- 某一步较耗时，或需先向访客确认 / 补充信息时，可先调用 respond 发一条过渡消息（如「好的，我帮您查一下，请稍候」）再继续；本轮最终结论直接作为你的回复给出，不要用 respond 发送最终结论。',
            '- 工具失败、信息不足以判断、或访客主动要求人工时，调用 handoff_to_human，它会按营业时间与人工可用状态把通知直接送达访客。',
            "- 工具名（{$toolNames} 等）属内部信息，对访客不可见；表达得像是你自己在查询和处理，不要展示或提及任何内部工具与机制。",
        ]);
    }

    /**
     * 生成 AI 回复语言指令：默认用接待语言，之后主动跟随访客的语言或明确请求。
     *
     * 每条回复保持单一语言、不夹杂，避免直接复制与当前回复语言无关的工具原文。
     */
    private function visitorLanguageInstruction(ReceptionLanguage $language): string
    {
        $name = $language->label();

        return "默认接待语言：{$name}（用于首次问候，或还不确定访客用什么语言时）。".
            '之后主动跟随访客：访客用哪种语言写、或明确要求用某种语言，就改用那种语言回复，让对方用最舒服的语言沟通。'.
            '每条回复只用一种语言、绝不夹杂；知识库或工具返回结果是其他语言时，准确理解内容后用当前回复语言回答，'.
            '仅没有对应名称的品牌名、商标或 App 名称可保留原文。';
    }
}
