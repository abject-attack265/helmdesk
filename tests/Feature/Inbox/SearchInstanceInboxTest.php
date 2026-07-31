<?php

use App\Actions\Inbox\SearchInstanceInboxAction;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationThread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
    bootTntSearch();
});

afterEach(function () {
    flushTntSearch();
});

/**
 * 创建归属指定联系人的进行中会话。
 */
function createOpenConversationForContact(Contact $contact): Conversation
{
    $channel = Channel::factory()->create();

    return Conversation::factory()
        ->forContactChannel($contact, $channel)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);
}

test('全局搜索返回应用内跨联系人的匹配消息并携带联系人信息', function () {
    $contactA = Contact::factory()->create([
        'name' => '张三',
    ]);
    $contactB = Contact::factory()->create([
        'name' => '李四',
    ]);

    $conversationA = createOpenConversationForContact($contactA);
    $conversationB = createOpenConversationForContact($contactB);

    ConversationMessage::factory()->create([
        'conversation_id' => $conversationA->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => '我的订单什么时候发货',
        'sender_name' => '张三',
    ]);

    ConversationMessage::factory()->create([
        'conversation_id' => $conversationB->id,
        'role' => MessageRole::Teammate,
        'kind' => MessageKind::Text,
        'content' => '已经安排发货了',
        'sender_name' => '客服小王',
    ]);

    $results = SearchInstanceInboxAction::run($this->user, '发货')->messages;

    expect($results)->toHaveCount(2);
    $resultsByContact = collect($results)->keyBy('contact_id');
    expect($resultsByContact[$contactA->id]->contact_name)->toBe('张三')
        ->and($resultsByContact[$contactB->id]->contact_name)->toBe('李四')
        ->and($resultsByContact[$contactA->id]->thread_id)->toBe(ConversationThread::requireForConversation($conversationA)->id)
        ->and($resultsByContact[$contactB->id]->thread_id)->toBe(ConversationThread::requireForConversation($conversationB)->id);
});

test('全局搜索跳过缺少渠道身份的消息', function () {
    $contact = Contact::factory()->create();
    $threadedConversation = createOpenConversationForContact($contact);
    $unthreadedConversation = Conversation::factory()
        ->forContact($contact)
        ->create();

    ConversationMessage::factory()->forConversation($unthreadedConversation)->create([
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => '匿名渠道退款咨询',
    ]);
    ConversationMessage::factory()->forConversation($threadedConversation)->create([
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => '正常渠道退款咨询',
    ]);

    $results = SearchInstanceInboxAction::run($this->user, '退款')->messages;

    expect($results)->toHaveCount(1)
        ->and($results[0]->thread_id)->toBe(ConversationThread::requireForConversation($threadedConversation)->id);
});

test('历史会话消息映射到联系人渠道的当前线程', function () {
    $contact = Contact::factory()->create();
    $channel = Channel::factory()->create();
    $closed = Conversation::factory()
        ->forContactChannel($contact, $channel)
        ->closed()
        ->create();
    $current = Conversation::factory()
        ->forContactChannel($contact, $channel)
        ->create();

    ConversationMessage::factory()->forConversation($closed)->create([
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => '历史退款咨询',
    ]);

    $results = SearchInstanceInboxAction::run($this->user, '历史退款')->messages;

    expect($results)->toHaveCount(1)
        ->and($results[0]->thread_id)->toBe(ConversationThread::requireForConversation($current)->id)
        ->and($results[0]->thread_id)->toBe(ConversationThread::requireForConversation($closed)->id);
});

test('全局搜索结果按消息时间倒序排列', function () {
    $contact = Contact::factory()->create([
    ]);
    $conversation = createOpenConversationForContact($contact);

    $older = ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => '发货进度如何',
        'created_at' => now()->subDay(),
    ]);

    $newer = ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Teammate,
        'kind' => MessageKind::Text,
        'content' => '今天就发货',
        'created_at' => now(),
    ]);

    $results = SearchInstanceInboxAction::run($this->user, '发货')->messages;

    expect(collect($results)->pluck('id')->all())->toBe([
        (string) $newer->id,
        (string) $older->id,
    ]);
});

