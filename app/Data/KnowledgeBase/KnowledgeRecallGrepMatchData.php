<?php

namespace App\Data\KnowledgeBase;

use Spatie\LaravelData\Data;

/**
 * 知识库测试页面中按关键词找到的一条内容。
 */
class KnowledgeRecallGrepMatchData extends Data
{
    /**
     * 封装关键词命中的来源、位置和上下文。
     */
    public function __construct(
        public string $origin_type,
        public ?string $origin_title,
        public string $field,
        public string $field_label,
        public int $line,
        public int $column,
        public string $context_before,
        public string $match,
        public string $context_after,
        public ?string $heading_path,
    ) {}

    /**
     * 补充内容来源标题和展示标签。
     *
     * @param  array<string, string>  $qaQuestions  qa_entry_id => 主问题
     */
    public static function fromMatch(KnowledgeSearchGrepMatchData $match, array $qaQuestions): self
    {
        if ($match->qa_entry_id !== null) {
            $originType = 'qa';
            $originTitle = $qaQuestions[$match->qa_entry_id] ?? null;
        } else {
            $originType = 'document';
            $originTitle = $match->document_title;
        }

        return new self(
            origin_type: $originType,
            origin_title: $originTitle,
            field: $match->field,
            field_label: self::resolveFieldLabel($match->field),
            line: $match->line,
            column: $match->column,
            context_before: $match->context_before,
            match: $match->match,
            context_after: $match->context_after,
            heading_path: $match->heading_path,
        );
    }

    /**
     * 返回内容字段的展示标签。
     */
    private static function resolveFieldLabel(string $field): string
    {
        return match ($field) {
            'document.parsed_content' => __('knowledge_recall.fields.document.parsed_content'),
            'qa_entry.question' => __('knowledge_recall.fields.qa_entry.question'),
            'qa_entry.similar_question' => __('knowledge_recall.fields.qa_entry.similar_question'),
            'qa_entry.answer' => __('knowledge_recall.fields.qa_entry.answer'),
        };
    }
}
