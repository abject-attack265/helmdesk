<?php

use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Models\Attachment;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\Ai\ConversationAiContextBuilder;
use App\Services\Reception\ReceptionHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('当前会话历史按时序映射为标准 user assistant 消息', function () {
    $conversation = Conversation::factory()->create();
    ConversationMessage::factory()->visitorText()->forConversation($conversation)->create(['content' => '你们几点关门']);
    ConversationMessage::factory()->aiText()->forConversation($conversation)->create(['content' => '晚上十点关门']);
    ConversationMessage::factory()->forConversation($conversation)->create([
        'role' => MessageRole::Teammate,
        'kind' => MessageKind::Text,
        'content' => '还有其他问题吗',
    ]);

    $messages = app(ReceptionHistory::class)->currentMessages($conversation);

    expect(array_map(static fn ($message): string => $message->getRole(), $messages))
        ->toBe(['user', 'assistant', 'assistant'])
        ->and(array_map(static fn ($message): ?string => $message->getContent(), $messages))
        ->toBe(['你们几点关门', '晚上十点关门', '还有其他问题吗']);
});

test('当前轮消息在加载历史时排除', function () {
    $conversation = Conversation::factory()->create();
    ConversationMessage::factory()->visitorText()->forConversation($conversation)->create(['content' => '你好']);
    $current = ConversationMessage::factory()->visitorText()->forConversation($conversation)->create(['content' => '几点关门']);

    $messages = app(ReceptionHistory::class)->currentMessages(
        $conversation,
        [(string) $current->id],
    );

    expect($messages)->toHaveCount(1)
        ->and($messages[0]->getContent())->toBe('你好');
});

test('当前会话历史不限制消息条数并从最新消息向前保留最多 50K 字符', function () {
    $conversation = Conversation::factory()->create();
    foreach (range(1, 60) as $index) {
        ConversationMessage::factory()->visitorText()->forConversation($conversation)->create([
            'content' => "短消息 {$index}",
        ]);
    }

    expect(app(ReceptionHistory::class)->currentMessages($conversation))->toHaveCount(60);

    $largeConversation = Conversation::factory()->create();
    foreach (['甲', '乙', '丙'] as $character) {
        ConversationMessage::factory()->visitorText()->forConversation($largeConversation)->create([
            'content' => str_repeat($character, 20_000),
        ]);
    }

    $limited = app(ReceptionHistory::class)->currentMessages($largeConversation);
    $totalCharacters = array_sum(array_map(
        static fn ($message): int => mb_strlen((string) $message->getContent()),
        $limited,
    ));

    expect($limited)->toHaveCount(3)
        ->and($totalCharacters)->toBe(ConversationAiContextBuilder::DEFAULT_MAX_CHARACTERS)
        ->and($limited[0]->getContent())->toStartWith('[较早内容已截断] ')
        ->and($limited[2]->getContent())->toBe(str_repeat('丙', 20_000));

    $exactLimitConversation = Conversation::factory()->create();
    ConversationMessage::factory()->visitorText()->forConversation($exactLimitConversation)->create([
        'content' => '应被省略的较早消息',
    ]);
    ConversationMessage::factory()->aiText()->forConversation($exactLimitConversation)->create([
        'content' => str_repeat('新', ConversationAiContextBuilder::DEFAULT_MAX_CHARACTERS),
    ]);

    $exactLimit = app(ReceptionHistory::class)->currentMessages($exactLimitConversation);

    expect($exactLimit)->toHaveCount(1)
        ->and(mb_strlen((string) $exactLimit[0]->getContent()))->toBe(ConversationAiContextBuilder::DEFAULT_MAX_CHARACTERS)
        ->and($exactLimit[0]->getContent())->toStartWith('[较早内容已截断] ');
});

