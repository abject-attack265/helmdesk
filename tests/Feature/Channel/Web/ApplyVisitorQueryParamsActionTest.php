<?php

use App\Actions\Channel\Web\ApplyVisitorQueryParamsAction;
use App\Data\Channel\Web\ChannelWebSettingsData;
use App\Enums\AttributeValueSource;
use App\Enums\IdentityType;
use App\Enums\TagSource;
use App\Models\AttributeDefinition;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\ContactAttributeValue;
use App\Models\ContactIdentity;
use App\Models\Conversation;
use App\Models\ConversationThread;
use App\Models\Tag;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->instance = createSystemSettings();
});

function buildWebChannel(GeneralSettings $app, array $mappings): Channel
{
    return Channel::factory()->create([
        'settings' => ChannelWebSettingsData::defaults([
            'query_param_mappings' => $mappings,
        ]),
    ]);
}

function freshContact(GeneralSettings $app): Contact
{
    return Contact::factory()->create([
        'name' => null,
    ]);
}

test('未配置映射或空 query 直接 noop', function () {
    $channel = Channel::factory()->create([]);
    $contact = freshContact($this->instance);

    ApplyVisitorQueryParamsAction::run($channel, $contact, [], false);
    ApplyVisitorQueryParamsAction::run($channel, $contact, ['utm_source' => 'google'], false);

    expect($contact->fresh()->name)->toBeNull();
});

test('OnlyIfEmpty + 联系人未填名时写入', function () {
    $channel = buildWebChannel($this->instance, [
        [
            'param_name' => 'name',
            'target' => 'contact_name',
            'trust' => 'always',
            'write_mode' => 'only_if_empty',
        ],
    ]);
    $contact = freshContact($this->instance);

    ApplyVisitorQueryParamsAction::run($channel, $contact, ['name' => '王小明'], false);

    expect($contact->fresh()->name)->toBe('王小明');
});

test('OnlyIfEmpty + 联系人已有姓名时不覆盖', function () {
    $channel = buildWebChannel($this->instance, [
        [
            'param_name' => 'name',
            'target' => 'contact_name',
            'trust' => 'always',
            'write_mode' => 'only_if_empty',
        ],
    ]);
    $contact = Contact::factory()->create(['name' => '原姓名']);

    ApplyVisitorQueryParamsAction::run($channel, $contact, ['name' => '新姓名'], false);

    expect($contact->fresh()->name)->toBe('原姓名');
});

test('Overwrite 模式覆盖已有姓名', function () {
    $channel = buildWebChannel($this->instance, [
        [
            'param_name' => 'name',
            'target' => 'contact_name',
            'trust' => 'always',
            'write_mode' => 'overwrite',
        ],
    ]);
    $contact = Contact::factory()->create(['name' => '原姓名']);

    ApplyVisitorQueryParamsAction::run($channel, $contact, ['name' => '新姓名'], false);

    expect($contact->fresh()->name)->toBe('新姓名');
});

test('SignedOnly 信任级别在未签名访客上不生效', function () {
    $channel = buildWebChannel($this->instance, [
        [
            'param_name' => 'name',
            'target' => 'contact_name',
            'trust' => 'signed_only',
            'write_mode' => 'overwrite',
        ],
    ]);
    $contact = freshContact($this->instance);

    ApplyVisitorQueryParamsAction::run($channel, $contact, ['name' => '潜在攻击者'], false);

    expect($contact->fresh()->name)->toBeNull();

    ApplyVisitorQueryParamsAction::run($channel, $contact, ['name' => '签名访客'], true);
    expect($contact->fresh()->name)->toBe('签名访客');
});

