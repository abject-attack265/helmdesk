<?php

namespace App\Actions\KnowledgeBase\Qa;

use App\Models\KnowledgeBase;
use App\Models\KnowledgeQaEntry;
use App\Services\KnowledgeBase\KnowledgeNodeRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 删除问答知识库条目及其相似问法、答案。
 */
class DeleteKnowledgeQaEntryAction
{
    use AsAction;

    /**
     * 注入问答索引仓库。
     */
    public function __construct(
        private readonly KnowledgeNodeRepository $nodes,
    ) {}

    /**
     * 删除问答条目聚合。
     */
    public function handle(KnowledgeQaEntry $entry): void
    {
        $this->nodes->purgeAllForQaEntry($entry);

        DB::transaction(function () use ($entry): void {
            $entry->similarQuestions()->delete();
            $entry->answers()->delete();
            $entry->delete();
        });
    }

    /**
     * 处理删除问答请求。
     */
    public function asController(Request $request, string $knowledgeBase, string $entry): RedirectResponse
    {

        $kb = KnowledgeBase::query()

            ->findOrFail($knowledgeBase);

        $entryModel = KnowledgeQaEntry::query()
            ->where('knowledge_base_id', $kb->id)
            ->findOrFail($entry);
        $groupId = (string) $entryModel->group_id;

        $this->handle($entryModel);

        return redirect()->route('app.manage.knowledge-bases.index', [
            'kb' => $kb->id,
            'group' => $groupId,
        ]);
    }
}
