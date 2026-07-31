<?php

use App\Enums\AiModelPurpose;
use App\Enums\AttachmentPurpose;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Models\AiModel;
use App\Models\Attachment;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Services\Ai\NeuronStructuredGenerator;
use App\Services\Ai\Schemas\InboxReplyPolishSchema;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NeuronAI\Chat\Enums\SourceType;
use NeuronAI\Chat\Messages\ContentBlocks\ImageContent;
use NeuronAI\Chat\Messages\ContentBlocks\TextContent;
use NeuronAI\Chat\Messages\UserMessage;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function (): void {
    $this->user = $this->createUserWithInstance();
});

/**
 * 绑定一个返回固定候选并记录调用入参的假结构化生成器。
 *
 * @param  list<string>  $candidates
 * @return object{model: ?AiModel, instructions: ?string, userMessage: ?string}
 */
function fakeReplyGenerator(array $candidates): object
{
    $capture = new class
    {
        public ?AiModel $model = null;

        public ?string $instructions = null;

        public ?string $userMessage = null;

        public ?UserMessage $message = null;
    };

    $schema = new InboxReplyPolishSchema;
    $schema->candidates = $candidates;

    $generator = Mockery::mock(NeuronStructuredGenerator::class);
    $generator->shouldReceive('generateFromMessage')
        ->andReturnUsing(function (AiModel $model, string $instructions, UserMessage $userMessage, string $class) use ($schema, $capture) {
            expect($class)->toBe(InboxReplyPolishSchema::class);
            $capture->model = $model;
            $capture->instructions = $instructions;
            $capture->userMessage = $userMessage->getContent();
            $capture->message = $userMessage;

            return $schema;
        });
    app()->instance(NeuronStructuredGenerator::class, $generator);

    return $capture;
}

/**
 * 绑定一个断言「绝不被调用」的假结构化生成器。
 */
function fakeReplyGeneratorNeverCalled(): void
{
    $generator = Mockery::mock(NeuronStructuredGenerator::class);
    $generator->shouldNotReceive('generateFromMessage');
    app()->instance(NeuronStructuredGenerator::class, $generator);
}

/** 从全局 BackgroundTask 模型池准备回复润色模型。 */
function seedReplyPolishModel(): AiModel
{
    return makeAiModel(AiModelPurpose::BackgroundTask);
}

function createReplyPolishConversation(GeneralSettings $app, User $user, array $attributes = []): Conversation
{
    $contact = Contact::factory()->create([
        'name' => 'Mia',
    ]);

    return Conversation::factory()
        ->forContact($contact)
        ->assignedTo($user)
        ->create(array_merge([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
            'visitor_locale' => 'en-US',
            'subject' => 'Refund status',
            'summary' => 'Visitor wants to know when a refund will arrive.',
        ], $attributes));
}

test('收件箱回复助手会把模式语气和会话上下文交给生成器', function (): void {
    $model = seedReplyPolishModel();
    $conversation = createReplyPolishConversation($this->instance, $this->user);

    $visitorMessage = ConversationMessage::factory()->forConversation($conversation)->visitorText()->create([
        'sender_name' => 'Mia',
        'content' => 'When will my refund arrive?',
    ]);
    ConversationMessage::factory()->forConversation($conversation)->create([
        'sender_user_id' => $this->user->id,
        'sender_name' => $this->user->name,
        'role' => MessageRole::Teammate,
        'kind' => MessageKind::Text,
        'content' => 'We are checking it for you.',
    ]);

    $capture = fakeReplyGenerator([
        'Hi, we are checking the refund status for you now.',
        'Hi Mia, we are checking your refund status now.',
        'Thanks for waiting. We are checking the refund status.',
    ]);

    $this->actingAs($this->user)
        ->postJson('/app'.'/inbox/'.$conversation->id.'/reply/polish', [
            'content' => 'hi we check refund now',
            'mode' => 'rewrite',
            'tone' => 'friendly',
            'quoted_message_id' => $visitorMessage->id,
        ])
        ->assertOk()
        ->assertJsonCount(3, 'candidates')
        ->assertJsonPath('candidates.0.content', 'Hi, we are checking the refund status for you now.');

    expect($capture->model->model_id)->toBe($model->model_id)
        ->and($capture->instructions)->toContain('改写')
        ->and($capture->instructions)->toContain('友好')
        ->and($capture->userMessage)->toContain('hi we check refund now')
        ->and($capture->userMessage)->toContain('en-US')
        ->and($capture->userMessage)->toContain('Refund status')
        ->and($capture->userMessage)->toContain('When will my refund arrive?')
        ->and($capture->userMessage)->toContain('We are checking it for you.');
});

