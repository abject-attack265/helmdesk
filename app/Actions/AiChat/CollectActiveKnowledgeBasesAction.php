<?php

namespace App\Actions\AiChat;

use App\Models\KnowledgeBase;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 收集可供 AI 助手检索的知识库白名单。
 */
class CollectActiveKnowledgeBasesAction
{
    use AsAction;

    /**
     * 返回知识库工具需要的标识、名称和描述。
     *
     * @return list<array{id: string, name: string, description: string}>
     */
    public function handle(): array
    {
        $knowledgeBases = KnowledgeBase::query()
            ->orderBy('created_at')
            ->get(['id', 'name', 'description']);

        $payload = [];
        foreach ($knowledgeBases as $knowledgeBase) {
            $payload[] = [
                'id' => $knowledgeBase->id,
                'name' => $knowledgeBase->name,
                'description' => $knowledgeBase->description ?? '',
            ];
        }

        return $payload;
    }
}
