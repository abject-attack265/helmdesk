<?php

use App\Actions\Conversation\BuildConversationTimelineMessageMapAction;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('一页消息正文与引用消息共用一次附件查询', function () {
    $conversation = Conversation::factory()->create();
    $bodyAttachment = Attachment::factory()->create([
        'object_key' => 'conversation_file/body.txt',
    ]);
    $quotedAttachment = Attachment::factory()->create([
        'object_key' => 'conversation_image/quoted.png',
    ]);
    $quotedMessage = ConversationMessage::factory()
        ->forConversation($conversation)
        ->create([
            'role' => MessageRole::Visitor,
            'kind' => MessageKind::Image,
            'content' => null,
            'sender_name' => '访客',
            'payload' => [
                'attachments' => [
                    ConversationMessage::attachmentSnapshot($quotedAttachment),
                ],
            ],
        ]);
    $rows = new Collection([
        (object) [
            'id' => 'body-message-1',
            'conversation_id' => (string) $conversation->id,
            'type' => 'message',
            'payload' => json_encode([
                'attachments' => [
                    ConversationMessage::attachmentSnapshot($bodyAttachment),
                ],
            ], JSON_THROW_ON_ERROR),
            'quoted_message_id' => (string) $quotedMessage->id,
            'recalled_at' => null,
        ],
        (object) [
            'id' => 'body-message-2',
            'conversation_id' => (string) $conversation->id,
            'type' => 'message',
            'payload' => json_encode([
                'attachments' => [
                    ConversationMessage::attachmentSnapshot($bodyAttachment),
                ],
            ], JSON_THROW_ON_ERROR),
            'quoted_message_id' => null,
            'recalled_at' => null,
        ],
        (object) [
            'id' => 'event-1',
            'conversation_id' => (string) $conversation->id,
            'type' => 'event',
            'payload' => ['attachments' => [ConversationMessage::attachmentSnapshot($bodyAttachment)]],
            'quoted_message_id' => null,
            'recalled_at' => null,
        ],
    ]);
    $attachmentQueries = [];

    DB::listen(function (QueryExecuted $query) use (&$attachmentQueries): void {
        if (str_contains($query->sql, 'from "attachments"')) {
            $attachmentQueries[] = $query->sql;
        }
    });

    $map = BuildConversationTimelineMessageMapAction::run($rows);

    expect($attachmentQueries)->toHaveCount(1)
        ->and($map->message_payloads)->toHaveKeys(['body-message-1', 'body-message-2'])
        ->and($map->message_payloads['body-message-1']['attachments'][0]['url'])
        ->toBe($bodyAttachment->full_url)
        ->and($map->message_payloads['body-message-2']['attachments'][0]['url'])
        ->toBe($bodyAttachment->full_url)
        ->and($map->quoted_messages[(string) $quotedMessage->id]->attachments[0]['url'])
        ->toBe($quotedAttachment->full_url);
});

test('页面没有附件 ID 时保留消息载荷并返回空附件', function () {
    $conversation = Conversation::factory()->create();
    $quotedMessage = ConversationMessage::factory()
        ->forConversation($conversation)
        ->create([
            'role' => MessageRole::Visitor,
            'kind' => MessageKind::Text,
            'content' => '引用正文',
            'sender_name' => '访客',
            'payload' => ['source' => 'web'],
        ]);
    $rows = new Collection([
        (object) [
            'id' => 'body-message',
            'conversation_id' => (string) $conversation->id,
            'type' => 'message',
            'payload' => json_encode(['source' => 'web'], JSON_THROW_ON_ERROR),
            'quoted_message_id' => (string) $quotedMessage->id,
            'recalled_at' => null,
        ],
    ]);

    $map = BuildConversationTimelineMessageMapAction::run($rows);

    expect($map->message_payloads['body-message'])->toBe(['source' => 'web'])
        ->and($map->quoted_messages[(string) $quotedMessage->id]->content)->toBe('引用正文')
        ->and($map->quoted_messages[(string) $quotedMessage->id]->attachments)->toBe([]);
});

test('已撤回引用消息只返回撤回快照', function () {
    $conversation = Conversation::factory()->create();
    $attachment = Attachment::factory()->create();
    $quotedMessage = ConversationMessage::factory()
        ->forConversation($conversation)
        ->recalled()
        ->create([
            'role' => MessageRole::Visitor,
            'kind' => MessageKind::File,
            'content' => '已撤回正文',
            'sender_name' => '访客',
            'payload' => [
                'attachments' => [
                    ConversationMessage::attachmentSnapshot($attachment),
                ],
            ],
        ]);
    $rows = new Collection([
        (object) [
            'id' => 'body-message',
            'conversation_id' => (string) $conversation->id,
            'type' => 'message',
            'payload' => null,
            'quoted_message_id' => (string) $quotedMessage->id,
            'recalled_at' => null,
        ],
    ]);

    $map = BuildConversationTimelineMessageMapAction::run($rows);
    $quote = $map->quoted_messages[(string) $quotedMessage->id];

    expect($quote->preview)->toBe(__('conversation.message_recalled_placeholder'))
        ->and($quote->content)->toBeNull()
        ->and($quote->attachments)->toBe([])
        ->and($quote->recalled_at)->not->toBeNull();
});

test('消息载荷必须是 JSON 对象', function () {
    $rows = new Collection([
        (object) [
            'id' => 'body-message',
            'conversation_id' => (string) Str::ulid(),
            'type' => 'message',
            'payload' => '[]',
            'quoted_message_id' => null,
            'recalled_at' => null,
        ],
    ]);

    expect(fn () => BuildConversationTimelineMessageMapAction::run($rows))
        ->toThrow(InvalidArgumentException::class);
});

test('引用消息缺失时显性失败', function () {
    $rows = new Collection([
        (object) [
            'id' => (string) Str::ulid(),
            'conversation_id' => (string) Str::ulid(),
            'type' => 'message',
            'payload' => null,
            'quoted_message_id' => (string) Str::ulid(),
            'recalled_at' => null,
        ],
    ]);

    expect(fn () => BuildConversationTimelineMessageMapAction::run($rows))
        ->toThrow(LogicException::class);
});

test('跨会话引用消息时显性失败', function () {
    $conversation = Conversation::factory()->create();
    $otherConversation = Conversation::factory()->create();
    $quotedMessage = ConversationMessage::factory()
        ->forConversation($otherConversation)
        ->visitorText()
        ->create();
    $rows = new Collection([
        (object) [
            'id' => (string) Str::ulid(),
            'conversation_id' => (string) $conversation->id,
            'type' => 'message',
            'payload' => null,
            'quoted_message_id' => (string) $quotedMessage->id,
            'recalled_at' => null,
        ],
    ]);

    expect(fn () => BuildConversationTimelineMessageMapAction::run($rows))
        ->toThrow(LogicException::class);
});
