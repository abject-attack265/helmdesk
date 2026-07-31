<?php

use App\Actions\Contact\CreateContactAction;
use App\Actions\Contact\CreateContactIdentityAction;
use App\Actions\Contact\DeleteContactAction;
use App\Actions\Contact\DeleteContactIdentityAction;
use App\Actions\Contact\GetContactTrashListAction;
use App\Actions\Contact\MergeContactsAction;
use App\Actions\Contact\ReplaceContactIdentityAction;
use App\Actions\Contact\ResolveContactIdentityAction;
use App\Actions\Contact\RestoreContactAction;
use App\Actions\Contact\ShowContactConversationTimelineAction;
use App\Actions\Contact\ShowContactListAction;
use App\Actions\Contact\UpdateContactAction;
use App\Actions\Contact\UpdateContactImportanceAction;
use App\Data\Contact\FormCreateContactData;
use App\Data\Contact\FormCreateContactIdentityData;
use App\Data\Contact\FormReplaceContactIdentityData;
use App\Data\Contact\FormUpdateContactData;
use App\Data\Contact\FormUpdateContactImportanceData;
use App\Enums\ContactListType;
use App\Enums\ContactSource;
use App\Enums\ContactType;
use App\Enums\ConversationRatingHandledBy;
use App\Enums\ConversationRatingScore;
use App\Enums\ConversationStatus;
use App\Enums\IdentityType;
use App\Exceptions\BusinessException;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\ContactActivityLog;
use App\Models\ContactIdentity;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationPageView;
use App\Models\ConversationRating;
use App\Models\ConversationTimelineEntry;
use App\Models\User;
use App\Services\Contact\ContactAiContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// === Contact List ===

test('联系人列表页面渲染并包含数据', function () {
    [$app, $user] = createInstanceWithOwner();

    Contact::factory()
        ->count(3)
        ->has(ContactIdentity::factory()->email(), 'identities')
        ->create();

    $props = ShowContactListAction::run(
        type: ContactListType::All,
    );

    expect($props->contact_list)->toHaveCount(3)
        ->and($props->contact_list_pagination)->not->toBeNull()
        ->and($props->current_type)->toBe(ContactListType::All);
});

test('联系人列表拒绝无效列表类型', function () {
    [$app, $user] = createInstanceWithOwner();

    $this->actingAs($user)
        ->get('/app/contacts/unknown/index')
        ->assertNotFound();
});

test('联系人列表筛选按类型', function () {
    [$app, $user] = createInstanceWithOwner();

    Contact::factory()->count(2)->visitor()->create();
    Contact::factory()->count(3)->contact()->create();

    $visitorProps = ShowContactListAction::run(
        type: ContactListType::Visitors,
    );

    expect($visitorProps->contact_list)->toHaveCount(2)
        ->and($visitorProps->current_type)->toBe(ContactListType::Visitors);

    $contactProps = ShowContactListAction::run(
        type: ContactListType::Contacts,
    );

    expect($contactProps->contact_list)->toHaveCount(3)
        ->and($contactProps->current_type)->toBe(ContactListType::Contacts);
});

test('联系人列表支持重点客户筛选并优先显示重点客户', function () {
    [$app, $user] = createInstanceWithOwner();

    $important = Contact::factory()->create([
        'name' => '重点客户',
        'is_important' => true,
        'important_at' => now()->subDay(),
        'important_source' => 'manual',
        'last_seen_at' => now()->subDays(5),
    ]);
    $normal = Contact::factory()->create([
        'name' => '普通客户',
        'last_seen_at' => now(),
    ]);

    $allProps = ShowContactListAction::run(
        type: ContactListType::All,
    );

    expect($allProps->contact_list[0]->id)->toBe($important->id)
        ->and($allProps->contact_list[1]->id)->toBe($normal->id);

    $importantProps = ShowContactListAction::run(
        type: ContactListType::All,
        importantOnly: true,
    );

    expect($importantProps->important_only)->toBeTrue()
        ->and($importantProps->contact_list)->toHaveCount(1)
        ->and($importantProps->contact_list[0]->id)->toBe($important->id)
        ->and($importantProps->contact_list[0]->is_important)->toBeTrue();
});

test('联系人列表支持分页参数', function () {
    [$app, $user] = createInstanceWithOwner();

    Contact::factory()
        ->count(12)
        ->has(ContactIdentity::factory()->email(), 'identities')
        ->create();

    $props = ShowContactListAction::run(
        type: ContactListType::All,
        page: 2,
        perPage: 15,
    );

    expect($props->contact_list_pagination->current_page)->toBe(2)
        ->and($props->contact_list_pagination->per_page)->toBe(15)
        ->and($props->contact_list_pagination->total)->toBe(12);
});

test('联系人回收站页面渲染软删除联系人', function () {
    [$app, $user] = createInstanceWithOwner();

    $trashedContact = Contact::factory()
        ->create([
            'name' => '已删除联系人',
        ]);

    DeleteContactAction::run($trashedContact->id);

    Contact::factory()
        ->create([
            'name' => '活跃联系人',
        ]);

    $props = GetContactTrashListAction::run();

    expect($props->contact_trash_list)->toHaveCount(1)
        ->and($props->contact_trash_list[0]->name)->toBe('已删除联系人')
        ->and($props->contact_trash_list_pagination->total)->toBe(1);
});

