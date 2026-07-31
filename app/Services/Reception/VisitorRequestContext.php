<?php

namespace App\Services\Reception;

use App\Services\LocalePreference;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * 从访客侧 HTTP 请求提取接待端点所需的身份、会话与环境上下文。
 *
 * 访客身份与业务参数统一由 HTTP header 承载，
 * 会话以 cookie `helmdesk_visitor_<code>` 持久化（跨域 iframe / WebView 场景用 X-Helmdesk-Visitor-Token 头承载）。
 *
 * 入口形态分 standalone / widget，决定会话 cookie 的 SameSite 策略。
 */
class VisitorRequestContext
{
    public const string ENTRY_STANDALONE = 'standalone';

    public const string ENTRY_WIDGET = 'widget';

    private const string COOKIE_PREFIX = 'helmdesk_visitor_';

    private const string SESSION_TOKEN_PATTERN = '/^[a-z0-9]{32}$/';

    private const string QUERY_KEY_PATTERN = '/^[a-zA-Z0-9_.-]{1,64}$/';

    private const int MAX_QUERY_PARAM_COUNT = 32;

    private const int MAX_QUERY_VALUE_LENGTH = 1024;

    private const int COOKIE_MAX_AGE = 60 * 60 * 24 * 365;

    /** 保留 key：接待协议自用，不进入业务参数映射。 */
    private const array QUERY_KEY_BLACKLIST = [
        'user_token',
        'session_token',
        '_token',
        'signature',
        'sig',
    ];

    /**
     * 承载从访客请求解析出的渠道 code、会话/身份 token、入口形态与环境/业务参数。
     *
     * @param  array<string, mixed>  $visitorEnvironment
     * @param  array<string, mixed>  $visitorClient
     * @param  array<string, string>  $queryParams
     */
    public function __construct(
        public readonly string $code,
        public readonly ?string $sessionToken,
        public readonly ?string $userToken,
        public readonly string $entryMode,
        public readonly array $visitorEnvironment,
        public readonly array $visitorClient,
        public readonly array $queryParams,
    ) {}

    /**
     * 从请求与渠道 code 解析访客上下文。
     */
    public static function fromRequest(Request $request, string $code): self
    {
        return new self(
            code: $code,
            sessionToken: self::resolveSessionToken($request, $code),
            userToken: self::resolveUserToken($request),
            entryMode: self::resolveEntryMode($request),
            visitorEnvironment: self::resolveVisitorEnvironment($request),
            visitorClient: self::resolveVisitorClient($request),
            queryParams: self::resolveQueryParams($request),
        );
    }

    /**
     * 当会话 token 变化时构造要写回的 cookie；无变化或非法返回 null。
     *
     * widget 且 HTTPS 用 SameSite=None，否则 Lax；httpOnly + secure(按请求)。
     */
    public function sessionCookie(Request $request, string $resolvedToken): ?Cookie
    {
        if ($resolvedToken === '' || ! preg_match(self::SESSION_TOKEN_PATTERN, $resolvedToken)) {
            return null;
        }
        if ($resolvedToken === $this->sessionToken) {
            return null;
        }

        $secure = $request->isSecure()
            || strtolower(trim((string) $request->header('X-Forwarded-Proto'))) === 'https';

        $sameSite = ($this->entryMode === self::ENTRY_WIDGET && $secure)
            ? Cookie::SAMESITE_NONE
            : Cookie::SAMESITE_LAX;

        return Cookie::create(
            name: self::COOKIE_PREFIX.$this->code,
            value: $resolvedToken,
            expire: now()->addSeconds(self::COOKIE_MAX_AGE),
            path: '/',
            secure: $secure,
            httpOnly: true,
            sameSite: $sameSite,
        );
    }

    /**
     * 解析会话 token：X-Helmdesk-Visitor-Token 头优先，其次渠道会话 cookie；非法 / 缺失返回 null。
     */
    private static function resolveSessionToken(Request $request, string $code): ?string
    {
        $header = self::normalizeSessionToken((string) $request->header('X-Helmdesk-Visitor-Token'));
        if ($header !== null) {
            return $header;
        }

        return self::normalizeSessionToken((string) $request->cookie(self::COOKIE_PREFIX.$code));
    }

    /**
     * 从 Authorization: Bearer 读取签名访客身份 token。
     */
    private static function resolveUserToken(Request $request): ?string
    {
        $header = trim((string) $request->header('Authorization'));
        if (stripos($header, 'bearer ') === 0) {
            $token = trim(substr($header, 7));

            return $token !== '' ? $token : null;
        }

        return null;
    }

