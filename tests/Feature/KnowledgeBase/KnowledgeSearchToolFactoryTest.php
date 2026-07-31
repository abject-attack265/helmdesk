<?php

use App\Enums\KnowledgeDocumentParseStatus;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use App\Services\KnowledgeBase\KnowledgeSearchToolFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NeuronAI\Tools\Tool;

uses(RefreshDatabase::class);

/**
 * 调用工具并返回结果字符串。
 *
 * @param  array<string, mixed>  $inputs
 */
function runKnowledgeSearchTool(Tool $tool, array $inputs): string
{
    $tool->setInputs($inputs);
    $tool->execute();

    return $tool->getResult();
}

test('knowledge_search 工具 grep 模式命中文档正文', function () {
    createSystemSettings();
    $kb = KnowledgeBase::factory()->create([]);
    KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $kb->id,
        'parse_status' => KnowledgeDocumentParseStatus::Succeeded,
        'parsed_content' => "# 退款政策\n\n本店支持 7 天无理由退款，请在订单详情页提交申请。",
        'parsed_content_format' => 'markdown',
    ]);

    $tool = app(KnowledgeSearchToolFactory::class)->buildKnowledgeSearchTool([$kb->id]);
    $result = json_decode(runKnowledgeSearchTool($tool, ['mode' => 'grep', 'query' => ['无理由退款']]), true);

    expect($result['mode'])->toBe('grep')
        ->and($result['grep_matches'])->not->toBeEmpty();
});

test('knowledge_search 工具返回的中文上下文保持有效 UTF-8', function () {
    createSystemSettings();
    $kb = KnowledgeBase::factory()->create([]);
    KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $kb->id,
        'parse_status' => KnowledgeDocumentParseStatus::Succeeded,
        'parsed_content' => '武汉芝麻小事'.str_repeat('网络科技有限公司提供智能客服服务。', 10),
        'parsed_content_format' => 'markdown',
    ]);

    $tool = app(KnowledgeSearchToolFactory::class)->buildKnowledgeSearchTool([$kb->id]);
    $result = json_decode(
        runKnowledgeSearchTool($tool, ['mode' => 'grep', 'query' => ['芝麻小事']]),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $match = $result['grep_matches'][0];

    expect(mb_check_encoding($match['context_before'], 'UTF-8'))->toBeTrue()
        ->and(mb_check_encoding($match['context_after'], 'UTF-8'))->toBeTrue()
        ->and(mb_strlen($match['context_after'], 'UTF-8'))->toBe(80)
        ->and($match['byte_start'])->toBe(strlen('武汉'))
        ->and($match['byte_end'])->toBe(strlen('武汉芝麻小事'));
});

test('knowledge_search 工具非法 mode 返回 mode_required', function () {
    createSystemSettings();
    $kb = KnowledgeBase::factory()->create([]);

    $tool = app(KnowledgeSearchToolFactory::class)->buildKnowledgeSearchTool([$kb->id]);
    $result = json_decode(runKnowledgeSearchTool($tool, ['mode' => 'bogus', 'query' => ['x']]), true);

    expect($result)->toBe(['error' => 'mode_required']);
});

test('knowledge_search 工具未知应用返回 inaccessible', function () {
    createSystemSettings();
    $tool = app(KnowledgeSearchToolFactory::class)->buildKnowledgeSearchTool(['00000000000000000000000000']);
    $result = json_decode(runKnowledgeSearchTool($tool, ['mode' => 'grep', 'query' => ['x']]), true);

    expect($result)->toBe(['error' => 'knowledge_base_inaccessible']);
});