test('联系人搜索不会模糊匹配不同邮箱词元', function () {
    [$app, $user] = createInstanceWithOwner();

    bootTntSearch();

    try {
        $contact = CreateContactAction::run(new FormCreateContactData(
            email: 't1@test.com',
        ));

        $matchedIds = Contact::search('test2')

            ->keys();

        expect($matchedIds)->not->toContain($contact->id);
    } finally {
        flushTntSearch();
    }
});

// === Create Contact ===

test('可以创建联系人并带邮箱', function () {
    [$app, $user] = createInstanceWithOwner();

    $data = new FormCreateContactData(
        name: '张三',
        email: 'zhang@example.com',
        phone: null,
    );

    $contact = CreateContactAction::run($data);

    expect($contact)->toBeInstanceOf(Contact::class)
        ->and($contact->name)->toBe('张三')
        ->and($contact->type)->toBe(ContactType::Contact)
        ->and($contact->source)->toBe(ContactSource::Manual)
        ->and($contact->avatar_url)->toBe(Contact::DEFAULT_AVATAR_URL)
        ->and($contact->primary_email)->toBe('zhang@example.com');

    expect($contact->identities)->toHaveCount(1);
    expect($contact->identities->first()->type)->toBe(IdentityType::Email);
    expect($contact->activityLogs()->latest('created_at')->value('action'))->toBe('created');
});

test('可以创建联系人并带电话', function () {
    [$app, $user] = createInstanceWithOwner();

    $data = new FormCreateContactData(
        name: null,
        email: null,
        phone: '+8613800138000',
    );

    $contact = CreateContactAction::run($data);

    expect($contact->primary_phone)->toBe('+8613800138000')
        ->and($contact->name)->toBeNull()
        ->and($contact->type)->toBe(ContactType::Contact);
});

test('创建联系人会拒绝无效身份载荷', function (string $case) {
    [$app, $user] = createInstanceWithOwner();

    $data = match ($case) {
        'missing identity' => new FormCreateContactData(name: '张三'),
        'duplicate email' => tap(new FormCreateContactData(email: 'dup@example.com'), function (): void {
            $existing = Contact::factory()->create();
            ContactIdentity::factory()->email()->create([
                'contact_id' => $existing->id,
                'value' => 'dup@example.com',
            ]);
        }),
        'duplicate phone' => tap(new FormCreateContactData(phone: '+86 13800138000'), function (): void {
            $existing = Contact::factory()->create();
            ContactIdentity::factory()->phone()->create([
                'contact_id' => $existing->id,
                'value' => '+8613800138000',
                'display_value' => '+8613800138000',
            ]);
        }),
        'invalid phone' => new FormCreateContactData(phone: 'call-me-maybe'),
        'phone without country code' => new FormCreateContactData(phone: '18995543120'),
    };

    expect(fn () => CreateContactAction::run($data))
        ->toThrow(ValidationException::class);
})->with([
    'missing identity',
    'duplicate email',
    'duplicate phone',
    'invalid phone',
    'phone without country code',
]);

test('创建联系人将邮箱标准化为小写', function () {
    [$app, $user] = createInstanceWithOwner();

    $data = new FormCreateContactData(email: 'Zhang@Example.COM');
    $contact = CreateContactAction::run($data);

    expect($contact->identities->first()->value)->toBe('zhang@example.com');
});

test('创建联系人将电话号码标准化为E.164', function () {
    [$app, $user] = createInstanceWithOwner();

    $data = new FormCreateContactData(phone: '+86 138 0013 8000');
    $contact = CreateContactAction::run($data);

    expect($contact->primary_phone)->toBe('+8613800138000')
        ->and($contact->identities->first()->value)->toBe('+8613800138000');
});

test('解析并创建联系人并带默认头像URL', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = ResolveContactIdentityAction::run([
        'type' => IdentityType::Session,
        'value' => 'sess-default-avatar',
    ]);

    expect($contact->avatar_url)->toBe(Contact::DEFAULT_AVATAR_URL);
});

// === Update Contact ===

test('可以更新联系人名称和类型', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->visitor()->create(['name' => '旧名字']);

    $data = new FormUpdateContactData(
        name: '新名字',
        type: 'contact',
        note: '长期备注',
        country: '中国',
        city: '上海',
    );
    $updated = UpdateContactAction::run($contact->id, $data);

    expect($updated->name)->toBe('新名字')
        ->and($updated->type)->toBe(ContactType::Contact)
        ->and($updated->note)->toBe('长期备注')
        ->and($updated->country)->toBe('中国')
        ->and($updated->city)->toBe('上海');
    expect($contact->fresh()->activityLogs()->latest('created_at')->value('action'))->toBe('updated');
    expect(data_get($contact->fresh()->activityLogs()->latest('created_at')->first()?->payload, 'field_changes.type.new'))
        ->toBe('contact');
});

