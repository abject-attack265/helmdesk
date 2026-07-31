<?php

namespace App\Actions\Reception;

use App\Data\Conversation\ChannelContext\WebConversationChannelContextData;
use App\Models\Conversation;
use App\Services\Reception\UserAgentParser;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 把访客端透传的 Web 访客信号落到会话渠道上下文：
 * 首次建会话时写入入站快照（落地页/来源/UA 等）并由 UA 派生浏览器/OS/设备，
 * 后续恢复时更新当前页并追加浏览轨迹。
 *
 * geo_* 字段当前留空（暂未接入 IP 地理），后续可由边缘头或 GeoLite2 补齐。
 */
class CaptureWebConversationContextAction
{
    use AsAction;

    private const int URL_MAX = 2048;

    private const int TEXT_MAX = 1024;

    /**
     * 注入 UA 解析服务，用于在落库前补齐浏览器/OS/设备派生字段。
     */
    public function __construct(
        private readonly UserAgentParser $userAgentParser,
    ) {}

    /**
     * 写入或更新会话的 Web 渠道上下文，并按需追加一条浏览轨迹。
     *
     * @param  array<string, mixed>  $client  访客入口透传的原始信号：current_url/entry_url/landing_url/referrer/user_agent/ip_address/browser_language
     * @param  array<string, string>  $queryParams  已清洗的 URL/widget 参数，用于快照 utm/ref
     */
    public function handle(Conversation $conversation, array $client, array $queryParams, bool $created): void
    {
        $currentUrl = $this->url($client['current_url'] ?? null);
        $referrer = $this->url($client['referrer'] ?? null);

        $existing = $conversation->channel_context instanceof WebConversationChannelContextData
            ? $conversation->channel_context
            : null;

        if ($created || $existing === null) {
            $userAgent = $this->text($client['user_agent'] ?? null);
            $ua = $userAgent !== null
                ? $this->userAgentParser->parse($userAgent)
                : ['browser' => null, 'browser_version' => null, 'platform' => null, 'device_type' => null];

            $context = new WebConversationChannelContextData(
                current_url: $currentUrl,
                entry_url: $this->url($client['entry_url'] ?? null) ?? $currentUrl,
                landing_url: $this->url($client['landing_url'] ?? null),
                referrer: $referrer,
                user_agent: $userAgent,
                ip_address: $this->text($client['ip_address'] ?? null, 45),
                browser_language: $this->text($client['browser_language'] ?? null, 35),
                browser: $ua['browser'],
                browser_version: $ua['browser_version'],
                platform: $ua['platform'],
                device_type: $ua['device_type'],
                utm_source: $this->param($queryParams, 'utm_source'),
                utm_medium: $this->param($queryParams, 'utm_medium'),
                utm_campaign: $this->param($queryParams, 'utm_campaign'),
                ref: $this->param($queryParams, 'ref'),
                captured_at: Carbon::now()->toIso8601String(),
            );
        } else {
            // 恢复会话：保留入站快照，仅刷新当前页；旧快照缺 UA 时用本次 UA 补齐派生字段。
            $userAgent = $existing->user_agent ?? $this->text($client['user_agent'] ?? null);
            $ua = $existing->user_agent === null && $userAgent !== null
                ? $this->userAgentParser->parse($userAgent)
                : [
                    'browser' => $existing->browser,
                    'browser_version' => $existing->browser_version,
                    'platform' => $existing->platform,
                    'device_type' => $existing->device_type,
                ];

            $context = new WebConversationChannelContextData(
                current_url: $currentUrl ?? $existing->current_url,
                entry_url: $existing->entry_url,
                landing_url: $existing->landing_url,
                referrer: $existing->referrer,
                user_agent: $userAgent,
                ip_address: $existing->ip_address ?? $this->text($client['ip_address'] ?? null, 45),
                browser_language: $existing->browser_language ?? $this->text($client['browser_language'] ?? null, 35),
                browser: $ua['browser'],
                browser_version: $ua['browser_version'],
                platform: $ua['platform'],
                device_type: $ua['device_type'],
                geo_country: $existing->geo_country,
                geo_region: $existing->geo_region,
                geo_city: $existing->geo_city,
                utm_source: $existing->utm_source,
                utm_medium: $existing->utm_medium,
                utm_campaign: $existing->utm_campaign,
                ref: $existing->ref,
                captured_at: $existing->captured_at,
            );
        }

        $conversation->channel_context = $context;
        $conversation->save();

        $this->appendPageView($conversation, $currentUrl, $referrer);
    }

    /**
     * 追加一条浏览轨迹；与最近一条相同 URL 时跳过，避免重复。
     */
    private function appendPageView(Conversation $conversation, ?string $currentUrl, ?string $referrer): void
    {
        if ($currentUrl === null) {
            return;
        }

        $lastUrl = $conversation->pageViews()->reorder()->orderByDesc('viewed_at')->orderByDesc('id')->value('url');
        if ($lastUrl === $currentUrl) {
            return;
        }

        $conversation->pageViews()->create([
            'contact_id' => $conversation->contact_id,
            'url' => $currentUrl,
            'referrer' => $referrer,
            'viewed_at' => Carbon::now(),
        ]);
    }

    /**
     * 清洗 URL 类字段：去空白、空串归 null、超长截断。
     */
    private function url(mixed $value): ?string
    {
        return $this->text($value, self::URL_MAX);
    }

    /**
     * 清洗文本字段：仅接受非空字符串，去空白并按长度上限截断。
     */
    private function text(mixed $value, int $max = self::TEXT_MAX): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : mb_substr($trimmed, 0, $max);
    }

    /**
     * 从已清洗的查询参数里取一个值，缺失归 null。
     *
     * @param  array<string, string>  $queryParams
     */
    private function param(array $queryParams, string $key): ?string
    {
        $value = $queryParams[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
