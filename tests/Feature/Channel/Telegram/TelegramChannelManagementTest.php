<?php

use App\Data\Channel\Telegram\ChannelTelegramSettingsData;
use App\Enums\ChannelType;
use App\Enums\ReceptionLanguage;
use App\Enums\TelegramWebhookMode;
use App\Enums\UserPermission;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\WithInstance;

require_once __DIR__.'/TelegramTestSupport.php';

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
    config(['app.url' => 'https://helmdesk.test']);
});

/**
 * 伪造 Telegram Bot API：getMe / setWebhook / deleteWebhook 全部成功。
 */
function fakeTelegramApiOk(): void
{
    Http::fake([
        '*/getMe' => Http::response(['ok' => true, 'result' => ['id' => 777000111, 'username' => 'helmdesk_bot', 'first_name' => 'HelmDesk']]),
        '*/setWebhook' => Http::response(['ok' => true, 'result' => true]),
        '*/deleteWebhook' => Http::response(['ok' => true, 'result' => true]),
    ]);
}

test('所有者可以先创建待配置的 Telegram 渠道', function () {
    $version = createTelegramDeployablePlanVersion($this->instance);

    $response = $this->actingAs($this->user)
        ->from(route('app.manage.channels.telegram.create', []))
        ->post(route('app.manage.channels.telegram.store', []), [
            'name' => '客服机器人',
            'description' => '官方 Telegram 客服',
            'reception_plan_id' => $version->reception_plan_id,
            'default_visitor_locale' => 'en',
        ]);

    $channel = Channel::query()->firstOrFail();

    $response->assertRedirect(route('app.manage.channels.telegram.show', [
        'channel' => $channel->id,
        'tab' => 'telegram',
    ]));

    $settings = $channel->settings;
    expect($channel->type)->toBe(ChannelType::Telegram)
        ->and($channel->name)->toBe('客服机器人')
        ->and($channel->code)->toStartWith('tg_')
        ->and($settings)->toBeInstanceOf(ChannelTelegramSettingsData::class)
        ->and($settings->bot_token)->toBeNull()
        ->and($settings->bot_username)->toBeNull()
        ->and($settings->bot_id)->toBeNull()
        ->and($settings->webhook_secret)->not->toBe('')
        ->and($settings->default_visitor_locale)->toBe(ReceptionLanguage::English)
        ->and($settings->webhook_mode)->toBe(TelegramWebhookMode::Direct)
        ->and($settings->webhook_registered_at)->toBeNull();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/getMe'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/setWebhook'));
});

test('详情页手动注册 webhook 成功后标记为已注册', function () {
    fakeTelegramApiOk();
    config(['app.url' => 'https://support.example.test']);

    $version = createTelegramDeployablePlanVersion($this->instance);
    $channel = Channel::factory()->telegram()->create([
        'reception_plan_id' => $version->reception_plan_id,
        'settings' => ChannelTelegramSettingsData::defaults([
            'bot_token' => '777000111:AAHk9_manual_register_token_abcdefghi',
            'webhook_secret' => 'secret_for_manual_register_test_000000',
            'bot_username' => 'helmdesk_bot',
            'bot_id' => 777000111,
        ]),
    ]);

    // 未注册时 webhook_active 为 false。
    expect($channel->settings->webhook_registered_at)->toBeNull();

    $this->actingAs($this->user)
        ->from(route('app.manage.channels.telegram.show', [
            'channel' => $channel->id,
        ]))
        ->post(route('app.manage.channels.telegram.webhook.register', [
            'channel' => $channel->id,
        ]))
        ->assertRedirect(route('app.manage.channels.telegram.show', [
            'channel' => $channel->id,
        ]));

    // 注册成功后写入注册时间，setWebhook 携带本渠道公网 URL 与 secret。
    expect($channel->fresh()->settings->webhook_registered_at)->not->toBeNull();
    Http::assertSent(function ($request) use ($channel) {
        return str_contains($request->url(), '/setWebhook')
            && $request['url'] === 'https://support.example.test/webhook/telegram/'.$channel->code
            && $request['secret_token'] === 'secret_for_manual_register_test_000000';
    });
});

