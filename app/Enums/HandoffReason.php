<?php

namespace App\Enums;

/**
 * 转人工原因。AI 接待工具的 reason 由模型自由生成，入口经 fromAiInput() 收窄到本枚举：
 * 未知或空值一律归一化为 ai_requested，避免脏值落库以及下游按 reason 分支时缺分支。
 * ai_unavailable 由系统在「AI 不可用」兜底路径写入，不在 AI 工具可选项内。
 */
enum HandoffReason: string
{
    case UserRequested = 'user_requested';
    case AiRequested = 'ai_requested';
    case LowConfidence = 'low_confidence';
    case ToolFailure = 'tool_failure';
    case PolicyRequired = 'policy_required';
    case AiUnavailable = 'ai_unavailable';

    /**
     * 把 AI 工具自由生成的 reason 文本收窄为枚举；未知或空值归一化为 ai_requested。
     */
    public static function fromAiInput(?string $raw): self
    {
        return self::tryFrom(trim((string) $raw)) ?? self::AiRequested;
    }

    /**
     * AI 接待工具可在 reason 字段中选择的原因（不含系统兜底的 ai_unavailable）。
     *
     * @return list<self>
     */
    public static function aiSelectableCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $reason): bool => $reason !== self::AiUnavailable,
        ));
    }

    /**
     * AI 可选原因的值列表，用于拼装工具描述。
     *
     * @return list<string>
     */
    public static function aiSelectableValues(): array
    {
        return array_map(static fn (self $reason): string => $reason->value, self::aiSelectableCases());
    }
}
