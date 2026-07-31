<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * AI 供应商底层协议，决定接待运行时用哪种原生 agentic model 适配。
 * 仅保留三种原生通道：其它品牌（DeepSeek/Qwen/Azure/Ollama 等）在品牌目录里映射到这三者之一 + 预设 base_url。
 */
enum AiProviderProtocol: string implements LabeledEnum
{
    case OpenAI = 'openai';
    case Anthropic = 'anthropic';
    case Gemini = 'gemini';

    /**
     * 返回协议展示名称（协议为专有名词，保留英文原文）。
     */
    public function label(): string
    {
        return match ($this) {
            self::OpenAI => 'OpenAI Responses',
            self::Anthropic => 'Anthropic Messages',
            self::Gemini => 'Google Gemini',
        };
    }
}
