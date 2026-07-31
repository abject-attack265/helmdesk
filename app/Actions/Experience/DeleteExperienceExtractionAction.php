<?php

namespace App\Actions\Experience;

use App\Enums\ExperienceExtractionStatus;
use App\Exceptions\BusinessException;
use App\Models\ExperienceExtraction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 删除一个提炼任务及其候选经验与会话登记。
 *
 * 进行中的任务不可删除（后台 Job 仍会回写运行记录）；已采纳候选生成的知识库 QA 条目
 * 独立存在，不随任务删除。
 */
class DeleteExperienceExtractionAction
{
    use AsAction;

    /**
     * 删除任务、其候选与会话关联。
     */
    public function handle(ExperienceExtraction $extraction): void
    {
        if ($extraction->status === ExperienceExtractionStatus::Running) {
            throw new BusinessException(__('experience.errors.cannot_delete_running'));
        }

        DB::transaction(function () use ($extraction): void {
            $extraction->candidates()->delete();
            $extraction->conversations()->detach();
            $extraction->delete();
        });
    }

    /**
     * 解析当前应用下的任务并执行删除，跳回任务列表页。
     */
    public function asController(Request $request, string $extraction): RedirectResponse
    {
        $model = ExperienceExtraction::query()

            ->findOrFail($extraction);

        $this->handle($model);

        return redirect()->route('app.manage.experience-extraction.index', [
            'knowledgeBase' => (string) $model->knowledge_base_id,
        ]);
    }
}