test('可以标记和取消标记重点客户', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->create([
        'is_important' => false,
    ]);

    $this->actingAs($user)
        ->putJson(route('app.contacts.importance.update', [
            'id' => $contact->id,
        ]), [
            'is_important' => true,
        ])
        ->assertOk()
        ->assertJsonPath('is_important', true);

    $contact->refresh();
    expect($contact->is_important)->toBeTrue()
        ->and($contact->important_at)->not->toBeNull()
        ->and($contact->important_by_user_id)->toBe($user->id)
        ->and($contact->important_source)->toBe('manual')
        ->and($contact->activityLogs()->latest('created_at')->value('action'))->toBe(ContactActivityLog::ACTION_IMPORTANT_MARKED);

    $this->actingAs($user)
        ->putJson(route('app.contacts.importance.update', [
            'id' => $contact->id,
        ]), [
            'is_important' => false,
        ])
        ->assertOk()
        ->assertJsonPath('is_important', false)
        ->assertJsonPath('important_at', null);

    $contact->refresh();
    expect($contact->is_important)->toBeFalse()
        ->and($contact->important_at)->toBeNull()
        ->and($contact->important_by_user_id)->toBeNull()
        ->and($contact->important_source)->toBeNull()
        ->and($contact->activityLogs()->latest('created_at')->value('action'))->toBe(ContactActivityLog::ACTION_IMPORTANT_UNMARKED);
});

test('重复提交相同重点客户状态不重复写活动日志', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->create([
        'is_important' => true,
        'important_at' => now(),
        'important_by_user_id' => $user->id,
        'important_source' => 'manual',
    ]);

    UpdateContactImportanceAction::run(
        $contact->id,
        new FormUpdateContactImportanceData(is_important: true),
        $user,
    );

    expect($contact->activityLogs()->count())->toBe(0);
});

// === Delete Contact ===

test('删除联系人软删除联系人和身份', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->create();
    $identity = ContactIdentity::factory()->email()->create([
        'contact_id' => $contact->id,
    ]);

    DeleteContactAction::run($contact->id);

    expect(Contact::find($contact->id))->toBeNull();
    expect(Contact::withTrashed()->find($contact->id))->not->toBeNull();
    expect(ContactIdentity::find($identity->id))->toBeNull();
    expect(ContactIdentity::withTrashed()->find($identity->id))->not->toBeNull();
    expect(Contact::withTrashed()->findOrFail($contact->id)->activityLogs()->latest('created_at')->value('action'))
        ->toBe('deleted');
});

test('软删除身份会为新联系人释放唯一键', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact1 = Contact::factory()->create();
    ContactIdentity::factory()->email()->create([
        'contact_id' => $contact1->id,
        'value' => 'release@example.com',
    ]);

    DeleteContactAction::run($contact1->id);

    $data = new FormCreateContactData(email: 'release@example.com');
    $contact2 = CreateContactAction::run($data);

    expect($contact2->primary_email)->toBe('release@example.com')
        ->and($contact2->id)->not->toBe($contact1->id);
});

// === Restore Contact ===

test('可以恢复软已删除联系人并带身份', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->create();
    ContactIdentity::factory()->email()->create([
        'contact_id' => $contact->id,
        'value' => 'restore@example.com',
    ]);

    DeleteContactAction::run($contact->id);
    $restored = RestoreContactAction::run($contact->id);

    expect($restored->trashed())->toBeFalse();
    expect($restored->identities()->count())->toBe(1);
    expect($restored->activityLogs()->latest('created_at')->value('action'))
        ->toBe('restored');
});

test('恢复联系人拒绝当身份冲突并带活跃记录', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact1 = Contact::factory()->create();
    ContactIdentity::factory()->email()->create([
        'contact_id' => $contact1->id,
        'value' => 'conflict@example.com',
    ]);

    DeleteContactAction::run($contact1->id);

    $contact2 = Contact::factory()->create();
    ContactIdentity::factory()->email()->create([
        'contact_id' => $contact2->id,
        'value' => 'conflict@example.com',
    ]);

    RestoreContactAction::run($contact1->id);
})->throws(ValidationException::class);

// === Create Contact Identity ===

test('可以添加身份到现有联系人', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->create();
    $data = new FormCreateContactIdentityData(type: 'email', value: 'new@example.com');

    $identity = CreateContactIdentityAction::run($contact->id, $data);

    expect($identity->type)->toBe(IdentityType::Email)
        ->and($identity->value)->toBe('new@example.com');

    $contact->refresh();
    expect($contact->primary_email)->toBe('new@example.com');
    expect($contact->activityLogs()->latest('created_at')->value('action'))->toBe('identity_added');
});

test('可以添加电话身份并带标准化值', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->create();
    $data = new FormCreateContactIdentityData(type: 'phone', value: '+86 13800138000');

    $identity = CreateContactIdentityAction::run($contact->id, $data);

    expect($identity->value)->toBe('+8613800138000')
        ->and($identity->display_value)->toBe('+8613800138000');
});

test('添加身份检测到重复', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact1 = Contact::factory()->create();
    ContactIdentity::factory()->email()->create([
        'contact_id' => $contact1->id,
        'value' => 'taken@example.com',
    ]);

    $contact2 = Contact::factory()->create();
    $data = new FormCreateContactIdentityData(type: 'email', value: 'taken@example.com');

    CreateContactIdentityAction::run($contact2->id, $data);
})->throws(ValidationException::class);

test('添加电话身份拒绝无效电话输入', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->create();
    $data = new FormCreateContactIdentityData(type: 'phone', value: 'invalid-phone');

    CreateContactIdentityAction::run($contact->id, $data);
})->throws(ValidationException::class);

test('添加电话身份拒绝电话且没有国家代码', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->create();
    $data = new FormCreateContactIdentityData(type: 'phone', value: '18995543120');

    CreateContactIdentityAction::run($contact->id, $data);
})->throws(ValidationException::class);

test('external_id可以直接创建', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->create();
    $data = new FormCreateContactIdentityData(type: 'external_id', value: '12345');

    $identity = CreateContactIdentityAction::run($contact->id, $data);

    expect($identity->type)->toBe(IdentityType::ExternalId)
        ->and($identity->namespace)->toBe('');
});

