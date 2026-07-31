<?php

use App\Actions\Conversation\GenerateConversationTagsAction;
use App\Enums\AiModelPurpose;
use App\Models\AiModel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Services\Ai\NeuronStructuredGenerator;
use App\Services\Ai\Schemas\ConversationTagPickSchema;
use App\Services\Ai\Schemas\ConversationTagSelectionSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

/**
 * 把简化的 pick 数组转成 LLM 结构化标签选择结果。
 *
 * @param  list<array{tag_id: string, confidence: float, reason?: string|null}>  $picks
 */
function tagSelectionSchema(array $picks): ConversationTagSelectionSchema
{
    $schema = new ConversationTagSelectionSchema;
    $schema->tags = array_map(static function (array $pick): ConversationTagPickSchema {
        $item = new ConversationTagPickSchema;
        $item->tag_id = $pick['tag_id'];
        $item->confidence = $pick['confidence'];
        $item->reason = $pick['reason'] ?? null;

        return $item;
    }, $picks);

    return $schema;
}

/**
 * 绑定一个返回固定标签选择并记录用户输入的假结构化生成器。
 *
 * @return object{userMessage: ?string}
 */
function fakeTagGenerator(ConversationTagSelectionSchema $schema): object
{
    $capture = new class
    {
        public ?string $userMessage = null;
    };

    $generator = Mockery::mock(NeuronStructuredGenerator::class);
    $generator->shouldReceive('generate')
        ->once()
        ->andReturnUsing(function (AiModel $model, string $instructions, string $userMessage, string $class) use ($schema, $capture) {
            expect($class)->toBe(ConversationTagSelectionSchema::class);
            $capture->userMessage = $userMessage;

            return $schema;
        });
    app()->instance(NeuronStructuredGenerator::class, $generator);

    return $capture;
}

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
    $this->contact = Contact::factory()->create([]);
    $this->conversation = Conversation::factory()->create([
        'contact_id' => $this->contact->id,
        'summary' => '客户咨询退款流程',
        'summary_last_message_seq_no' => 12,
    ]);

    // 提供一个全局可用的 background_task 用途模型，让候选解析命中。
    makeAiModel(AiModelPurpose::BackgroundTask);

    $this->conversationGroup = TagGroup::factory()->conversation()->create([]);
    $this->contactGroup = TagGroup::factory()->contact()->create([]);
});

test('词表只下发会话维度标签，且按阈值映射回标签落库', function () {
    $refund = Tag::factory()->forGroup($this->conversationGroup)->create(['name' => '退款', 'description' => '客户要求退款时']);
    Tag::factory()->forGroup($this->contactGroup)->create(['name' => 'VIP']);

    $capture = fakeTagGenerator(tagSelectionSchema([
        ['tag_id' => (string) $refund->id, 'confidence' => 0.9, 'reason' => '客户说要退款'],
        ['tag_id' => (string) $refund->id, 'confidence' => 0.3, 'reason' => '低置信被过滤'],
        ['tag_id' => '01HALLUCINATEDTAGID', 'confidence' => 0.95, 'reason' => '幻觉'],
    ]));

    GenerateConversationTagsAction::make()->handle($this->conversation);

    // 词表只包含会话维度的「退款」，不含联系人维度的 VIP
    expect($capture->userMessage)->toContain('退款')
        ->and($capture->userMessage)->not->toContain('VIP');

    $tags = $this->conversation->tags()->get();
    expect($tags)->toHaveCount(1);
    expect($tags->first()->name)->toBe('退款');
    expect($tags->first()->pivot->source)->toBe('ai');
    expect((int) $tags->first()->pivot->based_on_seq_no)->toBe(12);
});

test('低置信度建议不落库', function () {
    $tag = Tag::factory()->forGroup($this->conversationGroup)->create(['name' => '技术支持']);

    fakeTagGenerator(tagSelectionSchema([
        ['tag_id' => (string) $tag->id, 'confidence' => 0.2, 'reason' => '不确定'],
    ]));

    GenerateConversationTagsAction::make()->handle($this->conversation);

    expect($this->conversation->tags()->count())->toBe(0);
});

test('同名标签按 tag_id 映射，允许不同分组使用相同名称', function () {
    $moodGroup = TagGroup::factory()->conversation()->create(['name' => '脾气']);
    $styleGroup = TagGroup::factory()->conversation()->create(['name' => '语气']);
    $moodTag = Tag::factory()->forGroup($moodGroup)->create(['name' => '温和']);
    $styleTag = Tag::factory()->forGroup($styleGroup)->create(['name' => '温和']);

    fakeTagGenerator(tagSelectionSchema([
        ['tag_id' => (string) $styleTag->id, 'confidence' => 0.9, 'reason' => '命中语气分组'],
    ]));

    GenerateConversationTagsAction::make()->handle($this->conversation);

    $tagIds = $this->conversation->tags()->pluck('tags.id')->all();
    expect($tagIds)->toContain($styleTag->id);
    expect($tagIds)->not->toContain($moodTag->id);
});
