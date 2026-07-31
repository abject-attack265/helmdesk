<?php

use App\Actions\Conversation\GenerateConversationSubjectAction;
use App\Enums\AiModelPurpose;
use App\Enums\ConversationInboxStatus;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Jobs\Conversation\GenerateConversationSubjectJob;
use App\Models\AiModel;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\Ai\NeuronChatGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Bus::fake([GenerateConversationSubjectJob::class]);
});

/**
 * 创建主题生成测试使用的最小会话。
 *
 * @param  array<string, mixed>  $attributes
 */
function createSubjectTestConversation(array $attributes = []): Conversation
{
    return Conversation::factory()->create([
        'subject' => null,
        ...$attributes,
    ]);
}

test('会话主题生成会调用 LLM 并写入清理后的主题', function () {
    makeAiModel(AiModelPurpose::BackgroundTask);
    $conversation = createSubjectTestConversation();
    ConversationMessage::factory()->forConversation($conversation)->visitorText()->create([
        'content' => '我想咨询一下订单退款什么时候到账，已经等了三天。',
    ]);

    $captured = null;
    mock(NeuronChatGenerator::class, function ($mock) use (&$captured) {
        $mock->shouldReceive('generate')
            ->once()
            ->andReturnUsing(function (AiModel $model, string $instructions, string $userMessage) use (&$captured): string {
                $captured = $userMessage;

                return ' “退款到账进度。” ';
            });
    });

    app(GenerateConversationSubjectAction::class)->handle($conversation);

    expect($conversation->fresh()->subject)->toBe('退款到账进度')
        ->and($captured)->toBe('我想咨询一下订单退款什么时候到账，已经等了三天。');
});

test('会话主题按显示宽度截断中文输出', function () {
    makeAiModel(AiModelPurpose::BackgroundTask);
    $conversation = createSubjectTestConversation();
    ConversationMessage::factory()->forConversation($conversation)->visitorText()->create([
        'content' => '你好啊',
    ]);
    $generatedSubject = str_repeat('退款进度', 20);

    mock(NeuronChatGenerator::class, function ($mock) use ($generatedSubject) {
        $mock->shouldReceive('generate')->once()->andReturn($generatedSubject);
    });

    $subject = app(GenerateConversationSubjectAction::class)->handle($conversation);

    expect($subject)->toBe(mb_substr($generatedSubject, 0, 30))
        ->and(mb_strlen($subject))->toBe(30)
        ->and(mb_check_encoding($subject, 'UTF-8'))->toBeTrue();
});

test('已有主题保持人工设置结果', function () {
    $conversation = createSubjectTestConversation(['subject' => '人工设置主题']);
    ConversationMessage::factory()->forConversation($conversation)->visitorText()->create([
        'content' => '帮我查一下退款。',
    ]);

    mock(NeuronChatGenerator::class, function ($mock) {
        $mock->shouldNotReceive('generate');
    });

    $result = app(GenerateConversationSubjectAction::class)->handle($conversation);

    expect($result)->toBe('人工设置主题')
        ->and($conversation->fresh()->subject)->toBe('人工设置主题');
});

test('人工待接的无主题会话收到访客文本后派发主题生成任务', function () {
    $conversation = createSubjectTestConversation([
        'inbox_status' => ConversationInboxStatus::TeammatePending,
    ]);

    ConversationMessage::factory()
        ->forConversation($conversation)
        ->visitorText()
        ->create(['content' => '麻烦看下这个报告']);

    Bus::assertDispatched(
        GenerateConversationSubjectJob::class,
        fn (GenerateConversationSubjectJob $job): bool => $job->conversationId === (string) $conversation->id,
    );
});

test('消息事务回滚时不派发主题生成任务', function () {
    $conversation = createSubjectTestConversation();

    expect(fn () => DB::transaction(function () use ($conversation): void {
        ConversationMessage::factory()
            ->forConversation($conversation)
            ->visitorText()
            ->create(['content' => '这条消息会回滚']);

        throw new RuntimeException('rollback');
    }))->toThrow(RuntimeException::class, 'rollback');

    Bus::assertNotDispatched(GenerateConversationSubjectJob::class);
});

test('已有主题的访客文本消息保持当前主题状态', function () {
    $conversation = createSubjectTestConversation(['subject' => '已有主题']);

    ConversationMessage::factory()
        ->forConversation($conversation)
        ->visitorText()
        ->create(['content' => '继续补充一个问题。']);

    Bus::assertNotDispatched(GenerateConversationSubjectJob::class);
});

test('主题生成任务按会话去重', function () {
    $conversation = createSubjectTestConversation();
    $otherConversation = createSubjectTestConversation();

    ConversationMessage::factory()
        ->forConversation($conversation)
        ->visitorText()
        ->count(2)
        ->create();
    ConversationMessage::factory()
        ->forConversation($otherConversation)
        ->visitorText()
        ->create();

    Bus::assertDispatchedTimes(GenerateConversationSubjectJob::class, 2);
    Bus::assertDispatched(
        GenerateConversationSubjectJob::class,
        fn (GenerateConversationSubjectJob $job): bool => $job->conversationId === (string) $conversation->id,
    );
    Bus::assertDispatched(
        GenerateConversationSubjectJob::class,
        fn (GenerateConversationSubjectJob $job): bool => $job->conversationId === (string) $otherConversation->id,
    );
});

test('不符合访客文本条件的消息不会派发主题生成任务', function (
    MessageRole $role,
    MessageKind $kind,
    ?string $content,
) {
    $conversation = createSubjectTestConversation();

    ConversationMessage::factory()->forConversation($conversation)->create([
        'role' => $role,
        'kind' => $kind,
        'content' => $content,
    ]);

    Bus::assertNotDispatched(GenerateConversationSubjectJob::class);
})->with([
    '客服文本' => [MessageRole::Teammate, MessageKind::Text, '客服回复'],
    '访客图片' => [MessageRole::Visitor, MessageKind::Image, null],
    '空白访客文本' => [MessageRole::Visitor, MessageKind::Text, '  '],
]);
