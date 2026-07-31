<?php

namespace App\Services\Reception;

use Jenssegers\Agent\Agent;

/**
 * 解析访客 User-Agent，派生浏览器、版本、操作系统平台与设备类型，
 * 供 Web 会话渠道上下文展示给坐席。封装 jenssegers/agent，调用方不直接依赖其 API。
 */
class UserAgentParser
{
    /**
     * 解析 UA 字符串；无法识别的字段返回 null。
     *
     * @return array{browser: ?string, browser_version: ?string, platform: ?string, device_type: ?string}
     */
    public function parse(string $userAgent): array
    {
        $agent = new Agent;
        $agent->setUserAgent($userAgent);

        $browser = $this->clean($agent->browser());

        return [
            'browser' => $browser,
            'browser_version' => $browser !== null ? $this->clean($agent->version($browser)) : null,
            'platform' => $this->clean($agent->platform()),
            'device_type' => $this->deviceType($agent),
        ];
    }

    /**
     * 按平板 > 移动 > 桌面优先级归类设备类型；无法判断时返回 null。
     */
    private function deviceType(Agent $agent): ?string
    {
        if ($agent->isTablet()) {
            return 'tablet';
        }

        if ($agent->isMobile()) {
            return 'mobile';
        }

        if ($agent->isDesktop()) {
            return 'desktop';
        }

        return null;
    }

    /**
     * jenssegers/agent 未识别时返回 false；统一规整为去空白的字符串或 null。
     */
    private function clean(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
