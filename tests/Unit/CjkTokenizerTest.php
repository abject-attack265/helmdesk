<?php

use App\Services\Search\CjkTokenizer;

test('将文本分词为精确的词元列表', function (string $input, array $expected, array $stopwords = []) {
    expect((new CjkTokenizer)->tokenize($input, $stopwords))->toBe($expected);
})->with([
    'latin words' => ['hello world', ['hello', 'world']],
    'chinese unigrams and bigrams' => ['张三', ['张', '三', '张三']],
    'empty string' => ['', []],
    'lowercase normalization' => ['Hello WORLD', ['hello', 'world']],
    'stopwords' => ['the quick brown fox', ['quick', 'brown', 'fox'], ['the']],
]);

test('将文本分词为可搜索片段', function (string $input, array $contains, array $missing = []) {
    $tokens = (new CjkTokenizer)->tokenize($input);

    expect($tokens)->toContain(...$contains);

    if ($missing !== []) {
        expect($tokens)->not->toContain(...$missing);
    }
})->with([
    'mixed chinese and latin' => [
        '张三的email是test@example.com',
        ['张', '三', '张三', '三的', 'email', 'test', 'example', 'com'],
    ],
    'email parts' => [
        'user@example.com',
        ['user', 'example', 'com'],
        ['user@example'],
    ],
    'numeric prefixes' => [
        '13800138000',
        ['13800138000', '1380013800', '138001380', '138'],
    ],
    'e164 chinese mobile prefixes' => [
        '+8613800138000',
        ['8613800138000', '13800138000', '138'],
    ],
]);