test('添加邮箱身份将访客提升为联系人', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->visitor()->create();
    expect($contact->type)->toBe(ContactType::Visitor);

    $data = new FormCreateContactIdentityData(type: 'email', value: 'upgrade@example.com');
    CreateContactIdentityAction::run($contact->id, $data);

    $contact->refresh();
    expect($contact->type)->toBe(ContactType::Contact);
});

test('添加邮箱身份不会降级联系人类型', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->contact()->create();

    $data = new FormCreateContactIdentityData(type: 'email', value: 'stay@example.com');
    CreateContactIdentityAction::run($contact->id, $data);

    $contact->refresh();
    expect($contact->type)->toBe(ContactType::Contact);
});

test('可以删除邮箱身份和同步主字段', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->create();
    $primaryIdentity = ContactIdentity::factory()->email()->create([
        'contact_id' => $contact->id,
        'value' => 'first@example.com',
        'display_value' => 'first@example.com',
        'created_at' => now()->subMinute(),
    ]);
    ContactIdentity::factory()->email()->create([
        'contact_id' => $contact->id,
        'value' => 'second@example.com',
        'display_value' => 'second@example.com',
    ]);

    $contact->syncPrimaryFields();

    DeleteContactIdentityAction::run($contact->id, $primaryIdentity->id);

    expect(ContactIdentity::find($primaryIdentity->id))->toBeNull()
        ->and($contact->fresh()->primary_email)->toBe('second@example.com');
    expect($contact->fresh()->activityLogs()->latest('created_at')->value('action'))->toBe('identity_deleted');
});

test('替换身份会软删除旧身份并保留主身份排序', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->create();
    $identity = ContactIdentity::factory()->phone()->create([
        'contact_id' => $contact->id,
        'value' => '+8613800138000',
        'display_value' => '+8613800138000',
        'created_at' => now()->subHour(),
    ]);

    $replacement = ReplaceContactIdentityAction::run(
        $contact->id,
        $identity->id,
        new FormReplaceContactIdentityData(value: '+852 5123 4567'),
    );

    expect(ContactIdentity::find($identity->id))->toBeNull();
    expect(ContactIdentity::withTrashed()->find($identity->id))->not->toBeNull();
    expect($replacement->value)->toBe('+85251234567')
        ->and($replacement->created_at?->toIso8601String())->toBe($identity->created_at?->toIso8601String())
        ->and($contact->fresh()->primary_phone)->toBe('+85251234567');
    expect($contact->fresh()->activityLogs()->latest('created_at')->value('action'))->toBe('identity_replaced');
    expect(data_get($contact->fresh()->activityLogs()->latest('created_at')->first()?->payload, 'old_value'))
        ->toBe('+8613800138000');
});

test('不能手动删除外部身份', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->create();
    $identity = ContactIdentity::factory()->create([
        'contact_id' => $contact->id,
        'type' => IdentityType::ExternalId,
        'namespace' => 'api:crm',
        'value' => 'crm-123',
        'display_value' => 'crm-123',
    ]);

    DeleteContactIdentityAction::run($contact->id, $identity->id);
})->throws(ValidationException::class);

// === Resolve Contact Identity ===

test('解析并创建新联系人当没有匹配', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = ResolveContactIdentityAction::run([
        'type' => IdentityType::Email,
        'value' => 'new@example.com',
    ]);

    expect($contact)->toBeInstanceOf(Contact::class)
        ->and($contact->identities)->toHaveCount(1)
        ->and($contact->primary_email)->toBe('new@example.com');
    expect($contact->activityLogs()->latest('created_at')->value('action'))->toBe('created');
});

test('解析并返回现有联系人当身份匹配', function () {
    [$app, $user] = createInstanceWithOwner();

    $existing = Contact::factory()->create();
    ContactIdentity::factory()->email()->create([
        'contact_id' => $existing->id,
        'value' => 'existing@example.com',
    ]);

    $resolved = ResolveContactIdentityAction::run([
        'type' => IdentityType::Email,
        'value' => 'existing@example.com',
    ]);

    expect($resolved->id)->toBe($existing->id);
    expect(ContactIdentity::query()->where('value', 'existing@example.com')->count())->toBe(1);
});

test('解析并匹配现有联系人当电话标准化到同一值', function () {
    [$app, $user] = createInstanceWithOwner();

    $existing = Contact::factory()->create();
    ContactIdentity::factory()->phone()->create([
        'contact_id' => $existing->id,
        'value' => '+8613800138000',
        'display_value' => '+8613800138000',
    ]);

    $resolved = ResolveContactIdentityAction::run([
        'type' => IdentityType::Phone,
        'value' => '+86 13800138000',
    ]);

    expect($resolved->id)->toBe($existing->id);
    expect(ContactIdentity::query()->where('value', '+8613800138000')->count())->toBe(1);
});

