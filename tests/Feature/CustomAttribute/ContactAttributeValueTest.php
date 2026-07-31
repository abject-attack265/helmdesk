<?php

use App\Actions\Contact\ShowContactDetailAction;
use App\Actions\Contact\ShowContactListAction;
use App\Actions\CustomAttribute\UpdateContactAttributeValuesAction;
use App\Enums\ContactListType;
use App\Models\AttributeDefinition;
use App\Models\Contact;
use App\Models\ContactAttributeValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// === Update Values ===

test('可以更新文本属性值', function () {
    [$app, $user] = createInstanceWithOwner();
    $contact = Contact::factory()->create();
    $def = AttributeDefinition::factory()->text()->create(['key' => 'company']);

    UpdateContactAttributeValuesAction::run($contact->id, ['company' => 'Acme'], $user->id);

    $value = ContactAttributeValue::query()
        ->where('contact_id', $contact->id)
        ->where('definition_id', $def->id)
        ->first();

    expect($value)->not->toBeNull()
        ->and($value->value())->toBe('Acme')
        ->and($value->source->value)->toBe('manual');
});

test('可以更新数字属性值', function () {
    [$app, $user] = createInstanceWithOwner();
    $contact = Contact::factory()->create();
    AttributeDefinition::factory()->number()->create(['key' => 'age']);

    UpdateContactAttributeValuesAction::run($contact->id, ['age' => 25], $user->id);

    $value = ContactAttributeValue::query()
        ->where('contact_id', $contact->id)
        ->first();

    expect($value->value())->toBe(25);
});

test('可以更新布尔属性值', function () {
    [$app, $user] = createInstanceWithOwner();
    $contact = Contact::factory()->create();
    AttributeDefinition::factory()->boolean()->create(['key' => 'is_vip']);

    UpdateContactAttributeValuesAction::run($contact->id, ['is_vip' => false], $user->id);

    $value = ContactAttributeValue::query()
        ->where('contact_id', $contact->id)
        ->first();

    expect($value)->not->toBeNull()
        ->and($value->value())->toBeFalse();
});

test('清除值删除行', function () {
    [$app, $user] = createInstanceWithOwner();
    $contact = Contact::factory()->create();
    $def = AttributeDefinition::factory()->text()->create(['key' => 'company']);

    ContactAttributeValue::factory()->create([
        'contact_id' => $contact->id,
        'definition_id' => $def->id,
        'value_json' => ['value' => 'Old'],
    ]);

    UpdateContactAttributeValuesAction::run($contact->id, ['company' => null], $user->id);

    expect(ContactAttributeValue::query()->where('contact_id', $contact->id)->count())->toBe(0);
});

test('布尔false会被保留不已删除', function () {
    [$app, $user] = createInstanceWithOwner();
    $contact = Contact::factory()->create();
    AttributeDefinition::factory()->boolean()->create(['key' => 'active']);

    UpdateContactAttributeValuesAction::run($contact->id, ['active' => false], $user->id);

    $value = ContactAttributeValue::query()->where('contact_id', $contact->id)->first();
    expect($value)->not->toBeNull()
        ->and($value->value())->toBeFalse();
});

test('不能写入到已删除定义', function () {
    [$app, $user] = createInstanceWithOwner();
    $contact = Contact::factory()->create();
    AttributeDefinition::factory()->text()->deleted()->create(['key' => 'old_field']);

    UpdateContactAttributeValuesAction::run($contact->id, ['old_field' => 'test'], $user->id);
})->throws(ValidationException::class);

test('拒绝无效选项代码用于单选选择', function () {
    [$app, $user] = createInstanceWithOwner();
    $contact = Contact::factory()->create();
    AttributeDefinition::factory()->singleSelect([
        ['code' => 'vip', 'label' => 'VIP'],
    ])->create(['key' => 'level']);

    UpdateContactAttributeValuesAction::run($contact->id, ['level' => 'invalid'], $user->id);
})->throws(ValidationException::class);

test('多选选择去重值', function () {
    [$app, $user] = createInstanceWithOwner();
    $contact = Contact::factory()->create();
    AttributeDefinition::factory()->multiSelect([
        ['code' => 'a', 'label' => 'A'],
        ['code' => 'b', 'label' => 'B'],
    ])->create(['key' => 'tags']);

    UpdateContactAttributeValuesAction::run($contact->id, ['tags' => ['a', 'b', 'a']], $user->id);

    $value = ContactAttributeValue::query()->where('contact_id', $contact->id)->first();
    expect($value->value())->toBe(['a', 'b']);
});

// === Contact Detail ===

test('联系人详情包含活跃定义即使且没有值', function () {
    [$app, $user] = createInstanceWithOwner();
    $contact = Contact::factory()->create();
    AttributeDefinition::factory()->text()->create(['key' => 'company']);
    AttributeDefinition::factory()->number()->create(['key' => 'revenue']);

    $detail = ShowContactDetailAction::run($contact->id);

    expect($detail->custom_attributes)->toHaveCount(2);
    expect($detail->custom_attributes[0]->key)->toBeIn(['company', 'revenue']);
    expect($detail->custom_attributes[0]->value)->toBeNull();
});

