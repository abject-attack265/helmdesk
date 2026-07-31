<?php

namespace App\Data\AiChat;

use Spatie\LaravelData\Data;

/**
 * 注入 AI 助手系统提示词的历史线程目录。
 */
class AiAssistantHistoryCatalogData extends Data
{
    /**
     * 保存历史线程总数和最近线程目录。
     *
     * @param  list<AiAssistantHistoryThreadData>  $threads
     */
    public function __construct(
        public int $total_threads,
        public array $threads,
    ) {}
}