test('并发抢先插入同一身份触发唯一冲突时自动重试', function () {
    [$app, $user] = createInstanceWithOwner();
    $winner = Contact::factory()->visitor()->create();

    // 模拟并发竞态：本次事务已通过「身份不存在」检查、即将插入身份时，
    // 另一并发请求抢先写入了同一身份（直插绕过模型事件避免递归触发本钩子）。
    $simulated = false;
    ContactIdentity::creating(function () use (&$simulated, $winner): void {
        if ($simulated) {
            return;
        }
        $simulated = true;
        DB::table('contact_identities')->insert([
            'id' => (string) Str::ulid(),
            'contact_id' => $winner->id,
            'type' => IdentityType::ExternalId->value,
            'namespace' => 'telegram:tg_test',
            'value' => '8180599868',
            'display_value' => '8180599868',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $resolved = ResolveContactIdentityAction::run([
        'type' => IdentityType::ExternalId,
        'namespace' => 'telegram:tg_test',
        'value' => '8180599868',
    ], ContactSource::Telegram, 'Tele 访客');

    // 首次事务因唯一冲突整体回滚（含模拟的抢先行），重试后干净落库且身份唯一
    $identities = ContactIdentity::query()

        ->where('value', '8180599868')
        ->whereNull('deleted_at')
        ->get();
    expect($identities)->toHaveCount(1)
        ->and($identities->first()->contact_id)->toBe($resolved->id);
});

test('唯一冲突只重试一次，持续冲突照常抛出', function () {
    [$app, $user] = createInstanceWithOwner();
    $winner = Contact::factory()->visitor()->create();

    // 每次尝试插入前都抢先写入同一身份，让两次尝试都冲突
    ContactIdentity::creating(function () use ($winner): void {
        DB::table('contact_identities')->insert([
            'id' => (string) Str::ulid(),
            'contact_id' => $winner->id,
            'type' => IdentityType::ExternalId->value,
            'namespace' => 'telegram:tg_test',
            'value' => '8180599868',
            'display_value' => '8180599868',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    ResolveContactIdentityAction::run([
        'type' => IdentityType::ExternalId,
        'namespace' => 'telegram:tg_test',
        'value' => '8180599868',
    ], ContactSource::Telegram);
})->throws(UniqueConstraintViolationException::class);

test('解析会创建联系人类型用于邮箱', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = ResolveContactIdentityAction::run([
        'type' => IdentityType::Email,
        'value' => 'known@example.com',
    ]);

    expect($contact->type)->toBe(ContactType::Contact);
});

test('解析会创建访客类型用于会话', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = ResolveContactIdentityAction::run([
        'type' => IdentityType::Session,
        'value' => 'sess-abc-123-def',
    ]);

    expect($contact->type)->toBe(ContactType::Visitor);
});

test('解析会将访客提升为联系人当匹配按邮箱', function () {
    [$app, $user] = createInstanceWithOwner();

    $visitor = Contact::factory()->visitor()->create();
    ContactIdentity::factory()->email()->create([
        'contact_id' => $visitor->id,
        'value' => 'promote@example.com',
    ]);

    expect($visitor->type)->toBe(ContactType::Visitor);

    $resolved = ResolveContactIdentityAction::run([
        'type' => IdentityType::Email,
        'value' => 'promote@example.com',
    ]);

    expect($resolved->id)->toBe($visitor->id)
        ->and($resolved->type)->toBe(ContactType::Contact);
    expect($visitor->fresh()->activityLogs()->latest('created_at')->value('action'))->toBe('updated');
});

// === Merge Contacts ===

test('合并拒绝联系人与自身合并', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->create();
    ContactIdentity::factory()->email()->create([
        'contact_id' => $contact->id,
    ]);

    MergeContactsAction::run($contact->id, $contact->id);
})->throws(InvalidArgumentException::class);

test('联系人合并接口校验双方必须不同', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->create();

    $this->actingAs($user)
        ->post(route('app.contacts.merge'), [
            'target_contact_id' => $contact->id,
            'merged_contact_id' => $contact->id,
        ])
        ->assertSessionHasErrors(['merged_contact_id']);

    expect($contact->fresh())->not->toBeNull();
});

test('合并转移身份和删除已合并联系人', function () {
    [$app, $user] = createInstanceWithOwner();

    $target = Contact::factory()->create(['name' => '目标']);
    ContactIdentity::factory()->email()->create([
        'contact_id' => $target->id,
        'value' => 'target@example.com',
    ]);

    $merged = Contact::factory()->create(['name' => '被合并']);
    ContactIdentity::factory()->phone()->create([
        'contact_id' => $merged->id,
        'value' => '+8613800138000',
    ]);

    $result = MergeContactsAction::run($target->id, $merged->id);

    expect($result->id)->toBe($target->id)
        ->and($result->name)->toBe('目标');

    expect(Contact::find($merged->id))->toBeNull();

    $log = ContactActivityLog::query()
        ->where('contact_id', $target->id)
        ->where('action', ContactActivityLog::ACTION_MERGED_INTO_CURRENT)
        ->latest('created_at')
        ->first();

    expect($log)->not->toBeNull()
        ->and(data_get($log->payload, 'related_contact_name'))->toBe('被合并')
        ->and(data_get($log->payload, 'identity_snapshots.0.value'))->toBe('+8613800138000')
        ->and(data_get($log->payload, 'merged_attributes.name'))->toBe('被合并');
});

test('合并把被合并联系人的会话改挂到目标联系人', function () {
    [$app] = createInstanceWithOwner();

    $target = Contact::factory()->create();
    $merged = Contact::factory()->create();

    $conversation = Conversation::factory()->create([
        'contact_id' => $merged->id,
    ]);

    MergeContactsAction::run($target->id, $merged->id);

    expect($conversation->fresh()->contact_id)->toBe($target->id);
});

test('合并同步会话时间线浏览轨迹和评价的联系人归属', function () {
    [$app] = createInstanceWithOwner();

    $target = Contact::factory()->create();
    $merged = Contact::factory()->create();
    $conversation = Conversation::factory()->closed()->create([
        'contact_id' => $merged->id,
    ]);
    $message = ConversationMessage::factory()->forConversation($conversation)->visitorText()->create([
        'content' => '被合并联系人的历史消息',
    ]);
    $pageView = ConversationPageView::factory()->forConversation($conversation)->create();
    $rating = ConversationRating::query()->create([
        'conversation_id' => $conversation->id,
        'contact_id' => $merged->id,
        'score' => ConversationRatingScore::Positive,
        'channel_type' => 'web',
        'handled_by' => ConversationRatingHandledBy::Ai,
        'rated_at' => now(),
    ]);

    MergeContactsAction::run($target->id, $merged->id);

    $timelineEntry = ConversationTimelineEntry::query()
        ->where('entry_id', $message->id)
        ->firstOrFail();
    $timeline = ShowContactConversationTimelineAction::run($target->fresh());

    expect($conversation->fresh()->contact_id)->toBe($target->id)
        ->and($timelineEntry->contact_id)->toBe($target->id)
        ->and($pageView->fresh()->contact_id)->toBe($target->id)
        ->and($rating->fresh()->contact_id)->toBe($target->id)
        ->and($timeline->conversations)->toHaveCount(1)
        ->and($timeline->entries)->toHaveCount(1)
        ->and($timeline->entries[0]->id)->toBe($message->id);
});

test('合并拒绝同一渠道存在两条开放会话', function () {
    [$app] = createInstanceWithOwner();

    $target = Contact::factory()->create();
    $merged = Contact::factory()->create();
    $channel = Channel::factory()->create();
    $targetConversation = Conversation::factory()->create([
        'contact_id' => $target->id,
        'channel_id' => $channel->id,
        'status' => ConversationStatus::Open,
    ]);
    $mergedConversation = Conversation::factory()->create([
        'contact_id' => $merged->id,
        'channel_id' => $channel->id,
        'status' => ConversationStatus::Open,
    ]);

    expect(fn () => MergeContactsAction::run($target->id, $merged->id))
        ->toThrow(BusinessException::class, __('contact.merge_open_conversation_conflict'));

    expect($targetConversation->fresh()->contact_id)->toBe($target->id)
        ->and($mergedConversation->fresh()->contact_id)->toBe($merged->id)
        ->and($merged->fresh())->not->toBeNull();
});

test('合并填充null属性来自已合并联系人', function () {
    [$app, $user] = createInstanceWithOwner();

    $target = Contact::factory()->create([
        'name' => '目标',
        'locale' => null,
        'timezone' => null,
        'note' => null,
    ]);
    ContactIdentity::factory()->email()->create([
        'contact_id' => $target->id,
    ]);

    $merged = Contact::factory()->create([
        'name' => '被合并',
        'locale' => 'zh_CN',
        'timezone' => 'Asia/Shanghai',
        'note' => '被合并联系人的备注',
    ]);
    ContactIdentity::factory()->phone()->create([
        'contact_id' => $merged->id,
    ]);

    $result = MergeContactsAction::run($target->id, $merged->id);

    expect($result->name)->toBe('目标')
        ->and($result->locale)->toBe('zh_CN')
        ->and($result->timezone)->toBe('Asia/Shanghai')
        ->and($result->note)->toBe('被合并联系人的备注');
});

test('合并提升目标到联系人类型当已合并是联系人类型', function () {
    [$app, $user] = createInstanceWithOwner();

    $target = Contact::factory()->visitor()->create();
    ContactIdentity::factory()->session()->create([
        'contact_id' => $target->id,
    ]);

    $merged = Contact::factory()->contact()->create();
    ContactIdentity::factory()->email()->create([
        'contact_id' => $merged->id,
        'value' => 'merge@example.com',
        'display_value' => 'merge@example.com',
    ]);

    $result = MergeContactsAction::run($target->id, $merged->id);

    expect($result->type)->toBe(ContactType::Contact);
});

test('合并保留较晚last_seen_at', function () {
    [$app, $user] = createInstanceWithOwner();

    $target = Contact::factory()->create([
        'last_seen_at' => now()->subDays(2),
    ]);
    ContactIdentity::factory()->email()->create([
        'contact_id' => $target->id,
    ]);

    $later = now()->subHour();
    $merged = Contact::factory()->create([
        'last_seen_at' => $later,
    ]);
    ContactIdentity::factory()->phone()->create([
        'contact_id' => $merged->id,
    ]);

    $result = MergeContactsAction::run($target->id, $merged->id);

    expect($result->last_seen_at->toDateTimeString())->toBe($later->toDateTimeString());
});

test('合并会保留重点客户标记', function () {
    [$app, $user] = createInstanceWithOwner();

    $target = Contact::factory()->create([
        'is_important' => false,
    ]);
    ContactIdentity::factory()->email()->create([
        'contact_id' => $target->id,
    ]);

    $markedAt = now()->subHours(2);
    $merged = Contact::factory()->create([
        'is_important' => true,
        'important_at' => $markedAt,
        'important_by_user_id' => $user->id,
        'important_source' => 'manual',
    ]);
    ContactIdentity::factory()->phone()->create([
        'contact_id' => $merged->id,
    ]);

    $result = MergeContactsAction::run($target->id, $merged->id);

    expect($result->is_important)->toBeTrue()
        ->and($result->important_at?->toDateTimeString())->toBe($markedAt->toDateTimeString())
        ->and($result->important_by_user_id)->toBe($user->id)
        ->and($result->important_source)->toBe('manual');
});

test('合并会保留目标ai_context值、填充缺失键并刷新更新时间戳', function () {
    [$app, $user] = createInstanceWithOwner();

    $target = Contact::factory()->create([
        'ai_context' => [
            'preferences' => '偏好中文',
            '_updated_at' => '2026-01-01T00:00:00+00:00',
        ],
    ]);
    ContactIdentity::factory()->email()->create([
        'contact_id' => $target->id,
    ]);

    $merged = Contact::factory()->create([
        'ai_context' => [
            'preferences' => 'prefers English',
            'sentiment' => 'positive',
            '_updated_at' => '2026-01-02T00:00:00+00:00',
        ],
    ]);
    ContactIdentity::factory()->phone()->create([
        'contact_id' => $merged->id,
    ]);

    $beforeMerge = now()->subSecond();

    $result = MergeContactsAction::run($target->id, $merged->id);

    expect($result->ai_context)->toBeArray()
        ->and($result->ai_context['preferences'])->toBe('偏好中文')
        ->and($result->ai_context['sentiment'])->toBe('positive')
        ->and($result->ai_context['_updated_at'])->not->toBe('2026-01-01T00:00:00+00:00')
        ->and($result->ai_context['_updated_at'])->not->toBe('2026-01-02T00:00:00+00:00')
        ->and($result->ai_context['_updated_at'])->toBeString();

    expect($result->fresh()->ai_context['_updated_at'])->toBeString();
    expect(strtotime($result->fresh()->ai_context['_updated_at']))->toBeGreaterThanOrEqual($beforeMerge->timestamp);
});

test('合并拒绝过大的ai_context载荷', function () {
    [$app, $user] = createInstanceWithOwner();

    $target = Contact::factory()->create([
        'ai_context' => [
            'summary' => str_repeat('a', ContactAiContext::MAX_BYTES),
        ],
    ]);
    ContactIdentity::factory()->email()->create([
        'contact_id' => $target->id,
    ]);

    $merged = Contact::factory()->create();
    ContactIdentity::factory()->phone()->create([
        'contact_id' => $merged->id,
    ]);

    MergeContactsAction::run($target->id, $merged->id);
})->throws(ValidationException::class);

// === HTTP endpoints ===

test('可以创建联系人通过HTTP', function () {
    [$app, $user] = createInstanceWithOwner();

    $this->actingAs($user)
        ->post(route('app.contacts.store', []), [
            'email' => 'http@example.com',
        ])
        ->assertRedirect();

    expect(Contact::query()->count())->toBe(1);
});

test('系统成员可以创建联系人', function () {
    [$app] = createInstanceWithOwner();
    $member = User::factory()->create();
    attachMember($member);

    $this->actingAs($member)
        ->post(route('app.contacts.store', []), [
            'email' => 'member@example.com',
        ])
        ->assertRedirect();

    expect(Contact::query()

        ->whereHas('identities', fn ($query) => $query->where('value', 'member@example.com'))
        ->exists())->toBeTrue();
});

test('可以获取联系人详情通过HTTP', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->create();
    ContactIdentity::factory()->email()->create([
        'contact_id' => $contact->id,
    ]);

    $this->actingAs($user)
        ->getJson(route('app.contacts.show', ['id' => $contact->id]))
        ->assertOk()
        ->assertJsonStructure(['id', 'name', 'type', 'source', 'identities']);
});

test('联系人详情包含命名空间用于外部身份', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->create();
    ContactIdentity::factory()->externalId('ext-123', 'api:shopify')->create([
        'contact_id' => $contact->id,
    ]);

    $this->actingAs($user)
        ->getJson(route('app.contacts.show', ['id' => $contact->id]))
        ->assertOk()
        ->assertJsonPath('identities.0.namespace', 'api:shopify');
});

test('请求包含已删除数据时可以通过HTTP获取已删除联系人详情', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->create();
    $identity = ContactIdentity::factory()->email()->create([
        'contact_id' => $contact->id,
        'value' => 'trashed@example.com',
        'display_value' => 'trashed@example.com',
    ]);

    DeleteContactAction::run($contact->id);

    $this->actingAs($user)
        ->getJson(route('app.contacts.show', [
            'id' => $contact->id,
            'include_trashed' => 1,
        ]))
        ->assertOk()
        ->assertJsonPath('deleted_at', Contact::withTrashed()->findOrFail($contact->id)->deleted_at?->toIso8601String())
        ->assertJsonPath('identities.0.display_value', $identity->display_value);
});

test('联系人详情包含合并日志用于活跃和已合并联系人', function () {
    [$app, $user] = createInstanceWithOwner();

    $target = Contact::factory()->create(['name' => '目标联系人']);
    ContactIdentity::factory()->email()->create([
        'contact_id' => $target->id,
        'value' => 'target@example.com',
        'display_value' => 'target@example.com',
    ]);

    $merged = Contact::factory()->create(['name' => '被合并联系人']);
    ContactIdentity::factory()->phone()->create([
        'contact_id' => $merged->id,
        'value' => '+8618995543120',
        'display_value' => '+8618995543120',
    ]);

    MergeContactsAction::run($target->id, $merged->id);

    $this->actingAs($user)
        ->getJson(route('app.contacts.show', ['id' => $target->id]))
        ->assertOk()
        ->assertJsonPath('activity_logs.0.action', 'merged_into_current')
        ->assertJsonPath('activity_logs.0.related_contact_name', '被合并联系人')
        ->assertJsonPath('activity_logs.0.identity_values.0', '+8618995543120');

    $this->actingAs($user)
        ->getJson(route('app.contacts.show', [
            'id' => $merged->id,
            'include_trashed' => 1,
        ]))
        ->assertOk()
        ->assertJsonPath('activity_logs.0.action', 'merged_into_other')
        ->assertJsonPath('activity_logs.0.related_contact_name', '目标联系人');
});

test('可以更新联系人通过HTTP', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->create();

    $this->actingAs($user)
        ->put(route('app.contacts.update', ['id' => $contact->id]), [
            'name' => '更新后',
            'type' => 'contact',
            'note' => '这个联系人需要优先处理',
        ])
        ->assertRedirect();

    $contact->refresh();
    expect($contact->name)->toBe('更新后')
        ->and($contact->type)->toBe(ContactType::Contact)
        ->and($contact->note)->toBe('这个联系人需要优先处理');

    $this->actingAs($user)
        ->getJson(route('app.contacts.show', ['id' => $contact->id]))
        ->assertOk()
        ->assertJsonPath('activity_logs.0.action', 'updated')
        ->assertJsonPath('activity_logs.0.actor_name', $user->name);
});