test('contact_importance 遵守信任级别并支持覆盖', function () {
    $channel = buildWebChannel($this->instance, [
        [
            'param_name' => 'vip',
            'target' => 'contact_importance',
            'trust' => 'signed_only',
            'write_mode' => 'overwrite',
        ],
    ]);
    $contact = freshContact($this->instance);
    $conversation = Conversation::factory()
        ->forContactChannel($contact, $channel)
        ->create();
    $thread = ConversationThread::requireForConversation($conversation);

    ApplyVisitorQueryParamsAction::run($channel, $contact, ['vip' => '1'], false);
    expect($contact->fresh()->is_important)->toBeFalse();

    ApplyVisitorQueryParamsAction::run($channel, $contact, ['vip' => 'vip'], true);
    $contact->refresh();
    expect($contact->is_important)->toBeTrue()
        ->and($contact->important_at)->not->toBeNull()
        ->and($contact->important_by_user_id)->toBeNull()
        ->and($contact->important_source)->toBe('channel')
        ->and($thread->fresh()->is_important)->toBeTrue();

    ApplyVisitorQueryParamsAction::run($channel, $contact, ['vip' => 'off'], true);
    $contact->refresh();
    expect($contact->is_important)->toBeFalse()
        ->and($contact->important_at)->toBeNull()
        ->and($contact->important_source)->toBeNull()
        ->and($thread->fresh()->is_important)->toBeFalse();
});

test('contact_importance 的 OnlyIfEmpty 不覆盖已有重点客户标记', function () {
    $channel = buildWebChannel($this->instance, [
        [
            'param_name' => 'vip',
            'target' => 'contact_importance',
            'trust' => 'always',
            'write_mode' => 'only_if_empty',
        ],
    ]);
    $contact = Contact::factory()->create([
        'is_important' => true,
        'important_at' => now(),
        'important_source' => 'manual',
    ]);

    ApplyVisitorQueryParamsAction::run($channel, $contact, ['vip' => '0'], false);

    expect($contact->fresh()->is_important)->toBeTrue()
        ->and($contact->fresh()->important_source)->toBe('manual');
});

test('contact_email 仅写入合法邮箱', function () {
    $channel = buildWebChannel($this->instance, [
        [
            'param_name' => 'email',
            'target' => 'contact_email',
            'trust' => 'always',
            'write_mode' => 'only_if_empty',
        ],
    ]);
    $contact = freshContact($this->instance);

    ApplyVisitorQueryParamsAction::run($channel, $contact, ['email' => 'not-an-email'], false);
    expect(ContactIdentity::query()->where('contact_id', $contact->id)->where('type', IdentityType::Email)->count())->toBe(0);

    ApplyVisitorQueryParamsAction::run($channel, $contact, ['email' => 'Foo@Example.com'], false);
    $identity = ContactIdentity::query()
        ->where('contact_id', $contact->id)
        ->where('type', IdentityType::Email)
        ->first();
    expect($identity)->not->toBeNull()
        ->and($identity->value)->toBe('foo@example.com');
});

test('email 已被其他联系人占用时跳过', function () {
    $channel = buildWebChannel($this->instance, [
        [
            'param_name' => 'email',
            'target' => 'contact_email',
            'trust' => 'always',
            'write_mode' => 'overwrite',
        ],
    ]);
    $occupied = Contact::factory()->create();
    ContactIdentity::query()->create([
        'contact_id' => $occupied->id,
        'type' => IdentityType::Email,
        'namespace' => '',
        'value' => 'shared@example.com',
        'display_value' => 'shared@example.com',
    ]);
    $contact = freshContact($this->instance);

    ApplyVisitorQueryParamsAction::run($channel, $contact, ['email' => 'shared@example.com'], true);

    expect(ContactIdentity::query()->where('contact_id', $contact->id)->where('type', IdentityType::Email)->count())->toBe(0);
});

test('attribute 仅当 definition 存在且 is_api_writable=true 时写入', function () {
    $writable = AttributeDefinition::factory()->text()->create([
        'key' => 'plan_level',
        'is_api_writable' => true,
    ]);
    AttributeDefinition::factory()->text()->create([
        'key' => 'locked_field',
        'is_api_writable' => false,
    ]);

    $channel = buildWebChannel($this->instance, [
        [
            'param_name' => 'plan',
            'target' => 'attribute',
            'target_key' => 'plan_level',
            'trust' => 'always',
            'write_mode' => 'only_if_empty',
        ],
        [
            'param_name' => 'locked',
            'target' => 'attribute',
            'target_key' => 'locked_field',
            'trust' => 'always',
            'write_mode' => 'overwrite',
        ],
        [
            'param_name' => 'unknown',
            'target' => 'attribute',
            'target_key' => 'no_such_definition',
            'trust' => 'always',
            'write_mode' => 'overwrite',
        ],
    ]);
    $contact = freshContact($this->instance);

    ApplyVisitorQueryParamsAction::run($channel, $contact, [
        'plan' => 'pro',
        'locked' => 'should-skip',
        'unknown' => 'ignored',
    ], false);

    $value = ContactAttributeValue::query()
        ->where('contact_id', $contact->id)
        ->where('definition_id', $writable->id)
        ->first();
    expect($value)->not->toBeNull()
        ->and($value->value_json)->toBe(['value' => 'pro'])
        ->and($value->source)->toBe(AttributeValueSource::Channel)
        ->and(ContactAttributeValue::query()->where('contact_id', $contact->id)->count())->toBe(1);
});

