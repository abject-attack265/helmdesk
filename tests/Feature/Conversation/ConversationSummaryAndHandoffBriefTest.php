<?php

use App\Actions\Contact\GenerateContactHandoffBriefAction;
use App\Actions\Conversation\GenerateConversationSummaryAction;
use App\Actions\Inbox\TranslateInboxContactHandoffBriefAction;
use App\Enums\AiModelPurpose;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Jobs\Contact\GenerateContactHandoffBriefJob;
use App\Models\AiModel;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ReceptionPlan;
use App\Models\ReceptionPlanVersion;
use App\Services\Ai\NeuronStructuredGenerator;
use App\Services\Ai\Schemas\ContactHandoffBriefSchema;
use App\Services\Ai\Schemas\ConversationSummarySchema;
use App\Services\AiRuntime\AiModelPool;
use App\Services\Conversation\ConversationLlmCandidateResolver;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use App\Services\Translation\TranslationProviderPool;
use App\Services\Translation\TranslationResult;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

/**
 * 创建会话摘要与接手简报测试上下文。
 *
 * @return array{0: GeneralSettings, 1: Contact, 2: Conversation, 3: AiModel}
 */
function createSummaryAndHandoffBriefContext(): array
{
    $app = createSystemSettings();
    $model = makeAiModel(AiModelPurpose::BackgroundTask);
    $plan = ReceptionPlan::factory()->create();
    $version = ReceptionPlanVersion::factory()
        ->for($plan, 'plan')
        ->create();
    $channel = Channel::factory()->create();
    $contact = Contact::factory()->visitor()->create(['locale' => 'en']);
    $conversation = Conversation::factory()->forContact($contact)->create([
        'channel_id' => $channel->id,
        'reception_plan_version_id' => $version->id,
        'inbox_status' => ConversationInboxStatus::AiHandling,
        'visitor_locale' => 'en',
        'summary' => null,
        'summary_locale' => null,
        'summary_translations' => null,
        'summary_last_message_seq_no' => 0,
        'ai_context' => null,
    ]);

    return [$app, $contact, $conversation, $model];
}

test('会话摘要生成会写入摘要水位并派发接手简报任务', function () {
    Bus::fake([GenerateContactHandoffBriefJob::class]);

    [, $contact, $conversation] = createSummaryAndHandoffBriefContext();
    ConversationMessage::factory()->forConversation($conversation)->visitorText()->create([
        'content' => 'I need to change the delivery address for order A100.',
    ]);
    ConversationMessage::factory()->forConversation($conversation)->aiText()->create([
        'content' => 'I can help. Please provide the new address.',
    ]);

    $schema = new ConversationSummarySchema;
    $schema->summary = 'Visitor wants to change the delivery address for order A100. AI asked for the new address.';

    $capturedInstructions = null;
    $capturedUserMessage = null;
    $generator = Mockery::mock(NeuronStructuredGenerator::class);
    $generator->shouldReceive('generate')
        ->once()
        ->andReturnUsing(function (AiModel $model, string $instructions, string $userMessage, string $class) use ($schema, &$capturedInstructions, &$capturedUserMessage) {
            expect($class)->toBe(ConversationSummarySchema::class);
            $capturedInstructions = $instructions;
            $capturedUserMessage = $userMessage;

            return $schema;
        });

    $notifier = Mockery::mock(ReceptionRealtimeNotifier::class);
    $notifier->shouldReceive('conversationChanged')
        ->once()
        ->with(Mockery::type(Conversation::class), 'conversation_summary_updated');

    $action = new GenerateConversationSummaryAction($generator, app(ConversationLlmCandidateResolver::class), $notifier);

    $result = $action->handle($conversation, force: true);
    $fresh = $conversation->fresh();

    expect($result)->toContain('Visitor wants to change')
        ->and($fresh->summary_locale)->toBe('en')
        ->and($fresh->summary_translations)->toBeNull()
        ->and($fresh->summary_last_message_seq_no)->toBe(2)
        ->and($fresh->summary_generated_at)->not->toBeNull()
        ->and($capturedInstructions)->toContain('en')
        ->and($capturedUserMessage)->toContain('visitor：I need to change the delivery address')
        ->and($capturedUserMessage)->toContain('ai：I can help.');

    Bus::assertDispatched(
        GenerateContactHandoffBriefJob::class,
        fn (GenerateContactHandoffBriefJob $job): bool => $job->contactId === (string) $contact->id
            && $job->conversationId === (string) $conversation->id,
    );
});