test('超过一页的消息搜索结果按全局时间倒序排列', function () {
    $contact = Contact::factory()->create();
    $conversation = createOpenConversationForContact($contact);
    $start = now()->subMinutes(120);

    foreach (range(0, 119) as $index) {
        ConversationMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'role' => MessageRole::Visitor,
            'kind' => MessageKind::Text,
            'content' => '批量发货查询',
            'created_at' => $start->copy()->addMinutes($index),
        ]);
    }

    $results = SearchInstanceInboxAction::run($this->user, '批量发货')->messages;
    $expectedIds = ConversationMessage::query()
        ->where('conversation_id', $conversation->id)
        ->orderByDesc('created_at')
        ->orderByDesc('id')
        ->limit(50)
        ->pluck('id')
        ->map(static fn ($id): string => (string) $id)
        ->all();

    expect(collect($results)->pluck('id')->all())->toBe($expectedIds);
});

test('中文搜索支持字符乱序与跨分词边界命中', function () {
    $contact = Contact::factory()->create([
    ]);
    $conversation = createOpenConversationForContact($contact);

    ConversationMessage::factory()->forConversation($conversation)->create([
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => '那我现在更新下咯',
    ]);

    ConversationMessage::factory()->forConversation($conversation)->create([
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => '订单退款流程已经提交',
    ]);

    // 乱序命中：查询字符全部出现即可，顺序无关（Telegram 语义）。
    $scrambled = SearchInstanceInboxAction::run($this->user, '下更新咯')->messages;
    expect(collect($scrambled)->pluck('content')->all())->toBe(['那我现在更新下咯']);

    // 跨分词边界命中：「单」藏在「订单」一词内部，字符级索引下仍可召回。
    $boundary = SearchInstanceInboxAction::run($this->user, '单退款')->messages;
    expect(collect($boundary)->pluck('content')->all())->toBe(['订单退款流程已经提交']);
});

test('搜索词无有效 token 时直接返回空结果', function () {
    expect(SearchInstanceInboxAction::run($this->user, '!!!')->messages)->toBe([]);
});

test('按联系人过滤时只返回该联系人的匹配消息', function () {
    $contactA = Contact::factory()->create([
    ]);
    $contactB = Contact::factory()->create([
    ]);

    $conversationA = createOpenConversationForContact($contactA);
    $conversationB = createOpenConversationForContact($contactB);

    ConversationMessage::factory()->create([
        'conversation_id' => $conversationA->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => '退款问题',
        'sender_name' => 'A',
    ]);

    ConversationMessage::factory()->create([
        'conversation_id' => $conversationB->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => '退款问题',
        'sender_name' => 'B',
    ]);

    $results = SearchInstanceInboxAction::run($this->user, '退款', $contactA->id)->messages;

    expect($results)->toHaveCount(1);
    expect($results[0]->thread_id)->toBe(ConversationThread::requireForConversation($conversationA)->id);
});

test('全局搜索接口需要认证', function () {
    $this->getJson('/app'.'/inbox/search?search=test')
        ->assertUnauthorized();
});

test('全局搜索接口返回 JSON 结果', function () {
    $contact = Contact::factory()->create([
        'name' => '王五',
    ]);
    $conversation = createOpenConversationForContact($contact);

    ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => '你好请问在吗',
        'sender_name' => '王五',
    ]);

    $this->actingAs($this->user)
        ->getJson('/app'.'/inbox/search?search='.urlencode('你好'))
        ->assertOk()
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.thread_id', ConversationThread::requireForConversation($conversation)->id)
        ->assertJsonPath('messages.0.contact_id', $contact->id)
        ->assertJsonPath('messages.0.contact_name', '王五')
        ->assertJsonPath('messages.0.content', '你好请问在吗')
        ->assertJsonPath('messages.0.matched_content', '你好请问在吗')
        ->assertJsonPath('messages.0.sender_name', '王五')
        ->assertJsonPath('messages.0.role', 'visitor')
        ->assertJsonPath('messages.0.role_label', '访客');
});

// === 联系人分组 ===

