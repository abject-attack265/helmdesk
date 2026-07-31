<?php

namespace App\Data\Integration;

use Spatie\LaravelData\Data;

/**
 * 集成创建与编辑表单使用的连接测试响应。
 */
class IntegrationConnectionCheckData extends Data
{
    /**
     * 保存连接状态和可直接展示的结果消息。
     */
    public function __construct(
        public bool $success,
        public string $message,
    ) {}
}
