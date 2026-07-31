<?php

use App\Actions\Reception\AppendVisitorMessageAction;
use App\Data\Channel\Web\ChannelWebSettingsData;
use App\Data\Reception\ReceptionStateData;
use App\Enums\ContactType;
use App\Enums\ConversationEntryMode;
use App\Enums\ConversationStatus;
use App\Enums\IdentityType;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\ContactIdentity;
use App\Models\Conversation;
use App\Models\ReceptionPlan;
use App\Models\ReceptionPlanVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {});

function signedChannelFor(string $secret = 'test-secret-supersecret-xxxxxxxxxx'): Channel
{
    $app = createSystemSettings();

    // 系统模型池中有可用接待对话模型即可让渠道判定为可用。
    makeAiModel();

    $plan = ReceptionPlan::factory()->create([
        'name' => '签名接待方案-'.Str::lower(Str::random(6)),
    ]);
    $version = ReceptionPlanVersion::factory()
        ->for($plan, 'plan')
        ->create();

    return Channel::factory()->create([
        'reception_plan_id' => $version->reception_plan_id,
        'settings' => ChannelWebSettingsData::defaults([
            'user_token_secret' => $secret,
        ]),
    ]);
}

function makeSignedUserToken(string $secret, array $claims): string
{
    $base64Url = static fn (string $input): string => rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $headerB64 = $base64Url(json_encode($header));
    $payloadB64 = $base64Url(json_encode($claims));
    $signature = hash_hmac('sha256', $headerB64.'.'.$payloadB64, $secret, true);

    return $headerB64.'.'.$payloadB64.'.'.$base64Url($signature);
}

/** 通过真实访客消息解析签名身份并创建接待会话。 */
function appendSignedVisitorMessage(Channel $channel, ?string $sessionToken, ?string $userToken): ReceptionStateData
{
    return app(AppendVisitorMessageAction::class)->handle(
        channelCode: $channel->code,
        sessionToken: $sessionToken,
        content: '签名访客测试消息',
        entryMode: ConversationEntryMode::Standalone,
        userToken: $userToken,
    );
}

test('签名 user_token 通过时联系人按 external_id 解析并写入展示名', function () {
    $channel = signedChannelFor();
    $secret = $channel->settings->user_token_secret;
    $token = makeSignedUserToken($secret, [
        'sub' => 'crm:user_42',
        'name' => '黎博士',
        'email' => 'Li@Example.com',
        'iat' => time() - 5,
        'exp' => time() + 3600,
    ]);

    $started = appendSignedVisitorMessage($channel, null, $token);

    $contact = Contact::query()->firstOrFail();
    $externalIdentity = ContactIdentity::query()
        ->where('contact_id', $contact->id)
        ->where('type', IdentityType::ExternalId)
        ->first();
    $emailIdentity = ContactIdentity::query()
        ->where('contact_id', $contact->id)
        ->where('type', IdentityType::Email)
        ->first();

    expect($started->session_token)->not->toBeEmpty()
        ->and($contact->type)->toBe(ContactType::Contact)
        ->and($contact->name)->toBe('黎博士')
        ->and($externalIdentity?->value)->toBe('crm:user_42')
        ->and($externalIdentity?->namespace)->toBe('')
        ->and($emailIdentity?->value)->toBe('li@example.com')
        ->and($contact->fresh()->primary_email)->toBe('li@example.com');
});

test('同一签名访客多次进入时复用同一联系人', function () {
    $channel = signedChannelFor();
    $secret = $channel->settings->user_token_secret;
    $token = makeSignedUserToken($secret, ['sub' => 'crm:user_42', 'iat' => time() - 5, 'exp' => time() + 3600]);

    appendSignedVisitorMessage($channel, null, $token);
    appendSignedVisitorMessage($channel, null, $token);

    expect(Contact::query()->count())->toBe(1)
        ->and(ContactIdentity::query()->where('type', IdentityType::ExternalId)->count())->toBe(1);
});