test('已删除定义并带值显示作为只读在详情', function () {
    [$app, $user] = createInstanceWithOwner();
    $contact = Contact::factory()->create();
    $def = AttributeDefinition::factory()->text()->deleted()->create(['key' => 'old']);

    ContactAttributeValue::factory()->create([
        'contact_id' => $contact->id,
        'definition_id' => $def->id,
        'value_json' => ['value' => 'legacy'],
    ]);

    $detail = ShowContactDetailAction::run($contact->id);

    $deletedField = collect($detail->custom_attributes)->firstWhere('key', 'old');
    expect($deletedField)->not->toBeNull()
        ->and($deletedField->value)->toBe('legacy')
        ->and($deletedField->deleted_at)->not->toBeNull()
        ->and($deletedField->is_editable)->toBeFalse();
});

// === Contact List Filters ===

test('联系人列表可以筛选按单选选择自定义属性', function () {
    [$app] = createInstanceWithOwner();

    $vipContact = Contact::factory()->create(['name' => 'VIP']);
    $normalContact = Contact::factory()->create(['name' => 'Normal']);
    $definition = AttributeDefinition::factory()->singleSelect([
        ['code' => 'vip', 'label' => 'VIP'],
        ['code' => 'normal', 'label' => 'Normal'],
    ])->create([
        'key' => 'level',
        'is_filterable' => true,
    ]);

    ContactAttributeValue::factory()->create([
        'contact_id' => $vipContact->id,
        'definition_id' => $definition->id,
        'value_json' => ['value' => 'vip'],
    ]);

    ContactAttributeValue::factory()->create([
        'contact_id' => $normalContact->id,
        'definition_id' => $definition->id,
        'value_json' => ['value' => 'normal'],
    ]);

    $result = ShowContactListAction::run(ContactListType::All, null, 1, 15, [
        'level' => 'vip',
    ]);

    expect($result->contact_list)->toHaveCount(1)
        ->and($result->contact_list[0]->id)->toBe($vipContact->id)
        ->and($result->attribute_filters)->toBe(['level' => 'vip']);
});

test('联系人列表可以筛选按数字和日期自定义属性', function () {
    [$app] = createInstanceWithOwner();

    $matchedContact = Contact::factory()->create(['name' => 'Matched']);
    $oldContact = Contact::factory()->create(['name' => 'Old']);

    $scoreDefinition = AttributeDefinition::factory()->number()->create([
        'key' => 'score',
        'is_filterable' => true,
    ]);
    $signupDateDefinition = AttributeDefinition::factory()->date()->create([
        'key' => 'signup_date',
        'is_filterable' => true,
    ]);

    ContactAttributeValue::factory()->create([
        'contact_id' => $matchedContact->id,
        'definition_id' => $scoreDefinition->id,
        'value_json' => ['value' => 80],
    ]);
    ContactAttributeValue::factory()->create([
        'contact_id' => $matchedContact->id,
        'definition_id' => $signupDateDefinition->id,
        'value_json' => ['value' => '2026-04-10'],
    ]);

    ContactAttributeValue::factory()->create([
        'contact_id' => $oldContact->id,
        'definition_id' => $scoreDefinition->id,
        'value_json' => ['value' => 40],
    ]);
    ContactAttributeValue::factory()->create([
        'contact_id' => $oldContact->id,
        'definition_id' => $signupDateDefinition->id,
        'value_json' => ['value' => '2026-03-01'],
    ]);

    $result = ShowContactListAction::run(ContactListType::All, null, 1, 15, [
        'score' => ['min' => 60],
        'signup_date' => ['from' => '2026-04-01'],
    ]);

    expect($result->contact_list)->toHaveCount(1)
        ->and($result->contact_list[0]->id)->toBe($matchedContact->id)
        ->and($result->attribute_filters)->toBe([
            'score' => ['min' => 60],
            'signup_date' => ['from' => '2026-04-01'],
        ]);
});

test('联系人列表拒绝未知自定义属性筛选', function () {
    [$app] = createInstanceWithOwner();

    ShowContactListAction::run(ContactListType::All, null, 1, 15, [
        'unknown_attribute' => 'value',
    ]);
})->throws(ValidationException::class);

test('联系人列表拒绝无效自定义属性筛选值', function () {
    [$app] = createInstanceWithOwner();

    AttributeDefinition::factory()->singleSelect([
        ['code' => 'vip', 'label' => 'VIP'],
    ])->create([
        'key' => 'level',
        'is_filterable' => true,
    ]);

    ShowContactListAction::run(ContactListType::All, null, 1, 15, [
        'level' => 'unknown',
    ]);
})->throws(ValidationException::class);