test('可以删除联系人通过HTTP', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->create();

    $this->actingAs($user)
        ->delete(route('app.contacts.destroy', ['id' => $contact->id]))
        ->assertRedirect();

    expect(Contact::find($contact->id))->toBeNull();
});

test('可以恢复联系人通过HTTP', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->create();

    DeleteContactAction::run($contact->id);

    $this->actingAs($user)
        ->put(route('app.contacts.restore', ['id' => $contact->id]))
        ->assertRedirect();

    expect(Contact::find($contact->id))->not->toBeNull();
});

test('可以合并联系人通过HTTP', function () {
    [$app, $user] = createInstanceWithOwner();

    $target = Contact::factory()->create(['name' => '目标']);
    ContactIdentity::factory()->email()->create([
        'contact_id' => $target->id,
        'value' => 'target@example.com',
    ]);

    $merged = Contact::factory()->create(['name' => '被合并']);
    ContactIdentity::factory()->phone()->create([
        'contact_id' => $merged->id,
        'value' => '+8613800138000',
    ]);

    $this->actingAs($user)
        ->post(route('app.contacts.merge', []), [
            'target_contact_id' => $target->id,
            'merged_contact_id' => $merged->id,
        ])
        ->assertRedirect();

    expect(Contact::find($merged->id))->toBeNull();
});