test('收件箱回复助手允许空内容生成回复候选', function (): void {
    seedReplyPolishModel();
    $conversation = createReplyPolishConversation($this->instance, $this->user);

    $capture = fakeReplyGenerator([
        'We can help check that for you.',
        'I can look into this right away.',
        'Let me confirm the details for you.',
    ]);

    $this->actingAs($this->user)
        ->postJson('/app'.'/inbox/'.$conversation->id.'/reply/polish', [
            'content' => '',
            'mode' => 'reply',
            'tone' => 'professional',
        ])
        ->assertOk()
        ->assertJsonCount(3, 'candidates')
        ->assertJsonPath('candidates.1.content', 'I can look into this right away.');

    expect($capture->instructions)->toContain('撰写')
        ->and($capture->instructions)->toContain('专业');
});

test('收件箱回复助手在改写模式拒绝纯空白内容且不会调用运行时', function (): void {
    seedReplyPolishModel();
    $conversation = createReplyPolishConversation($this->instance, $this->user);
    fakeReplyGeneratorNeverCalled();

    $this->actingAs($this->user)
        ->postJson('/app'.'/inbox/'.$conversation->id.'/reply/polish', [
            'content' => '   ',
            'mode' => 'rewrite',
            'tone' => 'keep',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('content');
});

test('收件箱回复助手拒绝不属于当前会话的引用消息', function (): void {
    seedReplyPolishModel();
    $conversation = createReplyPolishConversation($this->instance, $this->user);
    $foreignConversation = createReplyPolishConversation($this->instance, $this->user);
    $foreignMessage = ConversationMessage::factory()->forConversation($foreignConversation)->visitorText()->create([
        'content' => 'This message is from another conversation.',
    ]);
    fakeReplyGeneratorNeverCalled();

    $this->actingAs($this->user)
        ->postJson('/app'.'/inbox/'.$conversation->id.'/reply/polish', [
            'content' => '',
            'mode' => 'reply',
            'tone' => 'keep',
            'quoted_message_id' => $foreignMessage->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quoted_message_id');
});

test('收件箱回复助手在运行时返回空候选时抛出业务错误', function (): void {
    seedReplyPolishModel();
    $conversation = createReplyPolishConversation($this->instance, $this->user);

    fakeReplyGenerator([]);

    $this->actingAs($this->user)
        ->postJson('/app'.'/inbox/'.$conversation->id.'/reply/polish', [
            'content' => 'please rewrite',
            'mode' => 'rewrite',
            'tone' => 'keep',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('conversation.errors.reply_polish_failed'));
});

test('收件箱回复助手只向运行时发送最近三十条文本消息', function (): void {
    seedReplyPolishModel();
    $conversation = createReplyPolishConversation($this->instance, $this->user);

    for ($i = 1; $i <= 35; $i++) {
        ConversationMessage::factory()->forConversation($conversation)->visitorText()->create([
            'content' => "Message {$i}",
        ]);
    }

    $capture = fakeReplyGenerator([
        'We can help check that for you.',
        'I can look into this right away.',
        'Let me confirm the details for you.',
    ]);

    $this->actingAs($this->user)
        ->postJson('/app'.'/inbox/'.$conversation->id.'/reply/polish', [
            'content' => '',
            'mode' => 'reply',
            'tone' => 'keep',
        ])
        ->assertOk();

    expect($capture->userMessage)->toContain('"content": "Message 6"')
        ->and($capture->userMessage)->toContain('"content": "Message 35"')
        ->and($capture->userMessage)->not->toContain('"content": "Message 5"');
});

test('收件箱回复润色把最近的访客图片作为图片块喂给模型', function (): void {
    seedReplyPolishModel()->update(['supports_image_input' => true]);
    $conversation = createReplyPolishConversation($this->instance, $this->user);

    $imageMessage = ConversationMessage::factory()->forConversation($conversation)->create([
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Image,
        'content' => null,
    ]);
    $attachment = Attachment::factory()->create([
        'mime_type' => 'image/png',
        'byte_size' => 2048,
        'purpose' => AttachmentPurpose::ConversationImage,
        'attachable_type' => $imageMessage->getMorphClass(),
        'attachable_id' => $imageMessage->id,
    ]);
    $capture = fakeReplyGenerator(['Sure, I can help with that.']);

    $this->actingAs($this->user)
        ->postJson('/app'.'/inbox/'.$conversation->id.'/reply/polish', [
            'content' => '',
            'mode' => 'reply',
            'tone' => 'keep',
        ])
        ->assertOk();

    $imageBlocks = array_filter(
        $capture->message->getContentBlocks(),
        static fn ($block): bool => $block instanceof ImageContent,
    );
    $imageBlock = array_first($imageBlocks);

    expect($imageBlocks)->toHaveCount(1)
        ->and($imageBlock->sourceType)->toBe(SourceType::URL)
        ->and($imageBlock->content)->toBe($attachment->full_url);
});

test('收件箱回复润色按模型能力把图片降级为文字占位', function (): void {
    seedReplyPolishModel();
    $conversation = createReplyPolishConversation($this->instance, $this->user);

    $imageMessage = ConversationMessage::factory()->forConversation($conversation)->create([
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Image,
        'content' => null,
    ]);
    Attachment::factory()->create([
        'mime_type' => 'image/png',
        'byte_size' => 2048,
        'original_name' => 'damaged-product.png',
        'purpose' => AttachmentPurpose::ConversationImage,
        'attachable_type' => $imageMessage->getMorphClass(),
        'attachable_id' => $imageMessage->id,
    ]);
    $capture = fakeReplyGenerator(['Sure, I can help with that.']);

    $this->actingAs($this->user)
        ->postJson('/app'.'/inbox/'.$conversation->id.'/reply/polish', [
            'content' => '',
            'mode' => 'reply',
            'tone' => 'keep',
        ])
        ->assertOk();

    $fallbackBlocks = array_filter(
        $capture->message->getContentBlocks(),
        static fn ($block): bool => $block instanceof TextContent
            && str_contains((string) $block->content, 'damaged-product.png'),
    );

    expect($fallbackBlocks)->toHaveCount(1);
});

test('收件箱回复润色在 background_task 池没有可用模型时抛出业务错误', function (): void {
    // 不 seed 任何 background_task 模型：池为空，应直接抛 reply_polish_failed。
    $conversation = createReplyPolishConversation($this->instance, $this->user);
    fakeReplyGeneratorNeverCalled();

    $this->actingAs($this->user)
        ->postJson('/app'.'/inbox/'.$conversation->id.'/reply/polish', [
            'content' => 'please polish',
            'mode' => 'rewrite',
            'tone' => 'keep',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('conversation.errors.reply_polish_failed'));
});

test('收件箱回复润色复用会话回复权限控制', function (): void {
    seedReplyPolishModel();
    $otherUser = User::factory()->create();
    $conversation = createReplyPolishConversation($this->instance, $otherUser);
    fakeReplyGeneratorNeverCalled();

    $this->actingAs($this->user)
        ->postJson('/app'.'/inbox/'.$conversation->id.'/reply/polish', [
            'content' => 'please polish',
            'mode' => 'rewrite',
            'tone' => 'keep',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('conversation.errors.reply_not_allowed_for_assignee'));
});
