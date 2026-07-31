<?php

use App\Actions\Channel\Web\Public\ResolvePublicWebChannelBootstrapAction;
use App\Actions\Channel\Web\Public\ResolvePublicWebChannelWidgetBootstrapAction;
use App\Data\Channel\Web\ChannelWebSettingsData;
use App\Enums\WebChannelVisitorIdentityMode;
use App\Enums\WebChannelWidgetEntryMode;
use App\Enums\WebChannelWidgetEntryPosition;
use App\Enums\WebChannelWidgetEntryStyle;
use App\Enums\WebChannelWidgetIconSize;
use App\Models\Attachment;
use App\Models\Channel;
use App\Models\ReceptionPlan;
use App\Models\ReceptionPlanVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->planVersion = createPublicBootstrapReceptionPlanVersion();
});
/**
 * 创建一个接待方案版本，并按需 seed 全局接待对话模型，模拟公开 bootstrap 接口期望的"渠道可用"前提。
 *
 * 系统模型池默认 seed 一个可用的接待对话模型；传入 ['is_active' => false] 可模拟系统无可用模型的降级场景。
 */
function createPublicBootstrapReceptionPlanVersion(array $modelAttributes = []): ReceptionPlanVersion
{
    $isActive = $modelAttributes['is_active'] ?? true;
    makeAiModel(isActive: $isActive);

    $plan = ReceptionPlan::factory()->create([
        'name' => '公开 Bootstrap 方案-'.Str::lower(Str::random(6)),
    ]);

    return ReceptionPlanVersion::factory()
        ->for($plan, 'plan')
        ->create();
}

/**
 * 创建公开启动测试用的网站渠道入口图标附件。
 */
function createPublicBootstrapChannelIcon(array $attributes = []): Attachment
{
    return Attachment::factory()->create(array_merge([
        'object_key' => 'uploads/'.Str::lower(Str::random(8)).'.png',
        'original_name' => 'widget-icon.png',
        'mime_type' => 'image/png',
        'extension' => 'png',
        'byte_size' => 1024,
        'purpose' => 'channel_icon',
        'status' => 'uploaded',
    ], $attributes));
}

test('活跃频道解析公开独立启动数据', function () {
    $channel = Channel::factory()->create([
        'reception_plan_id' => $this->planVersion->reception_plan_id,
        'name' => '官网主站',
        'settings' => [
            'visitor_interface' => [
                'site_name' => 'HelmDesk 官网',
                'greeting_message' => '欢迎咨询',
                'theme_color' => '#14B8A6',
                'home_mode_enabled' => true,
                'home_welcome_message' => '欢迎你！',
            ],
            'suggestions' => [
                'enabled' => true,
                'items' => ['怎么收费？', '如何接入？'],
            ],
        ],
    ]);

    $result = ResolvePublicWebChannelBootstrapAction::run($channel->code);

    expect($result->toArray())->toMatchArray([
        'code' => $channel->code,
        'site_name' => 'HelmDesk 官网',
        'greeting_message' => '欢迎咨询',
        'theme_color' => '#14B8A6',
        'home_mode_enabled' => true,
        'home_welcome_message' => '欢迎你！',
        'suggestions' => [
            'enabled' => true,
            'items' => ['怎么收费？', '如何接入？'],
        ],
    ]);
});

test('公开启动在 code 不存在时仍然 404', function () {
    expect(fn () => ResolvePublicWebChannelBootstrapAction::run('wch_unknownxxxxx'))
        ->toThrow(NotFoundHttpException::class);
});

test('软删除的网站渠道公开启动返回 paused 数据', function () {
    $channel = Channel::factory()->create([
        'reception_plan_id' => $this->planVersion->reception_plan_id,
        'name' => '已暂停渠道',
        'settings' => [
            'visitor_interface' => [
                'site_name' => '已暂停官网',
                'greeting_message' => '欢迎咨询',
            ],
        ],
    ]);
    $channel->delete();

    $standalone = ResolvePublicWebChannelBootstrapAction::run($channel->code);
    expect($standalone->paused)->toBeTrue()
        ->and($standalone->site_name)->toBe('已暂停官网');

    $widget = ResolvePublicWebChannelWidgetBootstrapAction::run($channel->code);
    expect($widget->paused)->toBeTrue();
});