test('历史媒体附件转换为带访问地址的文字占位', function () {
    fakeAttachmentStorage();
    $conversation = Conversation::factory()->create();
    $imageMessage = ConversationMessage::factory()->visitorText()->forConversation($conversation)->create(['content' => '看下这个']);
    $attachment = Attachment::factory()->create([
        'attachable_type' => $imageMessage->getMorphClass(),
        'attachable_id' => $imageMessage->id,
        'original_name' => 'photo.png',
        'mime_type' => 'image/png',
    ]);

    $messages = app(ReceptionHistory::class)->currentMessages($conversation);

    expect($messages)->toHaveCount(1)
        ->and($messages[0]->getRole())->toBe('user')
        ->and($messages[0]->getContent())->toBe(
            "看下这个 [图片：photo.png；链接：{$attachment->full_url}]",
        );
});

test('无历史时返回空结果', function () {
    $conversation = Conversation::factory()->create();

    expect(app(ReceptionHistory::class)->currentMessages($conversation))->toBe([])
        ->and(app(ReceptionHistory::class)->contactContext($conversation))->toBe('');
});

test('同一联系人的其他会话按时间进入联系人背景', function () {
    $contact = Contact::factory()->create();
    $historical = Conversation::factory()->forContact($contact)->create();
    $olderHistorical = Conversation::factory()->forContact($contact)->create();
    $current = Conversation::factory()->forContact($contact)->create();

    ConversationMessage::factory()->visitorText()->forConversation($historical)->create([
        'content' => '之前咨询过退款',
        'created_at' => now()->subDay(),
    ]);
    ConversationMessage::factory()->aiText()->forConversation($historical)->create([
        'content' => '退款申请正在审核',
        'created_at' => now()->subDay()->addMinute(),
    ]);
    ConversationMessage::factory()->visitorText()->forConversation($olderHistorical)->create([
        'content' => '更早咨询过发票',
        'created_at' => now()->subDays(2),
    ]);
    $context = app(ReceptionHistory::class)->contactContext($current);

    expect($context)->toContain('[访客] 更早咨询过发票')
        ->toContain('[访客] 之前咨询过退款')
        ->toContain('[AI客服] 退款申请正在审核');
});

test('联系人其他会话背景从最新消息向前保留最多 50K 字符', function () {
    $contact = Contact::factory()->create();
    $historical = Conversation::factory()->forContact($contact)->create();
    $current = Conversation::factory()->forContact($contact)->create();

    ConversationMessage::factory()->visitorText()->forConversation($historical)->create([
        'content' => str_repeat('旧', 60_000),
        'created_at' => now()->subDay(),
    ]);
    ConversationMessage::factory()->aiText()->forConversation($historical)->create([
        'content' => str_repeat('新', 60_000),
        'created_at' => now()->subDay()->addMinute(),
    ]);

    $context = app(ReceptionHistory::class)->contactContext($current);

    expect(mb_strlen($context))->toBe(ReceptionHistory::CONTACT_HISTORY_MAX_CHARACTERS)
        ->and($context)->toContain('[较早内容已截断] ')
        ->and($context)->toContain(str_repeat('新', 40_000));
});

test('联系人其他会话背景不限制会话与消息条数', function () {
    $contact = Contact::factory()->create();
    $firstHistorical = Conversation::factory()->forContact($contact)->create();
    $secondHistorical = Conversation::factory()->forContact($contact)->create();
    $current = Conversation::factory()->forContact($contact)->create();

    foreach (range(1, 30) as $index) {
        ConversationMessage::factory()->visitorText()->forConversation($firstHistorical)->create([
            'content' => "第一段历史 {$index}",
            'created_at' => now()->subDays(2)->addSeconds($index),
        ]);
    }
    foreach (range(31, 60) as $index) {
        ConversationMessage::factory()->aiText()->forConversation($secondHistorical)->create([
            'content' => "第二段历史 {$index}",
            'created_at' => now()->subDay()->addSeconds($index),
        ]);
    }

    $context = app(ReceptionHistory::class)->contactContext($current);

    expect(substr_count($context, '[历史会话 '))->toBe(2)
        ->and($context)->toContain('[访客] 第一段历史 1')
        ->and($context)->toContain('[AI客服] 第二段历史 60');
});