test('attribute single_select 必须命中 options.code', function () {
    $definition = AttributeDefinition::factory()->singleSelect([
        ['code' => 'gold', 'label' => 'Gold'],
        ['code' => 'silver', 'label' => 'Silver'],
    ])->create([
        'key' => 'level',
        'is_api_writable' => true,
    ]);
    $channel = buildWebChannel($this->instance, [
        [
            'param_name' => 'lvl',
            'target' => 'attribute',
            'target_key' => 'level',
            'trust' => 'always',
            'write_mode' => 'overwrite',
        ],
    ]);
    $contact = freshContact($this->instance);

    ApplyVisitorQueryParamsAction::run($channel, $contact, ['lvl' => 'platinum'], false);
    expect(ContactAttributeValue::query()->where('contact_id', $contact->id)->count())->toBe(0);

    ApplyVisitorQueryParamsAction::run($channel, $contact, ['lvl' => 'gold'], false);
    $value = ContactAttributeValue::query()
        ->where('contact_id', $contact->id)
        ->where('definition_id', $definition->id)
        ->first();
    expect($value)->not->toBeNull()
        ->and($value->value_json)->toBe(['value' => 'gold']);
});

test('tag 模板 {value} 占位只接受白名单字符', function () {
    $channel = buildWebChannel($this->instance, [
        [
            'param_name' => 'utm_source',
            'target' => 'tag',
            'target_key' => 'src:{value}',
            'trust' => 'always',
            'write_mode' => 'overwrite',
        ],
    ]);
    $contact = freshContact($this->instance);

    ApplyVisitorQueryParamsAction::run($channel, $contact, ['utm_source' => '<svg>'], false);
    expect(Tag::query()->count())->toBe(0);

    ApplyVisitorQueryParamsAction::run($channel, $contact, ['utm_source' => 'google_ads'], false);
    $tag = Tag::query()->first();
    expect($tag)->not->toBeNull()
        ->and($tag->name)->toBe('src:google_ads')
        ->and($tag->source)->toBe(TagSource::Channel);

    $assigned = DB::table('contact_tag_assignments')
        ->where('tag_id', $tag->id)
        ->where('contact_id', $contact->id)
        ->first();
    expect($assigned)->not->toBeNull()
        ->and($assigned->source)->toBe(TagSource::Channel->value);
});

test('tag 模板无 {value} 占位时直接使用模板字面量', function () {
    $channel = buildWebChannel($this->instance, [
        [
            'param_name' => 'campaign',
            'target' => 'tag',
            'target_key' => '本季活动客户',
            'trust' => 'always',
            'write_mode' => 'overwrite',
        ],
    ]);
    $contact = freshContact($this->instance);

    ApplyVisitorQueryParamsAction::run($channel, $contact, ['campaign' => 'whatever'], false);

    $tag = Tag::query()->first();
    expect($tag)->not->toBeNull()
        ->and($tag->name)->toBe('本季活动客户');
});

test('外部 ID 写入为应用级单一标识', function () {
    $channel = buildWebChannel($this->instance, [
        [
            'param_name' => 'uid',
            'target' => 'contact_external_id',
            'trust' => 'signed_only',
            'write_mode' => 'only_if_empty',
        ],
    ]);
    $contact = freshContact($this->instance);

    ApplyVisitorQueryParamsAction::run($channel, $contact, ['uid' => 'extern-001'], true);

    $identity = ContactIdentity::query()
        ->where('contact_id', $contact->id)
        ->where('type', IdentityType::ExternalId)
        ->first();
    expect($identity)->not->toBeNull()
        ->and($identity->value)->toBe('extern-001')
        ->and($identity->namespace)->toBe('');
});