test('软删除的渠道 widget bootstrap 跳过 embed host 校验和落库', function () {
    $channel = Channel::factory()->create([
        'reception_plan_id' => $this->planVersion->reception_plan_id,
        'name' => 'Paused Widget Channel',
        'settings' => ChannelWebSettingsData::defaults([
            'allowed_embed_hosts' => ['example.com'],
        ]),
    ]);
    $channel->delete();

    // 即使来源不在白名单也不会 403，且不会写入 first/last embed
    $result = ResolvePublicWebChannelWidgetBootstrapAction::run($channel->code, 'attacker.test');
    expect($result->paused)->toBeTrue();

    $channel->refresh();
    expect($channel->first_embed_host)->toBeNull()
        ->and($channel->last_embed_host)->toBeNull();
});

test('公开启动在接待方案模型失效时仍返回基础启动数据以便降级人工待接', function () {
    $invalidVersion = createPublicBootstrapReceptionPlanVersion(
        modelAttributes: ['is_active' => false],
    );

    $channel = Channel::factory()->create([
        'reception_plan_id' => $invalidVersion->reception_plan_id,
        'name' => '降级渠道',
    ]);

    $standalone = ResolvePublicWebChannelBootstrapAction::run($channel->code);
    $widget = ResolvePublicWebChannelWidgetBootstrapAction::run($channel->code);

    expect($standalone->code)->toBe($channel->code)
        ->and($standalone->site_name)->toBe('降级渠道')
        ->and($widget->code)->toBe($channel->code);
});

test('公开启动数据使用频道名称当站点名称为空时', function () {
    $channel = Channel::factory()->create([
        'reception_plan_id' => $this->planVersion->reception_plan_id,
        'name' => '帮助中心',
        'settings' => [
            'visitor_interface' => [
                'site_name' => null,
            ],
        ],
    ]);

    $result = ResolvePublicWebChannelBootstrapAction::run($channel->code);

    expect($result->site_name)->toBe('帮助中心');
});

test('原生桥接解析公开独立启动数据', function () {
    $channel = Channel::factory()->create([
        'reception_plan_id' => $this->planVersion->reception_plan_id,
        'name' => 'Native Bridge Channel',
    ]);

    $result = ResolvePublicWebChannelBootstrapAction::run($channel->code);

    expect($result->code)->toBe($channel->code)
        ->and($result->site_name)->toBe('Native Bridge Channel');
});

test('公开组件启动允许没有嵌入主机的不限域频道', function () {
    $channel = Channel::factory()->create([
        'reception_plan_id' => $this->planVersion->reception_plan_id,
        'name' => 'Widget Channel',
    ]);

    $result = ResolvePublicWebChannelWidgetBootstrapAction::run($channel->code);

    expect($result->code)->toBe($channel->code)
        ->and($result->site_name)->toBe('Widget Channel');
});

