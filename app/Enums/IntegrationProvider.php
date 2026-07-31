<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 集成（Integration）的 provider 类型。
 * Mcp 接 MCP 协议服务；BusinessSystem 接自有业务系统（HelmDesk 自定义 HTTP/JSON 工具契约）；
 * MockBusinessSystem 为演示用的进程内假业务系统（不发 HTTP、不可由用户手动创建，仅 seed 命令建）；
 * 后续电商等平台在此扩展。
 */
enum IntegrationProvider: string implements LabeledEnum
{
    case Mcp = 'mcp';
    case BusinessSystem = 'business_system';
    case MockBusinessSystem = 'mock_business_system';

    public function label(): string
    {
        return match ($this) {
            self::Mcp => __('integration.providers.mcp'),
            self::BusinessSystem => __('integration.providers.business_system'),
            self::MockBusinessSystem => __('integration.providers.mock_business_system'),
        };
    }

    /**
     * 每个 provider 对应唯一传输协议：MCP 走 streamable_http，业务系统走普通 http。
     * MockBusinessSystem 为进程内实现、不发请求，transport 仅占位（映射为 http）。
     */
    public function transport(): IntegrationTransport
    {
        return match ($this) {
            self::Mcp => IntegrationTransport::StreamableHttp,
            self::BusinessSystem, self::MockBusinessSystem => IntegrationTransport::Http,
        };
    }

    /**
     * 用户可在创建页选择 / 手动创建的 provider（排除仅由 seed 命令接入的 MockBusinessSystem）。
     *
     * 创建页选项与表单 provider 校验同源此方法，确保 mock 不出现在下拉、也不能手动提交创建。
     *
     * @return list<self>
     */
    public static function userSelectableCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $provider): bool => $provider !== self::MockBusinessSystem,
        ));
    }
}
