<?php

namespace App\Data\AiProvider;

use Spatie\LaravelData\Data;

/**
 * 应用设置「编辑 AI 供应商」页 props。
 *
 * 由 ShowEditAiProviderPageAction 返回，传给 resources/js/pages/appSettings/aiProvider/Edit.vue：
 * provider 含脱敏后的凭据字段定义与遮掩值，前端据此渲染编辑表单与「测试连通」。
 */
class ShowEditAiProviderPagePropsData extends Data
{
    public function __construct(
        public AiProviderData $provider,
    ) {}
}