test('公开启动使用共享访客界面并保留入口样式', function () {
    $channel = Channel::factory()->create([
        'reception_plan_id' => $this->planVersion->reception_plan_id,
        'name' => '官网主站',
        'settings' => [
            'visitor_interface' => [
                'site_name' => '共享标题',
                'subtitle' => '共享副标题',
                'visitor_identity_mode' => WebChannelVisitorIdentityMode::UnifiedService->value,
                'service_display_name' => '共享客服',
                'greeting_message' => '共享欢迎语',
                'composer_placeholder' => '共享输入提示',
                'theme_color' => '#0EA5E9',
            ],
            'suggestions' => [
                'enabled' => true,
                'items' => ['共享问题'],
            ],
            'widget' => [
                'entry' => [
                    'position' => WebChannelWidgetEntryPosition::Left->value,
                    'style' => WebChannelWidgetEntryStyle::System->value,
                    'icon_size' => WebChannelWidgetIconSize::Small->value,
                    'bottom_offset' => 42,
                ],
            ],
        ],
    ]);

    $standalone = ResolvePublicWebChannelBootstrapAction::run($channel->code);
    $widget = ResolvePublicWebChannelWidgetBootstrapAction::run($channel->code);

    expect($standalone->site_name)->toBe('共享标题')
        ->and($standalone->subtitle)->toBe('共享副标题')
        ->and($standalone->assistant_name)->toBe('共享客服')
        ->and($standalone->greeting_message)->toBe('共享欢迎语')
        ->and($standalone->composer->placeholder)->toBe('共享输入提示')
        ->and($standalone->suggestions->items)->toBe(['共享问题'])
        ->and($standalone->theme_color)->toBe('#0EA5E9')
        ->and($widget->site_name)->toBe('共享标题')
        ->and($widget->subtitle)->toBe('共享副标题')
        ->and($widget->assistant_name)->toBe('共享客服')
        ->and($widget->greeting_message)->toBe('共享欢迎语')
        ->and($widget->composer->placeholder)->toBe('共享输入提示')
        ->and($widget->suggestions->items)->toBe(['共享问题'])
        ->and($widget->theme_color)->toBe('#0EA5E9')
        ->and($standalone->entry)->toBeNull()
        ->and($standalone->mobile_fullscreen_enabled)->toBeNull()
        ->and($widget->entry?->mode)->toBe(WebChannelWidgetEntryMode::Bubble)
        ->and($widget->entry?->position)->toBe(WebChannelWidgetEntryPosition::Left)
        ->and($widget->entry?->style)->toBe(WebChannelWidgetEntryStyle::System)
        ->and($widget->entry?->icon_size)->toBe(WebChannelWidgetIconSize::Small)
        ->and($widget->entry?->bottom_offset)->toBe(42)
        ->and($widget->mobile_fullscreen_enabled)->toBeTrue();
});

test('公开组件启动会下发自定义入口图标地址', function () {
    $defaultIcon = createPublicBootstrapChannelIcon();
    $activeIcon = createPublicBootstrapChannelIcon();
    $channel = Channel::factory()->create([
        'reception_plan_id' => $this->planVersion->reception_plan_id,
        'name' => 'Custom Widget Icon Channel',
        'settings' => ChannelWebSettingsData::defaults([
            'widget' => [
                'entry' => [
                    'mode' => WebChannelWidgetEntryMode::Bubble->value,
                    'position' => WebChannelWidgetEntryPosition::Right->value,
                    'style' => WebChannelWidgetEntryStyle::Custom->value,
                    'icon_size' => WebChannelWidgetIconSize::Large->value,
                    'bottom_offset' => 30,
                    'default_icon_id' => $defaultIcon->id,
                    'active_icon_id' => $activeIcon->id,
                ],
            ],
        ]),
    ]);

    $widget = ResolvePublicWebChannelWidgetBootstrapAction::run($channel->code);
    expect($widget->entry?->default_icon_url)->not->toBeNull()
        ->and($widget->entry?->active_icon_url)->not->toBeNull();

    // 临时签名 URL 每次生成的签名时间戳都不同，只断言指向同一对象路径，避免跨秒签名差异导致偶发失败。
    $envelope = app(ResolvePublicWebChannelWidgetBootstrapAction::class)->resolveEnvelope($channel->code, 'example.com');
    expect(Str::before((string) $envelope->channel->entry?->default_icon_url, '?'))
        ->toBe(Str::before((string) $widget->entry?->default_icon_url, '?'))
        ->and(Str::before((string) $envelope->channel->entry?->active_icon_url, '?'))
        ->toBe(Str::before((string) $widget->entry?->active_icon_url, '?'));
});

test('原生桥接解析公开组件启动数据', function () {
    $channel = Channel::factory()->create([
        'reception_plan_id' => $this->planVersion->reception_plan_id,
        'name' => 'Native Widget Channel',
    ]);

    $envelope = app(ResolvePublicWebChannelWidgetBootstrapAction::class)->resolveEnvelope($channel->code, 'example.com');

    expect($envelope->channel->code)->toBe($channel->code)
        ->and($envelope->channel->site_name)->toBe('Native Widget Channel')
        ->and($envelope->cors_allow_origin)->toBe('*');
});

