<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * AI 模型用途：决定模型进入哪个运行时取用池。
 *
 * 在应用设置「AI 模型管理」页给模型勾用途，各业务场景按用途从「启用且凭据完整」的模型里
 * 按 weight 加权随机选主、失败 fallback。每个用途对应一种底层能力类型（见 modelType）。
 * 例外：embedding 不走用途池，其模型由「知识库引擎」页 pin 配置。
 */
enum AiModelPurpose: string implements LabeledEnum
{
    case ReceptionChat = 'reception_chat';
    case BackgroundTask = 'background_task';
    case Assistant = 'assistant';
    case Embedding = 'embedding';
    case Rerank = 'rerank';

    public function label(): string
    {
        return match ($this) {
            self::ReceptionChat => __('ai.model_purposes.reception_chat'),
            self::BackgroundTask => __('ai.model_purposes.background_task'),
            self::Assistant => __('ai.model_purposes.assistant'),
            self::Embedding => __('ai.model_purposes.embedding'),
            self::Rerank => __('ai.model_purposes.rerank'),
        };
    }

    /**
     * 该用途要求的底层模型能力类型。
     */
    public function modelType(): AiModelType
    {
        return match ($this) {
            self::ReceptionChat, self::BackgroundTask, self::Assistant => AiModelType::Llm,
            self::Embedding => AiModelType::Embedding,
            self::Rerank => AiModelType::Rerank,
        };
    }

    /**
     * 给定能力类型下允许标注的用途集合（用于校验「purposes 必须匹配 type」与前端按 type 渲染可选用途）。
     *
     * @return list<self>
     */
    public static function forType(AiModelType $type): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $purpose): bool => $purpose->modelType() === $type,
        ));
    }
}