test('联系人接手简报基于当前会话与有限历史生成并执行输出上限', function () {
    [, $contact, $conversation] = createSummaryAndHandoffBriefContext();
    ConversationMessage::factory()->forConversation($conversation)->visitorText()->create([
        'content' => 'My current order cannot be confirmed.',
    ]);
    $historyAt = $conversation->created_at->copy()->subDay();
    Conversation::factory()->forContact($contact)->create([
        'channel_id' => $conversation->channel_id,
        'reception_plan_version_id' => $conversation->reception_plan_version_id,
        'summary' => 'Visitor asked about delivery address changes.',
        'summary_locale' => 'en',
        'status' => ConversationStatus::Closed,
        'closed_at' => $historyAt,
        'created_at' => $historyAt,
    ]);

    $schema = new ContactHandoffBriefSchema;
    $schema->brief = str_repeat('a', 260);
    $schema->next_actions = [
        str_repeat('b', 120),
        str_repeat('b', 120),
        'Check the confirmation failure.',
    ];

    $capturedUserMessage = null;
    $generator = Mockery::mock(NeuronStructuredGenerator::class);
    $generator->shouldReceive('generate')
        ->once()
        ->andReturnUsing(function (AiModel $model, string $instructions, string $userMessage, string $class) use ($schema, &$capturedUserMessage) {
            expect($class)->toBe(ContactHandoffBriefSchema::class);
            $capturedUserMessage = $userMessage;

            return $schema;
        });

    $notifier = Mockery::mock(ReceptionRealtimeNotifier::class);
    $notifier->shouldReceive('conversationChanged')
        ->once()
        ->with(Mockery::type(Conversation::class), 'contact_handoff_brief_updated', Mockery::on(
            fn (array $meta): bool => $meta['contact_id'] === (string) $contact->id,
        ));

    $action = new GenerateContactHandoffBriefAction($generator, app(AiModelPool::class), $notifier);
    $action->handle($contact, $conversation);
    $brief = $contact->fresh()->ai_context['handoff_brief'];

    expect(mb_strlen($brief['brief']))->toBe(240)
        ->and($brief['next_actions'])->toHaveCount(2)
        ->and(mb_strlen($brief['next_actions'][0]))->toBe(100)
        ->and($brief['next_actions'][1])->toBe('Check the confirmation failure.')
        ->and($brief['source_locale'])->toBe('en')
        ->and($brief['translations'])->toBe([])
        ->and($capturedUserMessage)->toContain('visitor：My current order cannot be confirmed.')
        ->and($capturedUserMessage)->toContain('Visitor asked about delivery address changes.');
});

test('收件箱联系人接手简报翻译缺失的目标语言内容', function () {
    [, $contact] = createSummaryAndHandoffBriefContext();

    $this->mock(TranslationProviderPool::class, function ($mock): void {
        $mock->shouldReceive('hasUsable')->andReturnTrue();
        $mock->shouldReceive('translate')->andReturnUsing(
            fn (string $content, string $source, string $target): TranslationResult => new TranslationResult(
                text: '译:'.$content,
                source_lang: 'en',
                target_lang: $target,
                provider_slug: 'fake',
                model: null,
                latency_ms: 1,
                char_count: mb_strlen($content),
            ),
        );
    });

    $contact->update([
        'ai_context' => [
            'handoff_brief' => [
                'brief' => 'Customer asks about billing.',
                'next_actions' => ['Send invoice'],
                'source_locale' => 'en',
                'translations' => [],
                'updated_at' => now()->toIso8601String(),
            ],
        ],
    ]);

    $translated = TranslateInboxContactHandoffBriefAction::run(
        contactId: (string) $contact->id,
        targetLocale: 'zh-CN',
    );

    expect($translated)->toBe(1);
    $brief = $contact->refresh()->ai_context['handoff_brief'];
    expect($brief['translations']['zh-CN']['brief']['text'])->toBe('译:Customer asks about billing.')
        ->and($brief['translations']['zh-CN']['next_actions'][0]['text'])->toBe('译:Send invoice');
});
