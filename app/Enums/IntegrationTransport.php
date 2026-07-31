<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 集成（Integration）工具来源的传输协议。
 * StreamableHttp 用于 MCP provider；Http 用于自有业务系统 provider（普通 HTTP/JSON 请求）。
 */
enum IntegrationTransport: string implements LabeledEnum
{
    case StreamableHttp = 'streamable_http';
    case Http = 'http';

    public function label(): string
    {
        return match ($this) {
            self::StreamableHttp => __('integration.transports.streamable_http'),
            self::Http => __('integration.transports.http'),
        };
    }
}