    /**
     * 判断入口形态：X-Helmdesk-Entry-Mode == widget 则 widget，否则 standalone。
     */
    private static function resolveEntryMode(Request $request): string
    {
        return strcasecmp(trim((string) $request->header('X-Helmdesk-Entry-Mode')), self::ENTRY_WIDGET) === 0
            ? self::ENTRY_WIDGET
            : self::ENTRY_STANDALONE;
    }

    /**
     * 收集访客环境信息（语言、时区、国家、城市）。
     *
     * @return array<string, mixed>
     */
    private static function resolveVisitorEnvironment(Request $request): array
    {
        $payload = [];

        $locale = self::firstNonBlank(
            (string) $request->header('X-Helmdesk-Visitor-Locale'),
            LocalePreference::firstAcceptedLanguageValue($request->header('Accept-Language')) ?? '',
        );
        if ($locale !== null) {
            $payload['locale'] = $locale;
        }
        if ($timezone = self::firstNonBlank((string) $request->header('X-Helmdesk-Visitor-Timezone'))) {
            $payload['timezone'] = $timezone;
        }
        if ($country = self::firstNonBlank((string) $request->header('CF-IPCountry'), (string) $request->header('X-Vercel-IP-Country'), (string) $request->header('X-Appengine-Country'))) {
            $payload['country'] = $country;
        }
        if ($city = self::firstNonBlank((string) $request->header('CF-IPCity'), (string) $request->header('X-Vercel-IP-City'), (string) $request->header('X-Appengine-City'))) {
            $payload['city'] = $city;
        }

        return $payload;
    }

    /**
     * 收集 Web 访客行为/环境原始信号（UA、IP、当前页/来源/落地页、浏览器语言）。
     *
     * @return array<string, mixed>
     */
    private static function resolveVisitorClient(Request $request): array
    {
        $payload = [];

        if ($ua = trim((string) $request->userAgent())) {
            $payload['user_agent'] = $ua;
        }
        if ($ip = trim((string) $request->ip())) {
            $payload['ip_address'] = $ip;
        }
        if ($lang = self::firstNonBlank((string) $request->header('X-Helmdesk-Visitor-Locale'), LocalePreference::firstAcceptedLanguageValue($request->header('Accept-Language')) ?? '')) {
            $payload['browser_language'] = $lang;
        }
        if ($url = self::firstNonBlank((string) $request->header('X-Helmdesk-Visitor-Url'))) {
            $payload['current_url'] = $url;
        }
        if ($referrer = self::firstNonBlank((string) $request->header('X-Helmdesk-Visitor-Referrer'), (string) $request->header('Referer'))) {
            $payload['referrer'] = $referrer;
        }
        if ($landing = self::firstNonBlank((string) $request->header('X-Helmdesk-Visitor-Landing'))) {
            $payload['landing_url'] = $landing;
        }
        if ($entry = self::firstNonBlank((string) $request->header('X-Helmdesk-Visitor-Entry'))) {
            $payload['entry_url'] = $entry;
        }

        return $payload;
    }

    /**
     * 解析 widget 宿主页通过 X-Helmdesk-Query-Params 头透传的业务参数 JSON。
     *
     * 过滤非法 key、黑名单 key，按数量/长度上限收敛。
     *
     * @return array<string, string>
     */
    private static function resolveQueryParams(Request $request): array
    {
        $raw = trim((string) $request->header('X-Helmdesk-Query-Params'));
        if ($raw === '' || strlen($raw) > 8192) {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $blacklist = array_flip(self::QUERY_KEY_BLACKLIST);
        $out = [];
        foreach ($decoded as $key => $value) {
            if (! is_string($key) || ! is_string($value)) {
                continue;
            }
            $trimmedKey = trim($key);
            if (! preg_match(self::QUERY_KEY_PATTERN, $trimmedKey) || isset($blacklist[strtolower($trimmedKey)])) {
                continue;
            }
            $clean = trim($value);
            if (strlen($clean) > self::MAX_QUERY_VALUE_LENGTH) {
                $clean = substr($clean, 0, self::MAX_QUERY_VALUE_LENGTH);
            }
            if ($clean === '') {
                continue;
            }
            $out[$trimmedKey] = $clean;
            if (count($out) >= self::MAX_QUERY_PARAM_COUNT) {
                break;
            }
        }

        return $out;
    }

    /**
     * 校验会话 token 格式（[a-z0-9]{32}），合法返回之，否则 null。
     */
    private static function normalizeSessionToken(string $raw): ?string
    {
        $trimmed = trim($raw);

        return preg_match(self::SESSION_TOKEN_PATTERN, $trimmed) ? $trimmed : null;
    }

    /**
     * 返回首个非空白字符串（已 trim）；都为空返回 null。
     */
    private static function firstNonBlank(string ...$values): ?string
    {
        foreach ($values as $value) {
            $trimmed = trim($value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }
}
