<?php

use App\Actions\AiChat\FinalizeAiAssistantMessageAction;
use App\Actions\AiChat\ReadAiAssistantHistoryAction;
use App\Actions\AiChat\SearchAiAssistantHistoryAction;
use App\Actions\AiChat\ShowAiAssistantMessagesAction;
use App\Actions\AiChat\StartAiAssistantRoundAction;
use App\Enums\AiAssistantMessageSegmentType;
use App\Enums\AiAssistantMessageStatus;
use App\Models\AiAssistantMessage;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

test('同一联系人的不同客户会话共享 AI 历史且不同联系人相互隔离', function () {
    $contact = Contact::factory()->create();
    $firstConversation = Conversation::factory()->forContact($contact)->create();
    $latestConversation = Conversation::factory()->forContact($contact)->create();
    $unrelatedConversation = Conversation::factory()
        ->forContact(Contact::factory()->create())
        ->create();
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();
    $startRound = app(StartAiAssistantRoundAction::class);

    $firstRound = $startRound->handle(
        $firstConversation,
        $firstUser,
        Str::uuid()->toString(),
        '历史问题',
    );
    app(FinalizeAiAssistantMessageAction::class)->handle(
        $firstRound->assistant_message_id,
        AiAssistantMessageStatus::Completed,
        [['type' => 'text', 'content' => '历史回答']],
    );

    $continuedRound = $startRound->handle(
        $latestConversation,
        $secondUser,
        Str::uuid()->toString(),
        '继续追问',
        threadId: $firstRound->thread_id,
    );

    expect($continuedRound->thread_id)->toBe($firstRound->thread_id)
        ->and(AiAssistantMessage::query()->findOrFail($continuedRound->user_message_id)->sender_user_id)
        ->toBe($secondUser->id);

    $threads = app(ShowAiAssistantMessagesAction::class)->handle($latestConversation);

    expect($threads)->toHaveCount(1)
        ->and($threads[0]->messages)->toHaveCount(4)
        ->and($threads[0]->messages[0]->content)->toBe('历史问题')
        ->and(app(ShowAiAssistantMessagesAction::class)->handle($unrelatedConversation))->toBe([]);

    expect(fn () => $startRound->handle(
        $unrelatedConversation,
        $secondUser,
        Str::uuid()->toString(),
        '越权续写',
        threadId: $firstRound->thread_id,
    ))->toThrow(NotFoundHttpException::class);
});

test('历史检索返回关键词位置并按客服时区读取连续上下文', function () {
    $contact = Contact::factory()->create();
    $conversation = Conversation::factory()->forContact($contact)->create();
    $user = User::factory()->create();
    $startRound = app(StartAiAssistantRoundAction::class);
    $historyRound = $startRound->handle(
        $conversation,
        $user,
        Str::uuid()->toString(),
        '请计算特别订单的退款金额',
    );
    app(FinalizeAiAssistantMessageAction::class)->handle(
        $historyRound->assistant_message_id,
        AiAssistantMessageStatus::Completed,
        [['type' => 'text', 'content' => '特别订单退款金额是 128 元']],
    );
    $currentRound = $startRound->handle(
        $conversation,
        $user,
        Str::uuid()->toString(),
        '开始新话题',
    );
    $timezone = new DateTimeZone('Asia/Shanghai');

    $search = app(SearchAiAssistantHistoryAction::class)->handle(
        $conversation,
        $currentRound->thread_id,
        ['特别订单', '不存在'],
        $timezone,
    );

    expect($search->matches)->toHaveCount(2)
        ->and($search->matches[0]->thread_id)->toBe($historyRound->thread_id)
        ->and($search->matches[0]->occurred_at)->toEndWith('+08:00');

    $page = app(ReadAiAssistantHistoryAction::class)->handle(
        $conversation,
        $currentRound->thread_id,
        $historyRound->thread_id,
        $timezone,
        offset: 0,
        limit: 2,
    );

    expect($page->messages)->toHaveCount(2)
        ->and($page->messages[0]['content'])->toBe('请计算特别订单的退款金额')
        ->and($page->messages[1]['content'])->toBe('特别订单退款金额是 128 元')
        ->and($page->messages[1]['occurred_at'])->toEndWith('+08:00');
});

test('已完成的助手回答不会被重复收口覆盖', function () {
    $round = app(StartAiAssistantRoundAction::class)->handle(
        Conversation::factory()->create(),
        User::factory()->create(),
        Str::uuid()->toString(),
        '问题',
    );
    $finalize = app(FinalizeAiAssistantMessageAction::class);

    $finalize->handle(
        $round->assistant_message_id,
        AiAssistantMessageStatus::Completed,
        [['type' => 'text', 'content' => '最终回答']],
    );
    $finalize->handle($round->assistant_message_id, AiAssistantMessageStatus::Failed);

    $message = AiAssistantMessage::query()->findOrFail($round->assistant_message_id);

    expect($message->status)->toBe(AiAssistantMessageStatus::Completed)
        ->and($message->content)->toBe('最终回答');
});

test('刷新查询按原顺序返回回答文本和工具片段', function () {
    $conversation = Conversation::factory()->create();
    $round = app(StartAiAssistantRoundAction::class)->handle(
        $conversation,
        User::factory()->create(),
        Str::uuid()->toString(),
        '查询订单',
    );

    app(FinalizeAiAssistantMessageAction::class)->handle(
        $round->assistant_message_id,
        AiAssistantMessageStatus::Completed,
        [
            ['type' => 'text', 'content' => '正在查询。'],
            [
                'type' => 'tool_call',
                'tool' => 'lookup_order',
                'args' => '{"order_id":"ORDER-1"}',
            ],
            [
                'type' => 'tool_result',
                'tool' => 'lookup_order',
                'content' => '{"status":"shipped"}',
            ],
            ['type' => 'text', 'content' => '订单已发货'],
        ],
    );

    $message = app(ShowAiAssistantMessagesAction::class)
        ->handle($conversation)[0]
        ->messages[1];

    expect($message->content)->toBe('正在查询。订单已发货')
        ->and($message->segments)->toHaveCount(4)
        ->and($message->segments[0]->type)->toBe(AiAssistantMessageSegmentType::Text)
        ->and($message->segments[1]->type)->toBe(AiAssistantMessageSegmentType::ToolCall)
        ->and($message->segments[1]->tool)->toBe('lookup_order')
        ->and($message->segments[2]->type)->toBe(AiAssistantMessageSegmentType::ToolResult)
        ->and($message->segments[3]->content)->toBe('订单已发货');
});

test('助手回答拒绝无法恢复到界面的片段', function () {
    $round = app(StartAiAssistantRoundAction::class)->handle(
        Conversation::factory()->create(),
        User::factory()->create(),
        Str::uuid()->toString(),
        '生成回复',
    );
    $finalize = app(FinalizeAiAssistantMessageAction::class);

    expect(fn () => $finalize->handle(
        $round->assistant_message_id,
        AiAssistantMessageStatus::Completed,
        [['type' => 'text', 'content' => '']],
    ))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $finalize->handle(
            $round->assistant_message_id,
            AiAssistantMessageStatus::Completed,
            [['type' => 'tool_call', 'args' => '{}']],
        ))->toThrow(InvalidArgumentException::class);
});