test('手动连接会补齐尚未保存的机器人身份', function () {
    fakeTelegramApiOk();
    $version = createTelegramDeployablePlanVersion($this->instance);
    $channel = Channel::factory()->telegram()->create([
        'reception_plan_id' => $version->reception_plan_id,
        'settings' => ChannelTelegramSettingsData::defaults([
            'bot_token' => '777000111:AAIdentity_token_value_abcdefghijkl',
            'bot_id' => null,
            'bot_username' => null,
            'webhook_registered_at' => null,
        ]),
    ]);

    $this->actingAs($this->user)
        ->post(route('app.manage.channels.telegram.webhook.register', [
            'channel' => $channel->id,
        ]))
        ->assertRedirect();

    $settings = $channel->fresh()->settings;
    expect($settings->bot_id)->toBe(777000111)
        ->and($settings->bot_username)->toBe('helmdesk_bot')
        ->and($settings->webhook_registered_at)->not->toBeNull();
});

test('Telegram 渠道创建只要求基础字段', function () {
    createTelegramDeployablePlanVersion($this->instance);

    $this->actingAs($this->user)
        ->from(route('app.manage.channels.telegram.create', []))
        ->post(route('app.manage.channels.telegram.store', []), [
            'name' => '客服机器人',
        ])
        ->assertSessionHasErrors(['reception_plan_id', 'default_visitor_locale'])
        ->assertSessionDoesntHaveErrors(['bot_token', 'webhook_mode']);
});

test('检测 Bot Token 返回机器人信息', function () {
    fakeTelegramApiOk();

    $this->actingAs($this->user)
        ->postJson(route('app.manage.channels.telegram.bot-token.check', []), [
            'bot_token' => '777000111:AAHk9_test_token_value_abcdefghijklmno',
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'bot_username' => 'helmdesk_bot',
        ]);

    // 检测是只读操作，不产生渠道数据。
    expect(Channel::query()->count())->toBe(0);
});

test('检测无效 Bot Token 返回失败标记', function () {
    Http::fake([
        '*/getMe' => Http::response(['ok' => false, 'error_code' => 401, 'description' => 'Unauthorized'], 401),
    ]);

    $this->actingAs($this->user)
        ->postJson(route('app.manage.channels.telegram.bot-token.check', []), [
            'bot_token' => '111000111:AAHk9_wrong_token_value_abcdefghijklm',
        ])
        ->assertOk()
        ->assertJson(['success' => false, 'bot_username' => null]);
});

test('手动注册 webhook 失败时渠道保持未注册', function () {
    Http::fake([
        '*/deleteWebhook' => Http::response(['ok' => true, 'result' => true]),
        '*/setWebhook' => Http::response(['ok' => false, 'error_code' => 400, 'description' => 'Bad webhook: HTTPS url must be provided'], 400),
    ]);
    $version = createTelegramDeployablePlanVersion($this->instance);
    $channel = Channel::factory()->telegram()->create([
        'reception_plan_id' => $version->reception_plan_id,
        'settings' => ChannelTelegramSettingsData::defaults([
            'bot_token' => '777000111:AAHk9_failed_register_token_abcdefghi',
            'webhook_secret' => 'secret_for_failed_register_test_00000',
            'bot_username' => 'helmdesk_bot',
            'bot_id' => 777000111,
        ]),
    ]);

    $this->actingAs($this->user)
        ->from(route('app.manage.channels.telegram.show', [
            'channel' => $channel->id,
        ]))
        ->post(route('app.manage.channels.telegram.webhook.register', [
            'channel' => $channel->id,
        ]));

    // 注册失败不影响渠道存在，仍保持「未注册」状态等待重试。
    expect($channel->fresh()->trashed())->toBeFalse()
        ->and($channel->fresh()->settings->webhook_registered_at)->toBeNull();
});

