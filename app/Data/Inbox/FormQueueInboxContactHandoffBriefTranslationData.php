<?php

namespace App\Data\Inbox;

use App\Enums\ReceptionLanguage;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * 收件箱右侧接手简报的翻译提交数据。
 */
class FormQueueInboxContactHandoffBriefTranslationData extends Data
{
    /**
     * 创建联系人接手简报补翻请求数据。
     */
    public function __construct(
        public string $target_locale,
        public bool $force = false,
    ) {}

    /**
     * 返回表单校验规则。
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'target_locale' => ['required', Rule::enum(ReceptionLanguage::class)],
            'force' => ['boolean'],
        ];
    }
}
