<?php

use App\Services\Ai\Schemas\ContactAttributeExtractionSchema;
use App\Services\Ai\Schemas\ContactAttributePickSchema;
use App\Services\Ai\Schemas\ContactHandoffBriefSchema;
use App\Services\Ai\Schemas\ConversationTagSelectionSchema;
use App\Services\Ai\Schemas\InboxReplyPolishSchema;
use NeuronAI\StructuredOutput\Validation\Validator;

test('接手简报没有下一步时允许空列表', function () {
    $schema = new ContactHandoffBriefSchema;
    $schema->brief = '访客咨询了发货时间，问题已经解决。';

    expect(Validator::validate($schema))->toBe([]);
});

test('会话标签选择 schema 无适用标签（空列表）时校验通过', function () {
    expect(Validator::validate(new ConversationTagSelectionSchema))->toBe([]);
});

test('联系人属性提取 schema 无可提取属性（空列表）时校验通过', function () {
    expect(Validator::validate(new ContactAttributeExtractionSchema))->toBe([]);
});

test('联系人属性建议 schema 的多选值为空时校验通过', function () {
    $pick = new ContactAttributePickSchema;
    $pick->key = 'industry';
    $pick->value = 'retail';
    $pick->confidence = 0.9;

    expect(Validator::validate($pick))->toBe([]);
});

test('回复助手 schema 仍要求至少一条候选（空列表不通过）', function () {
    // candidates 是 required(min:1) 的输出，空列表是真实失败，应继续被拒绝。
    expect(Validator::validate(new InboxReplyPolishSchema))->not->toBe([]);
});