test('列表页与详情页正常渲染', function () {
    fakeTelegramApiOk();
    $version = createTelegramDeployablePlanVersion($this->instance);
    $channel = Channel::factory()->telegram()->create([
        'reception_plan_id' => $version->reception_plan_id,
        'name' => '官方客服 Bot',
        'settings' => ChannelTelegramSettingsData::defaults([
            'bot_token' => '100000000:AARender_token_value_abcdefghijklmno',
            'webhook_secret' => 'secret_for_render_test_000000000000000',
            'webhook_registered_at' => now()->toIso8601String(),
        ]),
    ]);

    $this->actingAs($this->user)
        ->get(route('app.manage.channels.telegram.index', []))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('channel/telegram/List')
            ->has('channel_list', 1)
            ->where('channel_list.0.name', '官方客服 Bot')
            ->where('channel_list.0.webhook_active', true)
            ->where('channel_list.0.has_bot_token', true)
            ->missing('channel_list.0.bot_token')
        );

    $this->actingAs($this->user)
        ->get(route('app.manage.channels.telegram.show', [
            'channel' => $channel->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('channel/telegram/Show')
            ->where('telegram_channel.id', (string) $channel->id)
            ->where('telegram_channel.webhook_url', 'https://helmdesk.test/webhook/telegram/'.$channel->code)
            ->where('telegram_channel.has_bot_token', true)
            ->missing('telegram_channel.bot_token')
            ->has('form_options.reception_plan_options')
        );
});

test('Telegram 渠道只读成员不能读取 webhook 密钥', function () {
    $version = createTelegramDeployablePlanVersion($this->instance);
    $channel = Channel::factory()->telegram()->create([
        'reception_plan_id' => $version->reception_plan_id,
        'settings' => ChannelTelegramSettingsData::defaults([
            'bot_token' => '100000000:AAReadonly_token_value_abcdefghijkl',
            'webhook_secret' => 'readonly_webhook_secret_1234567890',
        ]),
    ]);

    $viewer = User::factory()->create([
        'permissions' => [UserPermission::ChannelsView->value],
    ]);
    attachMember($viewer);

    $this->actingAs($viewer)
        ->get(route('app.manage.channels.telegram.show', ['channel' => $channel->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('telegram_channel.has_bot_token', true)
            ->missing('telegram_channel.bot_token')
            ->where('telegram_channel.webhook_secret', null)
        );
});

test('所有者可以软删除 Telegram 渠道且不撤销 webhook', function () {
    fakeTelegramApiOk();
    $version = createTelegramDeployablePlanVersion($this->instance);
    $channel = Channel::factory()->telegram()->create([
        'reception_plan_id' => $version->reception_plan_id,
    ]);

    $this->actingAs($this->user)
        ->delete(route('app.manage.channels.telegram.destroy', [
            'channel' => $channel->id,
        ]))
        ->assertRedirect(route('app.manage.channels.telegram.index', []));

    expect($channel->fresh()->trashed())->toBeTrue()
        // 暂停只做本地软删除，不向 Telegram 撤销，注册标记保留供恢复后继续接收。
        ->and($channel->fresh()->settings->webhook_registered_at)->not->toBeNull();
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/deleteWebhook'));
});

test('未注册的 Telegram 渠道 webhook_active 为 false', function () {
    $version = createTelegramDeployablePlanVersion($this->instance);
    Channel::factory()->telegram()->create([
        'reception_plan_id' => $version->reception_plan_id,
        'name' => '尚未注册 Bot',
        'settings' => ChannelTelegramSettingsData::defaults([
            'webhook_secret' => 'secret_for_unregistered_state_00000000',
            'bot_username' => 'helmdesk_bot',
            'bot_id' => 777000111,
        ]),
    ]);

    $this->actingAs($this->user)
        ->get(route('app.manage.channels.telegram.index', []))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('channel/telegram/List')
            ->where('channel_list.0.name', '尚未注册 Bot')
            ->where('channel_list.0.webhook_active', false)
            ->where('channel_list.0.webhook_registered_at', null)
        );
});

test('回收站列出已删除的 Telegram 渠道', function () {
    fakeTelegramApiOk();
    $version = createTelegramDeployablePlanVersion($this->instance);
    $channel = Channel::factory()->telegram()->create([
        'reception_plan_id' => $version->reception_plan_id,
        'name' => '待恢复 Bot',
    ]);
    $channel->delete();

    $this->actingAs($this->user)
        ->get(route('app.manage.channels.telegram.trash', []))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('channel/telegram/Trash')
            ->has('trashed_channel_list', 1)
            ->where('trashed_channel_list.0.id', (string) $channel->id)
            ->where('trashed_channel_list.0.name', '待恢复 Bot')
        );
});

test('基本信息更新留空 Token 时保留原值', function () {
    fakeTelegramApiOk();
    $version = createTelegramDeployablePlanVersion($this->instance);
    $channel = Channel::factory()->telegram()->create([
        'reception_plan_id' => $version->reception_plan_id,
        'name' => '原始名称',
        'settings' => ChannelTelegramSettingsData::defaults([
            'bot_token' => '100000000:AAOld_token_value_abcdefghijklmnopq',
            'bot_id' => 100000000,
            'bot_username' => 'existing_bot',
            'webhook_registered_at' => now()->toIso8601String(),
        ]),
    ]);

    $this->actingAs($this->user)
        ->from(route('app.manage.channels.telegram.show', [
            'channel' => $channel->id,
        ]))
        ->put(route('app.manage.channels.telegram.basic.update', [
            'channel' => $channel->id,
        ]), [
            'name' => '改名后的 Bot',
            'reception_plan_id' => $version->reception_plan_id,
            'default_visitor_locale' => 'en',
            'webhook_mode' => 'direct',
            'visitor_message_ai_translation_enabled' => true,
            'translation_context_hint' => '保留产品名。',
        ])
        ->assertRedirect(route('app.manage.channels.telegram.show', [
            'channel' => $channel->id,
        ]));

    $fresh = $channel->fresh();
    expect($fresh->name)->toBe('改名后的 Bot')
        ->and($fresh->settings->bot_token)->toBe('100000000:AAOld_token_value_abcdefghijklmnopq')
        ->and($fresh->settings->visitor_message_ai_translation_enabled)->toBeTrue()
        ->and($fresh->settings->translation_context_hint)->toBe('保留产品名。');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/getMe'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/setWebhook'));
});

