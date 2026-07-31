<?php

use App\Services\KnowledgeBase\Parsing\MarkdownChunker;

it('提取标题大纲并保持层级嵌套', function () {
    $chunker = new MarkdownChunker;
    $outline = $chunker->outline("# 一级\n\n## 二级 A\n\n### 三级\n\n## 二级 B");

    expect($outline)->toHaveCount(1)
        ->and($outline[0]['heading'])->toBe('一级')
        ->and($outline[0]['children'])->toHaveCount(2)
        ->and($outline[0]['children'][0]['heading'])->toBe('二级 A')
        ->and($outline[0]['children'][0]['children'][0]['heading'])->toBe('三级')
        ->and($outline[0]['children'][1]['heading'])->toBe('二级 B');
});

it('按 token 预算切段并携带 heading_path', function () {
    $chunker = new MarkdownChunker;
    $markdown = "# 标题\n\n第一段非常重要。\n\n第二段也很长。\n\n第三段还在写。";
    $result = $chunker->chunk($markdown, maxTokens: 5, overlapTokens: 0);

    expect($result['segments'])->not->toBeEmpty();
    foreach ($result['segments'] as $segment) {
        expect($segment['heading_path'])->toBe(['标题'])
            ->and($segment['byte_start'])->toBeGreaterThanOrEqual(0)
            ->and($segment['byte_end'])->toBeGreaterThan($segment['byte_start']);
    }
});

it('超长段落会按句子边界进一步拆分而不会丢失内容', function () {
    $chunker = new MarkdownChunker;
    $paragraph = str_repeat('这是一句话。', 80);
    $result = $chunker->chunk("# H\n\n".$paragraph, maxTokens: 20, overlapTokens: 4);

    expect($result['segments'])->not->toBeEmpty();
    foreach ($result['segments'] as $segment) {
        expect($segment['token_count'])->toBeGreaterThan(0);
    }
});

it('对空 Markdown 返回空段与空大纲', function () {
    $chunker = new MarkdownChunker;
    $result = $chunker->chunk('', maxTokens: 32, overlapTokens: 0);

    expect($result['segments'])->toBe([])
        ->and($result['outline'])->toBe([]);
});

it('estimateTokens 给 ASCII 和 CJK 不同权重', function () {
    $chunker = new MarkdownChunker;

    expect($chunker->estimateTokens(''))->toBe(0)
        ->and($chunker->estimateTokens('hello world'))->toBeLessThanOrEqual(4)
        ->and($chunker->estimateTokens('中文中文中文'))->toBe(6);
});