test('签名错误时使用 session 身份', function () {
    $channel = signedChannelFor();
    $bogusToken = makeSignedUserToken('different-secret-xxxxxxxxxxxxxxx', ['sub' => 'crm:user_42', 'exp' => time() + 3600]);

    appendSignedVisitorMessage($channel, null, $bogusToken);

    expect(ContactIdentity::query()->where('type', IdentityType::ExternalId)->count())->toBe(0)
        ->and(ContactIdentity::query()->where('type', IdentityType::Session)->count())->toBe(1)
        ->and(Contact::query()->firstOrFail()->type)->toBe(ContactType::Visitor);
});

test('签名过期时使用 session 身份', function () {
    $channel = signedChannelFor();
    $token = makeSignedUserToken($channel->settings->user_token_secret, ['sub' => 'crm:user_42', 'exp' => time() - 3600]);

    appendSignedVisitorMessage($channel, null, $token);

    expect(ContactIdentity::query()->where('type', IdentityType::ExternalId)->count())->toBe(0)
        ->and(ContactIdentity::query()->where('type', IdentityType::Session)->count())->toBe(1);
});

test('签名过期时不能读取签名身份的已有会话', function () {
    $channel = signedChannelFor();
    $secret = $channel->settings->user_token_secret;
    $validToken = makeSignedUserToken($secret, [
        'sub' => 'crm:user_42',
        'exp' => time() + 3600,
    ]);

    $state = appendSignedVisitorMessage($channel, null, $validToken);
    $expiredToken = makeSignedUserToken($secret, [
        'sub' => 'crm:user_42',
        'exp' => time() - 3600,
    ]);

    $this->getJson("/api/chat/{$channel->code}/state", [
        'Authorization' => "Bearer {$expiredToken}",
        'X-Helmdesk-Visitor-Token' => $state->session_token,
    ])->assertOk()
        ->assertJsonPath('conversation_id', null);
});

test('未配置 user_token_secret 时即便传 token 也忽略', function () {
    $channel = signedChannelFor('');
    $token = makeSignedUserToken('any-secret', ['sub' => 'crm:user_42', 'exp' => time() + 3600]);

    appendSignedVisitorMessage($channel, null, $token);

    expect(ContactIdentity::query()->where('type', IdentityType::ExternalId)->count())->toBe(0);
});

test('签名 token 邮箱被另一联系人占用时不强行抢占', function () {
    $channel = signedChannelFor();
    $secret = $channel->settings->user_token_secret;

    $otherContact = Contact::factory()->create([]);
    ContactIdentity::query()->create([
        'contact_id' => $otherContact->id,
        'type' => IdentityType::Email,
        'namespace' => '',
        'value' => 'occupied@example.com',
        'display_value' => 'occupied@example.com',
    ]);

    $token = makeSignedUserToken($secret, [
        'sub' => 'crm:user_99',
        'email' => 'occupied@example.com',
        'exp' => time() + 3600,
    ]);

    appendSignedVisitorMessage($channel, null, $token);

    $signedContact = Contact::query()
        ->whereHas('identities', fn ($query) => $query->where('type', IdentityType::ExternalId)->where('value', 'crm:user_99'))
        ->firstOrFail();

    $emailIdentitiesOnSignedContact = ContactIdentity::query()
        ->where('contact_id', $signedContact->id)
        ->where('type', IdentityType::Email)
        ->count();

    expect($emailIdentitiesOnSignedContact)->toBe(0)
        ->and($signedContact->primary_email)->toBeNull();
});