test('尚未设置 Token 的渠道保存时要求填写 Token', function () {
    $version = createTelegramDeployablePlanVersion($this->instance);
    $channel = Channel::factory()->telegram()->create([
        'reception_plan_id' => $version->reception_plan_id,
        'name' => '待连接机器人',
        'settings' => ChannelTelegramSettingsData::defaults([
            'bot_token' => null,
            'bot_username' => null,
            'bot_id' => null,
            'webhook_registered_at' => null,
        ]),
    ]);

    $this->actingAs($this->user)
        ->from(route('app.manage.channels.telegram.show', [
            'channel' => $channel->id,
        ]))
        ->put(route('app.manage.channels.telegram.basic.update', [
            'channel' => $channel->id,
        ]), [
            'name' => '尝试保存',
            'reception_plan_id' => $version->reception_plan_id,
            'default_visitor_locale' => 'zh-CN',
            'webhook_mode' => 'direct',
        ])
        ->assertSessionHasErrors(['bot_token']);

    expect($channel->fresh()->name)->toBe('待连接机器人');
});

test('基本信息更新填写新 Token 时校验、落库并重注册 webhook', function () {
    fakeTelegramApiOk();
    $version = createTelegramDeployablePlanVersion($this->instance);
    $channel = Channel::factory()->telegram()->create([
        'reception_plan_id' => $version->reception_plan_id,
        'settings' => ChannelTelegramSettingsData::defaults([
            'bot_token' => '100000000:AAOld_token_value_abcdefghijklmnopq',
            'bot_id' => 100000000,
            'bot_username' => 'existing_bot',
            'webhook_registered_at' => now()->toIso8601String(),
        ]),
    ]);

    $this->actingAs($this->user)
        ->from(route('app.manage.channels.telegram.show', [
            'channel' => $channel->id,
        ]))
        ->put(route('app.manage.channels.telegram.basic.update', [
            'channel' => $channel->id,
        ]), [
            'name' => $channel->name,
            'reception_plan_id' => $version->reception_plan_id,
            'default_visitor_locale' => 'zh-CN',
            'webhook_mode' => 'direct',
            'bot_token' => '777000111:AAHk9_new_rotated_token_abcdefghijklmn',
        ])
        ->assertRedirect(route('app.manage.channels.telegram.show', [
            'channel' => $channel->id,
        ]));

    $fresh = $channel->fresh();
    expect($fresh->settings->bot_token)->toBe('777000111:AAHk9_new_rotated_token_abcdefghijklmn')
        // 新 Token 重新 getMe 后回填机器人信息。
        ->and($fresh->settings->bot_username)->toBe('helmdesk_bot')
        ->and($fresh->settings->bot_id)->toBe(777000111)
        ->and($fresh->settings->webhook_registered_at)->not->toBeNull();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/getMe'));
    Http::assertSent(fn ($request) => str_contains($request->url(), '/setWebhook'));
});

