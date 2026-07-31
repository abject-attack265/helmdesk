<?php

use App\Actions\Contact\MergeContactsAction;
use App\Enums\AttributeValueSource;
use App\Models\AttributeDefinition;
use App\Models\Contact;
use App\Models\ContactActivityLog;
use App\Models\ContactAttributeValue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('合并填充缺失单选值来自来源', function () {
    [$app, $user] = createInstanceWithOwner();

    $target = Contact::factory()->create();
    $merged = Contact::factory()->create();
    $def = AttributeDefinition::factory()->text()->create(['key' => 'company']);

    ContactAttributeValue::factory()->create([
        'contact_id' => $merged->id,
        'definition_id' => $def->id,
        'value_json' => ['value' => 'Acme'],
    ]);

    MergeContactsAction::run($target->id, $merged->id, $user);

    $val = ContactAttributeValue::query()
        ->where('contact_id', $target->id)
        ->where('definition_id', $def->id)
        ->first();

    expect($val)->not->toBeNull()
        ->and($val->value())->toBe('Acme')
        ->and($val->source)->toBe(AttributeValueSource::Merge);
});

test('合并保留目标值当双方都有值', function () {
    [$app, $user] = createInstanceWithOwner();

    $target = Contact::factory()->create();
    $merged = Contact::factory()->create();
    $def = AttributeDefinition::factory()->text()->create(['key' => 'company']);

    ContactAttributeValue::factory()->create([
        'contact_id' => $target->id,
        'definition_id' => $def->id,
        'value_json' => ['value' => 'Target Corp'],
    ]);

    ContactAttributeValue::factory()->create([
        'contact_id' => $merged->id,
        'definition_id' => $def->id,
        'value_json' => ['value' => 'Merged Corp'],
    ]);

    MergeContactsAction::run($target->id, $merged->id, $user);

    $val = ContactAttributeValue::query()
        ->where('contact_id', $target->id)
        ->where('definition_id', $def->id)
        ->first();
    $log = ContactActivityLog::query()
        ->where('contact_id', $target->id)
        ->where('action', 'merged_into_current')
        ->first();

    expect($val->value())->toBe('Target Corp')
        ->and($val->source)->toBe(AttributeValueSource::Manual)
        ->and($log->payload)->not->toHaveKey('merged_custom_attributes');
});

test('合并保留布尔false在目标', function () {
    [$app, $user] = createInstanceWithOwner();

    $target = Contact::factory()->create();
    $merged = Contact::factory()->create();
    $def = AttributeDefinition::factory()->boolean()->create(['key' => 'is_vip']);

    ContactAttributeValue::factory()->create([
        'contact_id' => $target->id,
        'definition_id' => $def->id,
        'value_json' => ['value' => false],
    ]);

    ContactAttributeValue::factory()->create([
        'contact_id' => $merged->id,
        'definition_id' => $def->id,
        'value_json' => ['value' => true],
    ]);

    MergeContactsAction::run($target->id, $merged->id, $user);

    $val = ContactAttributeValue::query()
        ->where('contact_id', $target->id)
        ->where('definition_id', $def->id)
        ->first();

    expect($val->value())->toBeFalse();
});

test('合并生成并集用于multi_select', function () {
    [$app, $user] = createInstanceWithOwner();

    $target = Contact::factory()->create();
    $merged = Contact::factory()->create();
    $def = AttributeDefinition::factory()->multiSelect([
        ['code' => 'a', 'label' => 'A'],
        ['code' => 'b', 'label' => 'B'],
        ['code' => 'c', 'label' => 'C'],
    ])->create(['key' => 'interests']);

    ContactAttributeValue::factory()->create([
        'contact_id' => $target->id,
        'definition_id' => $def->id,
        'value_json' => ['value' => ['a', 'b']],
    ]);

    ContactAttributeValue::factory()->create([
        'contact_id' => $merged->id,
        'definition_id' => $def->id,
        'value_json' => ['value' => ['b', 'c']],
    ]);

    MergeContactsAction::run($target->id, $merged->id, $user);

    $val = ContactAttributeValue::query()
        ->where('contact_id', $target->id)
        ->where('definition_id', $def->id)
        ->first();

    expect($val->value())->toBe(['a', 'b', 'c']);
});

test('合并设置来源到合并', function () {
    [$app, $user] = createInstanceWithOwner();

    $target = Contact::factory()->create();
    $merged = Contact::factory()->create();
    $def = AttributeDefinition::factory()->text()->create(['key' => 'note']);

    ContactAttributeValue::factory()->create([
        'contact_id' => $merged->id,
        'definition_id' => $def->id,
        'value_json' => ['value' => 'from merged'],
    ]);

    MergeContactsAction::run($target->id, $merged->id, $user);

    $val = ContactAttributeValue::query()
        ->where('contact_id', $target->id)
        ->where('definition_id', $def->id)
        ->first();

    expect($val->source)->toBe(AttributeValueSource::Merge);
});

test('合并活动日志包含merged_custom_attributes', function () {
    [$app, $user] = createInstanceWithOwner();

    $target = Contact::factory()->create();
    $merged = Contact::factory()->create();
    $def = AttributeDefinition::factory()->text()->create(['key' => 'company']);

    ContactAttributeValue::factory()->create([
        'contact_id' => $merged->id,
        'definition_id' => $def->id,
        'value_json' => ['value' => 'Acme'],
    ]);

    MergeContactsAction::run($target->id, $merged->id, $user);

    $log = ContactActivityLog::query()
        ->where('contact_id', $target->id)
        ->where('action', 'merged_into_current')
        ->first();

    expect($log->payload)->toHaveKey('merged_custom_attributes')
        ->and($log->payload['merged_custom_attributes'][0]['key'])->toBe('company')
        ->and($log->payload['merged_custom_attributes'][0]['value'])->toBe('Acme');
});

test('合并处理已删除定义', function () {
    [$app, $user] = createInstanceWithOwner();

    $target = Contact::factory()->create();
    $merged = Contact::factory()->create();
    $def = AttributeDefinition::factory()->text()->deleted()->create(['key' => 'legacy']);

    ContactAttributeValue::factory()->create([
        'contact_id' => $merged->id,
        'definition_id' => $def->id,
        'value_json' => ['value' => 'old data'],
    ]);

    MergeContactsAction::run($target->id, $merged->id, $user);

    $val = ContactAttributeValue::query()
        ->where('contact_id', $target->id)
        ->where('definition_id', $def->id)
        ->first();

    expect($val)->not->toBeNull()
        ->and($val->value())->toBe('old data');
});

test('合并移除来源联系人自定义属性行之后复制值', function () {
    [$app, $user] = createInstanceWithOwner();

    $target = Contact::factory()->create();
    $merged = Contact::factory()->create();
    $def = AttributeDefinition::factory()->text()->create(['key' => 'company']);

    ContactAttributeValue::factory()->create([
        'contact_id' => $merged->id,
        'definition_id' => $def->id,
        'value_json' => ['value' => 'Acme'],
    ]);

    MergeContactsAction::run($target->id, $merged->id, $user);

    expect(ContactAttributeValue::query()
        ->where('contact_id', $merged->id)
        ->count())->toBe(0)
        ->and(ContactAttributeValue::query()
            ->where('contact_id', $target->id)
            ->where('definition_id', $def->id)
            ->value('value_json'))->toBe(['value' => 'Acme']);
});