test('签名访客提交评价命中其签名会话', function () {
    Bus::fake();
    $channel = signedChannelFor();
    $token = makeSignedUserToken($channel->settings->user_token_secret, [
        'sub' => 'crm:user_42',
        'exp' => time() + 3600,
    ]);

    $state = $this->postJson("/api/chat/{$channel->code}/messages", [
        'content' => '需要帮助',
    ], [
        'Authorization' => "Bearer {$token}",
    ])->assertOk();
    $conversationId = $state->json('conversation_id');

    // 关闭会话使其可评价。
    Conversation::query()->whereKey($conversationId)->update([
        'status' => ConversationStatus::Closed,
        'closed_at' => now(),
    ]);

    $this->postJson("/api/chat/{$channel->code}/rating", [
        'score' => 'positive',
        'comment' => '解决得很快',
    ], [
        'Authorization' => "Bearer {$token}",
    ])->assertSuccessful()
        ->assertJsonPath('rating.score', 'positive')
        ->assertCookie("helmdesk_visitor_{$channel->code}");

    $this->assertDatabaseHas('conversation_ratings', [
        'conversation_id' => $conversationId,
        'score' => 'positive',
        'comment' => '解决得很快',
    ]);
});

test('签名访客同时存在 session 会话时评价命中签名会话', function () {
    Bus::fake();
    $channel = signedChannelFor();
    $token = makeSignedUserToken($channel->settings->user_token_secret, [
        'sub' => 'crm:user_42',
        'exp' => time() + 3600,
    ]);

    // 未签名访客发送消息后建立 session 身份会话。
    $anon = $this->postJson("/api/chat/{$channel->code}/messages", [
        'content' => '匿名访客问题',
    ])->assertOk();
    $sessionToken = $anon->json('session_token');
    $sessionConversationId = $anon->json('conversation_id');

    // 同一浏览器带上签名身份发送消息后建立签名会话。
    $signed = $this->postJson("/api/chat/{$channel->code}/messages", [
        'content' => '签名访客问题',
    ], [
        'Authorization' => "Bearer {$token}",
        'X-Helmdesk-Visitor-Token' => $sessionToken,
    ])->assertOk();
    $signedConversationId = $signed->json('conversation_id');
    expect($signedConversationId)->not->toBe($sessionConversationId);

    Conversation::query()->whereKey($signedConversationId)->update([
        'status' => ConversationStatus::Closed,
        'closed_at' => now(),
    ]);

    // 评价命中已关闭的签名会话，而非仍 Open 的 session 会话。
    $this->postJson("/api/chat/{$channel->code}/rating", [
        'score' => 'positive',
    ], [
        'Authorization' => "Bearer {$token}",
        'X-Helmdesk-Visitor-Token' => $sessionToken,
    ])->assertSuccessful()
        ->assertJsonPath('rating.score', 'positive');

    $this->assertDatabaseHas('conversation_ratings', ['conversation_id' => $signedConversationId]);
    $this->assertDatabaseMissing('conversation_ratings', ['conversation_id' => $sessionConversationId]);
});

test('签名访客 token 过期后仍能对既有已关闭会话补交评价', function () {
    Bus::fake();
    $channel = signedChannelFor();
    $secret = $channel->settings->user_token_secret;

    $validToken = makeSignedUserToken($secret, ['sub' => 'crm:user_42', 'exp' => time() + 3600]);
    $state = $this->postJson("/api/chat/{$channel->code}/messages", [
        'content' => '需要帮助',
    ], [
        'Authorization' => "Bearer {$validToken}",
    ])->assertOk();
    $conversationId = $state->json('conversation_id');

    Conversation::query()->whereKey($conversationId)->update([
        'status' => ConversationStatus::Closed,
        'closed_at' => now(),
    ]);

    // 已关闭会话允许使用签名有效但已过期的 token 提交评价。
    $expiredToken = makeSignedUserToken($secret, ['sub' => 'crm:user_42', 'exp' => time() - 3600]);
    $this->postJson("/api/chat/{$channel->code}/rating", [
        'score' => 'negative',
        'comment' => '过期后补评价',
    ], [
        'Authorization' => "Bearer {$expiredToken}",
    ])->assertSuccessful()
        ->assertJsonPath('rating.score', 'negative')
        ->assertCookie("helmdesk_visitor_{$channel->code}");

    $this->assertDatabaseHas('conversation_ratings', [
        'conversation_id' => $conversationId,
        'score' => 'negative',
        'comment' => '过期后补评价',
    ]);
});