test('可以替换联系人身份通过HTTP', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->create();
    $identity = ContactIdentity::factory()->email()->create([
        'contact_id' => $contact->id,
        'value' => 'old@example.com',
        'display_value' => 'old@example.com',
    ]);

    $this->actingAs($user)
        ->put(route('app.contacts.identities.replace', [
            'contactId' => $contact->id,
            'identityId' => $identity->id,
        ]), [
            'value' => 'new@example.com',
        ])
        ->assertRedirect();

    expect(ContactIdentity::find($identity->id))->toBeNull()
        ->and($contact->fresh()->primary_email)->toBe('new@example.com');
});

test('可以删除联系人身份通过HTTP', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->create();
    $identity = ContactIdentity::factory()->email()->create([
        'contact_id' => $contact->id,
        'value' => 'delete-me@example.com',
        'display_value' => 'delete-me@example.com',
    ]);

    $contact->syncPrimaryFields();

    $this->actingAs($user)
        ->delete(route('app.contacts.identities.destroy', [
            'contactId' => $contact->id,
            'identityId' => $identity->id,
        ]))
        ->assertRedirect();

    expect(ContactIdentity::find($identity->id))->toBeNull()
        ->and($contact->fresh()->primary_email)->toBeNull();
});

test('可以添加身份通过HTTP', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->create();

    $this->actingAs($user)
        ->post(route('app.contacts.identities.store', ['contactId' => $contact->id]), [
            'type' => 'email',
            'value' => 'added@example.com',
        ])
        ->assertRedirect();

    expect($contact->identities()->count())->toBe(1);
});

test('拒绝创建邮箱身份且无效格式', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->create();

    $this->actingAs($user)
        ->post(route('app.contacts.identities.store', ['contactId' => $contact->id]), [
            'type' => 'email',
            'value' => 'not-a-valid-email',
        ])
        ->assertSessionHasErrors('value');

    expect($contact->identities()->count())->toBe(0);
});

test('拒绝替换邮箱身份且无效格式', function () {
    [$app, $user] = createInstanceWithOwner();

    $contact = Contact::factory()->create();
    $identity = ContactIdentity::factory()->email()->create([
        'contact_id' => $contact->id,
        'value' => 'old@example.com',
        'display_value' => 'old@example.com',
    ]);

    $this->actingAs($user)
        ->put(route('app.contacts.identities.replace', [
            'contactId' => $contact->id,
            'identityId' => $identity->id,
        ]), [
            'value' => 'not-a-valid-email',
        ])
        ->assertSessionHasErrors('value');

    expect(ContactIdentity::find($identity->id))->not->toBeNull();
});
