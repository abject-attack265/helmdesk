<?php

use App\Actions\CustomAttribute\GenerateContactAttributeValuesAction;
use App\Enums\AiModelPurpose;
use App\Enums\AttributeValueSource;
use App\Models\AiModel;
use App\Models\AttributeDefinition;
use App\Models\Contact;
use App\Models\ContactActivityLog;
use App\Models\ContactAttributeValue;
use App\Models\Conversation;
use App\Services\Ai\NeuronStructuredGenerator;
use App\Services\Ai\Schemas\ContactAttributeExtractionSchema;
use App\Services\Ai\Schemas\ContactAttributePickSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

/**
 * 把简化的 pick 数组转成 LLM 结构化属性提取结果。
 *
 * @param  list<array{key: string, value?: string|null, values?: list<string>, confidence: float}>  $picks
 */
function attributeExtractionSchema(array $picks): ContactAttributeExtractionSchema
{
    $schema = new ContactAttributeExtractionSchema;
    $schema->picks = array_map(static function (array $pick): ContactAttributePickSchema {
        $item = new ContactAttributePickSchema;
        $item->key = $pick['key'];
        $item->value = $pick['value'] ?? null;
        $item->values = $pick['values'] ?? [];
        $item->confidence = $pick['confidence'];

        return $item;
    }, $picks);

    return $schema;
}

/**
 * 绑定一个返回固定属性提取并记录用户输入的假结构化生成器。
 *
 * @return object{userMessage: ?string}
 */
function fakeAttributeGenerator(ContactAttributeExtractionSchema $schema): object
{
    $capture = new class
    {
        public ?string $userMessage = null;
    };

    $generator = Mockery::mock(NeuronStructuredGenerator::class);
    $generator->shouldReceive('generate')
        ->once()
        ->andReturnUsing(function (AiModel $model, string $instructions, string $userMessage, string $class) use ($schema, $capture) {
            expect($class)->toBe(ContactAttributeExtractionSchema::class);
            $capture->userMessage = $userMessage;

            return $schema;
        });
    app()->instance(NeuronStructuredGenerator::class, $generator);

    return $capture;
}

/**
 * 绑定一个断言不会被调用的结构化生成器（候选属性为空时应直接短路）。
 */
function fakeNeverCalledAttributeGenerator(): void
{
    $generator = Mockery::mock(NeuronStructuredGenerator::class);
    $generator->shouldReceive('generate')->never();
    app()->instance(NeuronStructuredGenerator::class, $generator);
}

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
    $this->contact = Contact::factory()->create([]);
    $this->conversation = Conversation::factory()->forContact($this->contact)->create([
        'summary' => '客户来自一家 50 人规模的科技公司，正在评估专业版。',
    ]);

    // 提供一个全局可用的 background_task 用途模型，让候选解析命中。
    makeAiModel(AiModelPurpose::BackgroundTask);
});

test('提取的属性值按 ai 来源落库并带置信度', function () {
    $definition = AttributeDefinition::factory()->text()->create([
        'key' => 'company_size',
        'name' => '公司规模',
        'is_ai_writable' => true,
    ]);

    $capture = fakeAttributeGenerator(attributeExtractionSchema([
        ['key' => 'company_size', 'value' => '50 人左右', 'confidence' => 0.9],
    ]));

    GenerateContactAttributeValuesAction::make()->handle($this->conversation);

    expect($capture->userMessage)->toContain('company_size');

    $value = ContactAttributeValue::query()
        ->where('contact_id', $this->contact->id)
        ->where('definition_id', $definition->id)
        ->first();

    expect($value)->not->toBeNull()
        ->and($value->value())->toBe('50 人左右')
        ->and($value->source)->toBe(AttributeValueSource::Ai)
        ->and($value->confidence)->toBe(0.9)
        ->and($value->updated_by_user_id)->toBeNull();

    $log = ContactActivityLog::query()
        ->where('contact_id', $this->contact->id)
        ->where('action', 'custom_attributes_updated')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->payload['source'])->toBe('ai')
        ->and($log->actor_user_id)->toBeNull();
});

test('已有人工值的属性不进入候选清单也不被覆盖', function () {
    $definition = AttributeDefinition::factory()->text()->create([
        'key' => 'company_size',
        'is_ai_writable' => true,
    ]);
    ContactAttributeValue::factory()->create([
        'contact_id' => $this->contact->id,
        'definition_id' => $definition->id,
        'value_json' => ['value' => '人工填写的规模'],
        'source' => AttributeValueSource::Manual,
    ]);

    fakeNeverCalledAttributeGenerator();

    GenerateContactAttributeValuesAction::make()->handle($this->conversation);

    $value = ContactAttributeValue::query()
        ->where('definition_id', $definition->id)
        ->first();

    expect($value->value())->toBe('人工填写的规模')
        ->and($value->source)->toBe(AttributeValueSource::Manual);
});