test('网关托管模式下换 Token 只校验落库，不向 Telegram 注册 webhook', function () {
    fakeTelegramApiOk();
    $version = createTelegramDeployablePlanVersion($this->instance);
    $channel = Channel::factory()->telegram()->create([
        'reception_plan_id' => $version->reception_plan_id,
        'settings' => ChannelTelegramSettingsData::defaults([
            'bot_token' => '100000000:AAOld_token_value_abcdefghijklmnopq',
        ]),
    ]);

    $this->actingAs($this->user)
        ->put(route('app.manage.channels.telegram.basic.update', [
            'channel' => $channel->id,
        ]), [
            'name' => $channel->name,
            'reception_plan_id' => $version->reception_plan_id,
            'default_visitor_locale' => 'zh-CN',
            'webhook_mode' => 'gateway',
            'bot_token' => '777000111:AAHk9_new_rotated_token_abcdefghijklmn',
        ])
        ->assertRedirect();

    $fresh = $channel->fresh();
    expect($fresh->settings->bot_token)->toBe('777000111:AAHk9_new_rotated_token_abcdefghijklmn')
        ->and($fresh->settings->webhook_mode)->toBe(TelegramWebhookMode::Gateway)
        ->and($fresh->settings->webhook_registered_at)->toBeNull();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/getMe'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/setWebhook'));
});

test('基本信息更新提交原 Token 时不校验也不重注册', function () {
    fakeTelegramApiOk();
    $version = createTelegramDeployablePlanVersion($this->instance);
    $channel = Channel::factory()->telegram()->create([
        'reception_plan_id' => $version->reception_plan_id,
        'settings' => ChannelTelegramSettingsData::defaults([
            'bot_token' => '100000000:AAOld_token_value_abcdefghijklmnopq',
            'bot_id' => 100000000,
            'bot_username' => 'existing_bot',
            'webhook_registered_at' => now()->toIso8601String(),
        ]),
    ]);
    $originalRegisteredAt = $channel->settings->webhook_registered_at;

    $this->actingAs($this->user)
        ->put(route('app.manage.channels.telegram.basic.update', [
            'channel' => $channel->id,
        ]), [
            'name' => '改名后的 Bot',
            'reception_plan_id' => $version->reception_plan_id,
            'default_visitor_locale' => 'zh-CN',
            'webhook_mode' => 'direct',
            'bot_token' => '100000000:AAOld_token_value_abcdefghijklmnopq',
        ])
        ->assertRedirect(route('app.manage.channels.telegram.show', [
            'channel' => $channel->id,
        ]));

    $fresh = $channel->fresh();
    expect($fresh->name)->toBe('改名后的 Bot')
        ->and($fresh->settings->bot_token)->toBe('100000000:AAOld_token_value_abcdefghijklmnopq')
        ->and($fresh->settings->webhook_registered_at)->toBe($originalRegisteredAt);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/getMe'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/setWebhook'));
});

test('基本信息更新填写非法 Token 时拒绝且不改动渠道', function () {
    Http::fake([
        '*/getMe' => Http::response(['ok' => false, 'error_code' => 401, 'description' => 'Unauthorized'], 401),
    ]);
    $version = createTelegramDeployablePlanVersion($this->instance);
    $channel = Channel::factory()->telegram()->create([
        'reception_plan_id' => $version->reception_plan_id,
        'name' => '原始名称',
        'settings' => ChannelTelegramSettingsData::defaults([
            'bot_token' => '100000000:AAOld_token_value_abcdefghijklmnopq',
        ]),
    ]);

    $this->actingAs($this->user)
        ->from(route('app.manage.channels.telegram.show', [
            'channel' => $channel->id,
        ]))
        ->put(route('app.manage.channels.telegram.basic.update', [
            'channel' => $channel->id,
        ]), [
            'name' => '尝试改名',
            'reception_plan_id' => $version->reception_plan_id,
            'default_visitor_locale' => 'zh-CN',
            'webhook_mode' => 'direct',
            'bot_token' => '999000111:AAInvalid_token_value_abcdefghijklmno',
        ]);

    // Token 在落库前校验失败：渠道名称与 Token 都保持原样。
    $fresh = $channel->fresh();
    expect($fresh->name)->toBe('原始名称')
        ->and($fresh->settings->bot_token)->toBe('100000000:AAOld_token_value_abcdefghijklmnopq');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/setWebhook'));
});

