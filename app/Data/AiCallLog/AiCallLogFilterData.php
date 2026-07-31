<?php

namespace App\Data\AiCallLog;

use Spatie\LaravelData\Data;

/**
 * 「AI 调用日志」列表当前筛选状态回显。
 * 由 ShowAiCallLogListAction 下发，前端据此初始化筛选控件并与 URL 同步。
 * search 用于按定位钥匙反查（记录 / 会话 / 消息 / 联系人 ID），
 * 或按输入 / 输出内容与 turn ID 做子串匹配。
 */
class AiCallLogFilterData extends Data
{
    public function __construct(
        public ?string $purpose = null,
        public ?string $status = null,
        public ?string $search = null,
    ) {}
}
