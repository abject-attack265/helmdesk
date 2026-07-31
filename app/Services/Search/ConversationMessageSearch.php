<?php

namespace App\Services\Search;

use App\Models\ConversationMessage;
use App\Models\User;
use Laravel\Scout\Builder;
use TeamTNT\TNTSearch\TNTSearch;

/**
 * 会话消息内容搜索门面。
 * 索引与查询两侧共用 CJK 分词规则，TNTSearch Boolean 查询负责候选召回；
 * 应用层仅保留正文实际匹配的消息，并取出命中文本供前端高亮。
 */
class ConversationMessageSearch
{
    /**
     * 注入查询编译器与可见文本解析器。
     */
    public function __construct(
        private readonly TntBooleanQueryCompiler $queryCompiler,
        private readonly ConversationMessageVisibleTextResolver $visibleTextResolver,
    ) {}

    /**
     * 在应用（或限定会话集合）内搜索当前客服可见的消息，按时间倒序返回消息与命中文本。
     * TNTSearch 返回完整候选集合，回表后按 created_at 和 id 全局倒序排列；
     * 候选仅在消息正文未实际匹配时被过滤。
     *
     * @param  list<string>|null  $conversationIds  限定会话范围；null 表示全应用
     * @return list<array{message: ConversationMessage, matched_content: string}>
     */
    public function searchVisibleMessages(
        User $viewer,
        string $search,
        ?array $conversationIds = null,
        int $limit = 50,
    ): array {
        if ($this->queryCompiler->tokens($search) === []) {
            return [];
        }

        $query = $this->query($search);
        if ($conversationIds !== null) {
            $query->constrain(ConversationMessage::query()->whereIn('conversation_id', $conversationIds));
        }

        $matchedIds = $query->get()->modelKeys();
        if ($matchedIds === []) {
            return [];
        }

        $messages = ConversationMessage::query()
            ->with(['senderUser', 'conversation.contact'])
            ->whereHas('conversation', fn ($conversationQuery) => $conversationQuery
                ->whereNotNull('contact_id')
                ->whereNotNull('channel_id'))
            ->whereIn('id', $matchedIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $results = [];
        foreach ($messages as $message) {
            $matchedContent = $this->matchingText(
                $search,
                $this->visibleTextResolver->texts($message, $viewer),
            );

            if ($matchedContent === null) {
                continue;
            }

            $results[] = ['message' => $message, 'matched_content' => $matchedContent];

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * 返回消息内容搜索的 Scout 查询构造器。
     * 查询词经与索引侧相同的分词后编译为 Boolean AND 表达式，应用层再校验正文。
     *
     * @return Builder<ConversationMessage>
     */
    public function query(string $search): Builder
    {
        return ConversationMessage::search(
            $this->queryCompiler->compile($search),
            $this->booleanSearchCallback(),
        );
    }

    /**
     * 返回第一段命中的文本。
     *
     * @param  list<string>  $texts
     */
    public function matchingText(string $search, array $texts): ?string
    {
        $queryTokens = $this->queryCompiler->tokens($search);
        if ($queryTokens === []) {
            return null;
        }

        foreach ($texts as $text) {
            if ($this->textMatchesTokens($text, $queryTokens)) {
                return $text;
            }
        }

        return null;
    }

    /**
     * 判断文本集合是否匹配搜索词。
     *
     * @param  list<string>  $texts
     */
    public function matches(string $search, array $texts): bool
    {
        $queryTokens = $this->queryCompiler->tokens($search);
        if ($queryTokens === [] || $texts === []) {
            return false;
        }

        foreach ($texts as $text) {
            if ($this->textMatchesTokens($text, $queryTokens)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 判断单段文本是否包含全部查询 token。
     *
     * @param  list<string>  $queryTokens
     */
    private function textMatchesTokens(string $text, array $queryTokens): bool
    {
        $textTokens = $this->queryCompiler->tokens($text);
        $tokenSet = array_fill_keys($textTokens, true);

        foreach ($queryTokens as $token) {
            if (! isset($tokenSet[$token])) {
                return false;
            }
        }

        return true;
    }

    /**
     * 返回 TNTSearch Boolean 查询回调。
     */
    private function booleanSearchCallback(): callable
    {
        return static function (TNTSearch $tnt, string $query): array {
            $result = $tnt->searchBoolean($query, max(1, (int) $tnt->totalDocumentsInCollection()));
            $result['docScores'] = array_fill_keys($result['ids'], 0.0);

            return $result;
        };
    }
}