test('搜索联系人姓名命中联系人分组并指向其最近会话', function () {
    $contact = Contact::factory()->create([
        'name' => '张三',
    ]);

    $older = createOpenConversationForContact($contact);
    $older->update(['last_message_at' => now()->subDay(), 'last_message_preview' => '旧会话']);

    $latest = createOpenConversationForContact($contact);
    $latest->update(['last_message_at' => now(), 'last_message_preview' => '最近一条消息']);

    $results = SearchInstanceInboxAction::run($this->user, '张三');

    expect($results->contacts)->toHaveCount(1)
        ->and($results->contacts[0]->id)->toBe($contact->id)
        ->and($results->contacts[0]->name)->toBe('张三')
        ->and($results->contacts[0]->thread_id)->toBe(ConversationThread::requireForConversation($latest)->id)
        ->and($results->contacts[0]->last_message_preview)->toBe('最近一条消息');
});

test('联系人分组保持 TNTSearch 的相关度召回顺序', function () {
    // 先建「张三丰」再建「张三」，让创建顺序与相关度顺序不一致：
    // 若结果直接沿用 DB 返回顺序而非召回顺序，本用例会失败。
    $zhangSanfeng = Contact::factory()->create([
        'name' => '张三丰',
    ]);
    $zhangSan = Contact::factory()->create([
        'name' => '张三',
    ]);
    createOpenConversationForContact($zhangSanfeng);
    createOpenConversationForContact($zhangSan);

    $recallOrder = Contact::search('张三')

        ->keys()
        ->all();

    $results = SearchInstanceInboxAction::run($this->user, '张三');

    expect(collect($results->contacts)->pluck('id')->all())->toBe($recallOrder);
});

test('大量无会话联系人不会把有会话的联系人挤出分组', function () {
    // 联系人索引以 created_at 为 default_sorting_field，同名命中按新建在前排序。
    // 有会话的这个建得最早、相关度排序垫底，只有过量召回后再过滤才能把它捞回来。
    $withConversation = Contact::factory()->create([
        'name' => '陈九',
    ]);
    createOpenConversationForContact($withConversation);

    Contact::factory()->count(25)->create([
        'name' => '陈九',
    ]);

    $results = SearchInstanceInboxAction::run($this->user, '陈九');

    expect(collect($results->contacts)->pluck('id')->all())->toBe([$withConversation->id]);
});

test('联系人最近会话尚无消息时回落到创建时间而非被丢弃', function () {
    $contact = Contact::factory()->create([
        'name' => '周八',
    ]);

    $conversation = createOpenConversationForContact($contact);
    $conversation->update(['last_message_at' => null]);

    $results = SearchInstanceInboxAction::run($this->user, '周八');

    expect($results->contacts)->toHaveCount(1)
        ->and($results->contacts[0]->thread_id)->toBe(ConversationThread::requireForConversation($conversation)->id);
});

test('没有任何会话的联系人不出现在联系人分组', function () {
    Contact::factory()->create([
        'name' => '王五',
    ]);

    $results = SearchInstanceInboxAction::run($this->user, '王五');

    expect($results->contacts)->toBe([]);
});

test('限定此聊天范围时不返回联系人分组', function () {
    $contact = Contact::factory()->create([
        'name' => '赵六',
    ]);
    $conversation = createOpenConversationForContact($contact);

    ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => '赵六在此留言',
        'sender_name' => '赵六',
    ]);

    $results = SearchInstanceInboxAction::run($this->user, '赵六', $contact->id);

    expect($results->contacts)->toBe([])
        ->and($results->messages)->toHaveCount(1);
});

test('搜索接口返回联系人与聊天记录两个分组', function () {
    $contact = Contact::factory()->create([
        'name' => '孙七',
    ]);
    $conversation = createOpenConversationForContact($contact);

    ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => '我是孙七',
        'sender_name' => '孙七',
    ]);

    $this->actingAs($this->user)
        ->getJson('/app'.'/inbox/search?search='.urlencode('孙七'))
        ->assertOk()
        ->assertJsonCount(1, 'contacts')
        ->assertJsonPath('contacts.0.id', $contact->id)
        ->assertJsonPath('contacts.0.name', '孙七')
        ->assertJsonPath('contacts.0.thread_id', ConversationThread::requireForConversation($conversation)->id)
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.thread_id', ConversationThread::requireForConversation($conversation)->id);
});

test('全局搜索关键词为空时返回验证错误', function () {
    $this->actingAs($this->user)
        ->getJson('/app'.'/inbox/search?search=')
        ->assertUnprocessable();
});
