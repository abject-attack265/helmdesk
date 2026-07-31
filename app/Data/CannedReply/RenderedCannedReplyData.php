<?php

namespace App\Data\CannedReply;

use Spatie\LaravelData\Data;

/**
 * 快捷回复渲染结果。
 * UseAndRenderCannedReplyAction 返回给前端 composer 直接插入到光标位置；
 * warnings 列举遇到的 AI token 或缺值字段，方便前端做 toast 提示。
 */
class RenderedCannedReplyData extends Data
{
    /**
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public string $id,
        public string $rendered_content,
        public string $original_content,
        public array $warnings,
        public int $usage_count,
        public ?string $last_used_at,
    ) {}
}