test('已绑定到回收站渠道的机器人不能再次绑定', function () {
    fakeTelegramApiOk();
    $version = createTelegramDeployablePlanVersion($this->instance);
    $originalChannel = Channel::factory()->telegram()->create([
        'reception_plan_id' => $version->reception_plan_id,
        'settings' => ChannelTelegramSettingsData::defaults([
            'bot_token' => '777000111:AAOriginal_token_value_abcdefghijkl',
            'bot_id' => 777000111,
            'bot_username' => 'helmdesk_bot',
        ]),
    ]);
    $originalChannel->delete();

    $targetChannel = Channel::factory()->telegram()->create([
        'reception_plan_id' => $version->reception_plan_id,
        'name' => '另一个渠道',
        'settings' => ChannelTelegramSettingsData::defaults([
            'bot_token' => '888000222:AAExisting_token_value_abcdefghijk',
            'bot_id' => 888000222,
            'bot_username' => 'another_bot',
        ]),
    ]);
    $originalToken = $targetChannel->settings->bot_token;

    $this->actingAs($this->user)
        ->from(route('app.manage.channels.telegram.show', [
            'channel' => $targetChannel->id,
        ]))
        ->put(route('app.manage.channels.telegram.basic.update', [
            'channel' => $targetChannel->id,
        ]), [
            'name' => '尝试绑定同一机器人',
            'reception_plan_id' => $version->reception_plan_id,
            'default_visitor_locale' => 'zh-CN',
            'webhook_mode' => 'direct',
            'bot_token' => '777000111:AADuplicate_token_value_abcdefghijk',
        ])
        ->assertSessionHasErrors(['bot_token']);

    $fresh = $targetChannel->fresh();
    expect($fresh->name)->toBe('另一个渠道')
        ->and($fresh->settings->bot_token)->toBe($originalToken);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/setWebhook'));
});

test('直连注册失败后再次保存会自动重试', function () {
    Http::fake([
        '*/deleteWebhook' => Http::response(['ok' => true, 'result' => true]),
        '*/setWebhook' => Http::sequence()
            ->push(['ok' => false, 'error_code' => 400, 'description' => 'Bad webhook'], 400)
            ->push(['ok' => true, 'result' => true]),
    ]);
    $version = createTelegramDeployablePlanVersion($this->instance);
    $channel = Channel::factory()->telegram()->create([
        'reception_plan_id' => $version->reception_plan_id,
        'settings' => ChannelTelegramSettingsData::defaults([
            'bot_token' => '777000111:AARetry_token_value_abcdefghijklmnop',
            'bot_id' => 777000111,
            'bot_username' => 'helmdesk_bot',
            'webhook_registered_at' => null,
        ]),
    ]);
    $payload = [
        'name' => $channel->name,
        'reception_plan_id' => $version->reception_plan_id,
        'default_visitor_locale' => 'zh-CN',
        'webhook_mode' => 'direct',
        'bot_token' => '',
    ];

    $this->actingAs($this->user)
        ->put(route('app.manage.channels.telegram.basic.update', [
            'channel' => $channel->id,
        ]), $payload)
        ->assertStatus(422);

    expect($channel->fresh()->settings->webhook_registered_at)->toBeNull();

    $this->actingAs($this->user)
        ->put(route('app.manage.channels.telegram.basic.update', [
            'channel' => $channel->id,
        ]), $payload)
        ->assertRedirect(route('app.manage.channels.telegram.show', [
            'channel' => $channel->id,
        ]));

    expect($channel->fresh()->settings->webhook_registered_at)->not->toBeNull();
    Http::assertSentCount(4);
});

test('从回收站恢复 Telegram 渠道且不重注册 webhook', function () {
    fakeTelegramApiOk();
    $version = createTelegramDeployablePlanVersion($this->instance);
    $channel = Channel::factory()->telegram()->create([
        'reception_plan_id' => $version->reception_plan_id,
    ]);
    $channel->delete();

    $this->actingAs($this->user)
        ->put(route('app.manage.channels.telegram.restore', [
            'channel' => $channel->id,
        ]))
        ->assertRedirect(route('app.manage.channels.telegram.index', []));

    expect($channel->fresh()->trashed())->toBeFalse()
        // 恢复不重注册：暂停时未撤销 webhook，注册标记保留，恢复后即为已注册。
        ->and($channel->fresh()->settings->webhook_registered_at)->not->toBeNull();
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/setWebhook'));
});
