<?php

use App\Services\Search\CjkTokenizer;
use App\Services\Search\TntBooleanQueryCompiler;

test('中文查询拆成单字 token', function (): void {
    $compiler = new TntBooleanQueryCompiler(new CjkTokenizer);

    expect($compiler->tokens('你是谁'))->toBe(['你', '是', '谁']);
});

test('混合查询保留非中文 token', function (): void {
    $compiler = new TntBooleanQueryCompiler(new CjkTokenizer);

    expect($compiler->tokens('订单 ABC-123 你是谁'))->toBe(['订', '单', 'abc-123', '你', '是', '谁']);
});

test('compile 输出 TNTSearch Boolean AND 表达式', function (): void {
    $compiler = new TntBooleanQueryCompiler(new CjkTokenizer);

    expect($compiler->compile('订单退款'))->toBe('订 单 退 款')
        ->and($compiler->compile('Refund 订单'))->toBe('refund 订 单')
        ->and($compiler->compile('13800'))->toBe('13800 1380 138')
        ->and($compiler->compile('!!!'))->toBe('');
});
