<?php

namespace App\Services\Search;

/**
 * 把用户输入编译为 TNTSearch Boolean 查询表达式。
 */
class TntBooleanQueryCompiler
{
    private const string CJK_RANGES = '\x{2E80}-\x{9FFF}\x{F900}-\x{FAFF}\x{FE30}-\x{FE4F}\x{20000}-\x{2FA1F}';

    private const array BOOLEAN_RESERVED_WORDS = ['or'];

    /**
     * 注入通用分词器以复用拉丁文本和数字处理规则。
     */
    public function __construct(
        private readonly CjkTokenizer $tokenizer,
    ) {}

    /**
     * 编译为由空格连接的 AND 查询表达式。
     */
    public function compile(string $query): string
    {
        return implode(' ', $this->tokens($query));
    }

    /**
     * 返回 Boolean 查询使用的最小 token 列表。
     *
     * @return list<string>
     */
    public function tokens(string $query): array
    {
        $query = mb_strtolower(trim($query));
        if ($query === '') {
            return [];
        }

        $tokens = [];
        $segments = preg_split(
            '/(['.self::CJK_RANGES.']+)/u',
            $query,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        );

        foreach ($segments as $segment) {
            if (preg_match('/^['.self::CJK_RANGES.']+$/u', $segment) === 1) {
                $tokens = [...$tokens, ...mb_str_split($segment)];

                continue;
            }

            $tokens = [...$tokens, ...$this->tokenizer->tokenize($segment)];
        }

        return $this->uniqueTokens($tokens);
    }

    /**
     * 去掉空 token 和 Boolean 保留词，并保持原始顺序。
     *
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private function uniqueTokens(array $tokens): array
    {
        $seen = [];
        $unique = [];

        foreach ($tokens as $token) {
            $token = trim($token);

            if ($token === '' || in_array($token, self::BOOLEAN_RESERVED_WORDS, true) || isset($seen[$token])) {
                continue;
            }

            $seen[$token] = true;
            $unique[] = $token;
        }

        return $unique;
    }
}
