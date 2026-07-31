<?php

use App\Models\Channel;
use App\Models\ReceptionPlan;
use App\Models\ReceptionPlanVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->planVersion = createWidgetEmbedReceptionPlanVersion();
});
/**
 * 创建一个接待方案版本并 seed 全局接待对话模型，作为渠道可用的前提。
 */
function createWidgetEmbedReceptionPlanVersion(): ReceptionPlanVersion
{
    makeAiModel();

    $plan = ReceptionPlan::factory()->create([
        'name' => '小部件方案-'.Str::lower(Str::random(6)),
    ]);

    return ReceptionPlanVersion::factory()
        ->for($plan, 'plan')
        ->create();
}

/**
 * @param  array<string, mixed>  $visitorInterface
 * @param  array<string, mixed>  $settingsOverride
 */
function createWidgetEmbedChannel(ReceptionPlanVersion $planVersion, array $visitorInterface = [], array $settingsOverride = []): Channel
{
    return Channel::factory()->create([
        'reception_plan_id' => $planVersion->reception_plan_id,
        'name' => '官网小部件',
        'settings' => array_merge([
            'visitor_interface' => array_merge([
                'site_name' => 'HelmDesk 小部件',
                'theme_color' => '#14B8A6',
            ], $visitorInterface),
        ], $settingsOverride),
    ]);
}

test('小部件安装脚本以 JS 与短缓存返回', function () {
    $response = $this->get('/embed/widget.js')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/javascript')
        ->and($response->headers->get('Cache-Control'))->toContain('max-age=300')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    $response->assertSee('__HELMDESK_WIDGETS__', false);
});

test('小部件 iframe 页渲染渠道启动数据到 window.__HELMDESK_WIDGET__', function () {
    $channel = createWidgetEmbedChannel($this->planVersion);

    $this->get('/embed/widget/'.$channel->code)
        ->assertOk()
        ->assertSee('window.__HELMDESK_WIDGET__', false)
        ->assertSee($channel->code, false)
        ->assertSee('<title>HelmDesk 小部件</title>', false);
});

test('小部件 iframe 页未配置白名单时下发 frame-ancestors * 并占位 X-Frame-Options', function () {
    $channel = createWidgetEmbedChannel($this->planVersion);

    $response = $this->get('/embed/widget/'.$channel->code)->assertOk();

    expect($response->headers->get('Content-Security-Policy'))->toBe('frame-ancestors *')
        ->and($response->headers->get('X-Frame-Options'))->toBe('ALLOWALL')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('小部件 iframe 页按 allowed_embed_hosts 下发 frame-ancestors 白名单', function () {
    $channel = createWidgetEmbedChannel(
        $this->planVersion,
        settingsOverride: ['allowed_embed_hosts' => ['example.com', '.foo.com']],
    );

    $response = $this->get('/embed/widget/'.$channel->code, [
        'Origin' => 'https://example.com',
    ])->assertOk();

    expect($response->headers->get('Content-Security-Policy'))->toBe('frame-ancestors example.com *.foo.com');
});

test('小部件 bootstrap 在未配置白名单时返回 channel 与通配 CORS', function () {
    $channel = createWidgetEmbedChannel($this->planVersion);

    $response = $this->getJson('/embed/widget/'.$channel->code.'/bootstrap')
        ->assertOk()
        ->assertJsonPath('channel.code', $channel->code);

    expect($response->headers->get('Access-Control-Allow-Origin'))->toBe('*')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('小部件 bootstrap 在配置白名单且来源命中时回写实际 Origin', function () {
    $channel = createWidgetEmbedChannel(
        $this->planVersion,
        settingsOverride: ['allowed_embed_hosts' => ['example.com']],
    );

    $response = $this->getJson('/embed/widget/'.$channel->code.'/bootstrap', [
        'Origin' => 'https://example.com',
    ])->assertOk();

    // Vary 由框架 Inertia 中间件接管（且响应本身 no-store），此处只校验安全相关的 ACAO 精确回写。
    expect($response->headers->get('Access-Control-Allow-Origin'))->toBe('https://example.com');
});

test('小部件 iframe 页对不合法 code 返回 404', function () {
    $this->get('/embed/widget/not-a-valid-code')->assertNotFound();
});

test('小部件 bootstrap 对不存在的渠道返回 404', function () {
    $this->getJson('/embed/widget/wch_nonexistentxx/bootstrap')->assertNotFound();
});
