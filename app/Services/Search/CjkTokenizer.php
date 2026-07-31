<?php

namespace App\Services\Search;

use TeamTNT\TNTSearch\Tokenizer\AbstractTokenizer;
use TeamTNT\TNTSearch\Tokenizer\TokenizerInterface;

/**
 * TNTSearch 使用的中日韩分词器。
 */
class CjkTokenizer extends AbstractTokenizer implements TokenizerInterface
{
    protected static $pattern = '/[^\p{L}\p{N}\p{Pc}\p{Pd}]+/u';

    private const string CJK_RANGES = '\x{2E80}-\x{9FFF}\x{F900}-\x{FAFF}\x{FE30}-\x{FE4F}\x{20000}-\x{2FA1F}';

    private const int MIN_NUMERIC_PREFIX_LENGTH = 3;

    /**
     * 把文本拆成可索引 token：中文逐字 unigram + 相邻 bigram，拉丁/数字另行处理。
     *
     * @param  list<string>  $stopwords
     * @return list<string>
     */
    public function tokenize($text, $stopwords = []): array
    {
        $text = mb_strtolower((string) $text);

        if ($text === '') {
            return [];
        }

        $tokens = [];
        $segments = preg_split('/(['.self::CJK_RANGES.']+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        foreach ($segments as $segment) {
            if (preg_match('/^['.self::CJK_RANGES.']+$/u', $segment)) {
                $chars = mb_str_split($segment);
                foreach ($chars as $char) {
                    $tokens[] = $char;
                }

                for ($index = 0; $index < count($chars) - 1; $index++) {
                    $tokens[] = $chars[$index].$chars[$index + 1];
                }
            } else {
                $words = preg_split(static::$pattern, $segment, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($words as $word) {
                    $tokens[] = $word;

                    if (preg_match('/^\d+$/', $word) === 1) {
                        $tokens = [...$tokens, ...$this->numericPrefixes($word)];

                        if (str_starts_with($word, '86') && strlen($word) > 11) {
                            $localNumber = substr($word, 2);
                            $tokens[] = $localNumber;
                            $tokens = [...$tokens, ...$this->numericPrefixes($localNumber)];
                        }
                    }
                }
            }
        }

        return array_values(array_unique(array_filter(
            $tokens,
            fn (string $token) => ! in_array($token, $stopwords, true)
        )));
    }

    /**
     * 生成数字串的前缀 token。
     *
     * @return array<int, string>
     */
    private function numericPrefixes(string $word): array
    {
        $prefixes = [];

        for ($length = strlen($word) - 1; $length >= self::MIN_NUMERIC_PREFIX_LENGTH; $length--) {
            $prefixes[] = substr($word, 0, $length);
        }

        return $prefixes;
    }
}