test('AI 来源的旧值可以被刷新', function () {
    $definition = AttributeDefinition::factory()->text()->create([
        'key' => 'company_size',
        'is_ai_writable' => true,
    ]);
    ContactAttributeValue::factory()->create([
        'contact_id' => $this->contact->id,
        'definition_id' => $definition->id,
        'value_json' => ['value' => '10 人'],
        'source' => AttributeValueSource::Ai,
        'confidence' => 0.6,
    ]);

    fakeAttributeGenerator(attributeExtractionSchema([
        ['key' => 'company_size', 'value' => '50 人', 'confidence' => 0.95],
    ]));

    GenerateContactAttributeValuesAction::make()->handle($this->conversation);

    $value = ContactAttributeValue::query()->where('definition_id', $definition->id)->first();

    expect($value->value())->toBe('50 人')
        ->and($value->confidence)->toBe(0.95);
});

test('低置信度建议不落库', function () {
    AttributeDefinition::factory()->text()->create([
        'key' => 'company_size',
        'is_ai_writable' => true,
    ]);

    fakeAttributeGenerator(attributeExtractionSchema([
        ['key' => 'company_size', 'value' => '50 人', 'confidence' => 0.3],
    ]));

    GenerateContactAttributeValuesAction::make()->handle($this->conversation);

    expect(ContactAttributeValue::query()->where('contact_id', $this->contact->id)->count())->toBe(0);
});

test('不符合类型约束的提取值被丢弃', function () {
    AttributeDefinition::factory()->singleSelect([
        ['code' => 'pro', 'label' => '专业版'],
        ['code' => 'basic', 'label' => '基础版'],
    ])->create([
        'key' => 'plan_interest',
        'is_ai_writable' => true,
    ]);
    AttributeDefinition::factory()->date()->create([
        'key' => 'expected_start',
        'is_ai_writable' => true,
    ]);

    fakeAttributeGenerator(attributeExtractionSchema([
        ['key' => 'plan_interest', 'value' => 'enterprise', 'confidence' => 0.9],
        ['key' => 'expected_start', 'value' => '下个月', 'confidence' => 0.9],
    ]));

    GenerateContactAttributeValuesAction::make()->handle($this->conversation);

    expect(ContactAttributeValue::query()->where('contact_id', $this->contact->id)->count())->toBe(0);
});

test('多选属性通过 values 字段落库为 code 数组', function () {
    $definition = AttributeDefinition::factory()->multiSelect([
        ['code' => 'api', 'label' => 'API 集成'],
        ['code' => 'login', 'label' => '登录'],
        ['code' => 'audit', 'label' => '审计日志'],
    ])->create([
        'key' => 'interested_features',
        'is_ai_writable' => true,
    ]);

    fakeAttributeGenerator(attributeExtractionSchema([
        ['key' => 'interested_features', 'values' => ['api', 'login'], 'confidence' => 0.8],
    ]));

    GenerateContactAttributeValuesAction::make()->handle($this->conversation);

    $value = ContactAttributeValue::query()->where('definition_id', $definition->id)->first();

    expect($value)->not->toBeNull()
        ->and($value->value())->toBe(['api', 'login'])
        ->and($value->source)->toBe(AttributeValueSource::Ai);
});

test('未开启 AI 写入的属性不进入候选清单', function () {
    AttributeDefinition::factory()->text()->create([
        'key' => 'ai_writable_field',
        'is_ai_writable' => true,
    ]);
    AttributeDefinition::factory()->text()->create([
        'key' => 'manual_only_field',
        'is_ai_writable' => false,
    ]);

    $capture = fakeAttributeGenerator(attributeExtractionSchema([]));

    GenerateContactAttributeValuesAction::make()->handle($this->conversation);

    expect($capture->userMessage)->toContain('ai_writable_field')
        ->and($capture->userMessage)->not->toContain('manual_only_field');
});

test('没有 AI 可写属性时不调用模型', function () {
    AttributeDefinition::factory()->text()->create([
        'key' => 'manual_only_field',
        'is_ai_writable' => false,
    ]);

    fakeNeverCalledAttributeGenerator();

    GenerateContactAttributeValuesAction::make()->handle($this->conversation);

    expect(ContactAttributeValue::query()->where('contact_id', $this->contact->id)->count())->toBe(0);
});
