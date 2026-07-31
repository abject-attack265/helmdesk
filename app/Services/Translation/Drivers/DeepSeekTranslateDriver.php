<?php

namespace App\Services\Translation\Drivers;

/**
 * 使用固定模型和关闭思考模式的 DeepSeek 翻译驱动。
 */
class DeepSeekTranslateDriver extends OpenAiCompatibleTranslateDriver
{
    /**
     * DeepSeek OpenAI 兼容 API 根地址。
     */
    protected function baseUrl(): string
    {
        return 'https://api.deepseek.com/v1';
    }

    /**
     * 固定使用的 DeepSeek 模型。
     */
    protected function model(): string
    {
        return 'deepseek-v4-flash';
    }

    /**
     * 关闭翻译场景不需要的思考模式。
     *
     * @return array<string, mixed>
     */
    protected function vendorOptions(): array
    {
        return ['thinking' => ['type' => 'disabled']];
    }
}
