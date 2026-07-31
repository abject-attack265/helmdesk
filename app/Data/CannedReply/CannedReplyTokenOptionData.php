<?php

namespace App\Data\CannedReply;

use Spatie\LaravelData\Data;

/**
 * 快捷回复编辑页"插入变量"按钮列表项。
 * 由后端 CannedReplyVariableResolver::availableTokens() 转换得到，前端按 kind 分组渲染。
 */
class CannedReplyTokenOptionData extends Data
{
    public function __construct(
        public string $kind,
        public string $kind_label,
        public string $key,
        public string $token,
        public string $label,
    ) {}

    /**
     * @param  array{kind: string, kind_label: string, key: string, token: string, label: string}  $token
     */
    public static function fromArray(array $token): self
    {
        return new self(
            kind: $token['kind'],
            kind_label: $token['kind_label'],
            key: $token['key'],
            token: $token['token'],
            label: $token['label'],
        );
    }
}
