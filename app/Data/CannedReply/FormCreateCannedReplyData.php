<?php

namespace App\Data\CannedReply;

use Spatie\LaravelData\Data;

/**
 * 创建快捷回复模版的表单数据。
 * 来自 resources/js/pages/cannedReplies/Index.vue 模态框的 Inertia 表单提交，
 * 由 CreateCannedReplyAction 校验并持久化。
 */
class FormCreateCannedReplyData extends Data
{
    public function __construct(
        public string $name,
        public string $content,
        public bool $is_personal,
        public ?string $shortcut = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', 'regex:/\S/'],
            'content' => ['required', 'string', 'max:5000'],
            'is_personal' => ['required', 'boolean'],
            'shortcut' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_\-]+$/'],
        ];
    }
}