test('原生桥接在配置 allowed_embed_hosts 时返回精确匹配的 CORS 策略并落库 embed host', function () {
    $channel = Channel::factory()->create([
        'reception_plan_id' => $this->planVersion->reception_plan_id,
        'name' => 'Whitelist Widget Channel',
        'settings' => ChannelWebSettingsData::defaults([
            'allowed_embed_hosts' => ['example.com', '*.docs.example.com'],
        ]),
    ]);

    $envelope = app(ResolvePublicWebChannelWidgetBootstrapAction::class)->resolveEnvelope($channel->code, 'example.com');

    expect($envelope->cors_allow_origin)->toBe('match');

    $channel->refresh();
    expect($channel->first_embed_host)->toBe('example.com')
        ->and($channel->first_embed_at)->not->toBeNull()
        ->and($channel->last_embed_host)->toBe('example.com')
        ->and($channel->last_embed_at)->not->toBeNull();
});

test('原生桥接在配置 allowed_embed_hosts 时拒绝白名单外的 embed host', function () {
    $channel = Channel::factory()->create([
        'reception_plan_id' => $this->planVersion->reception_plan_id,
        'name' => 'Whitelist Widget Channel',
        'settings' => ChannelWebSettingsData::defaults([
            'allowed_embed_hosts' => ['example.com'],
        ]),
    ]);

    expect(fn () => app(ResolvePublicWebChannelWidgetBootstrapAction::class)->resolveEnvelope($channel->code, 'attacker.test'))
        ->toThrow(AccessDeniedHttpException::class);

    $channel->refresh();
    expect($channel->first_embed_host)->toBeNull()
        ->and($channel->last_embed_host)->toBeNull();
});

test('通配 allowed_embed_hosts 命中二级域并支持落库 last embed host', function () {
    $channel = Channel::factory()->create([
        'reception_plan_id' => $this->planVersion->reception_plan_id,
        'name' => 'Wildcard Widget Channel',
        'settings' => ChannelWebSettingsData::defaults([
            'allowed_embed_hosts' => ['*.example.com'],
        ]),
        'first_embed_host' => 'old.example.com',
        'first_embed_at' => now()->subDay(),
        'last_embed_host' => 'old.example.com',
        'last_embed_at' => now()->subDay(),
    ]);

    app(ResolvePublicWebChannelWidgetBootstrapAction::class)->resolveEnvelope($channel->code, 'docs.example.com');

    $channel->refresh();
    expect($channel->first_embed_host)->toBe('old.example.com')
        ->and($channel->last_embed_host)->toBe('docs.example.com');
});

test('管理后台预览触发的 bootstrap 不会污染 embed host 跟踪字段', function () {
    config(['app.url' => 'https://admin.helmdesk.test']);

    $channel = Channel::factory()->create([
        'reception_plan_id' => $this->planVersion->reception_plan_id,
        'name' => 'Preview Channel',
    ]);

    app(ResolvePublicWebChannelWidgetBootstrapAction::class)->resolveEnvelope($channel->code, 'admin.helmdesk.test');

    $channel->refresh();
    expect($channel->first_embed_host)->toBeNull()
        ->and($channel->first_embed_at)->toBeNull()
        ->and($channel->last_embed_host)->toBeNull()
        ->and($channel->last_embed_at)->toBeNull();
});

test('管理后台预览触发的 bootstrap 即使 app.url 带端口也会被跳过', function () {
    config(['app.url' => 'http://localhost:8080']);

    $channel = Channel::factory()->create([
        'reception_plan_id' => $this->planVersion->reception_plan_id,
        'name' => 'Preview Channel With Port',
    ]);

    app(ResolvePublicWebChannelWidgetBootstrapAction::class)->resolveEnvelope($channel->code, 'localhost:8080');

    $channel->refresh();
    expect($channel->last_embed_host)->toBeNull();
});
