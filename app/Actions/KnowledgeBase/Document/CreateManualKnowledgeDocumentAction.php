<?php

namespace App\Actions\KnowledgeBase\Document;

use App\Actions\KnowledgeBase\Indexing\DispatchKnowledgeDocumentPipelineAction;
use App\Data\KnowledgeBase\FormCreateManualKnowledgeDocumentData;
use App\Enums\KnowledgeBaseCategory;
use App\Enums\KnowledgeDocumentParseStatus;
use App\Enums\KnowledgeDocumentSourceType;
use App\Enums\KnowledgeDocumentStatus;
use App\Exceptions\BusinessException;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 在知识库中创建正文存于 content 字段的手动 Markdown 文档。
 */
class CreateManualKnowledgeDocumentAction
{
    use AsAction;

    /**
     * 注入文档流水线编排器。
     */
    public function __construct(
        private readonly DispatchKnowledgeDocumentPipelineAction $pipeline,
    ) {}

    /**
     * 保存手动文档并触发解析与索引流水线。
     */
    public function handle(KnowledgeBase $knowledgeBase, FormCreateManualKnowledgeDocumentData $data, ?string $uploaderUserId = null): KnowledgeDocument
    {
        $this->assertDocumentKnowledgeBase($knowledgeBase);

        $group = $this->resolveTargetGroup($knowledgeBase, filled($data->group_id) ? $data->group_id : null);

        $title = trim($data->title);
        $content = $data->content;
        $byteSize = strlen($content);
        $checksum = hash('sha256', $content);

        /** @var KnowledgeDocument $document */
        $document = KnowledgeDocument::query()->create([
            'knowledge_base_id' => $knowledgeBase->id,
            'group_id' => $group->id,
            'uploaded_by_user_id' => $uploaderUserId,
            'original_filename' => $title,
            'mime_type' => 'text/markdown',
            'byte_size' => $byteSize,
            'extension' => 'md',
            'checksum_sha256' => $checksum,
            'source_type' => KnowledgeDocumentSourceType::Manual,
            'status' => KnowledgeDocumentStatus::Pending,
            'error_message' => null,
            'content' => $content,
            'parse_status' => KnowledgeDocumentParseStatus::Pending,
        ]);

        $this->pipeline->handle($document, forceReparse: true);

        return $document;
    }

    /**
     * 处理「手动添加」按钮提交并跳回当前知识库 / 分组视图。
     */
    public function asController(Request $request, string $knowledgeBase): RedirectResponse
    {

        $kb = KnowledgeBase::query()

            ->findOrFail($knowledgeBase);

        $data = FormCreateManualKnowledgeDocumentData::from($request);
        $document = $this->handle($kb, $data, (string) $request->user()?->id);

        $query = ['kb' => $kb->id];
        $groupId = (string) $document->group_id;
        if ($groupId !== '') {
            $query['group'] = $groupId;
        }

        return redirect()->route('app.manage.knowledge-bases.index', [
            ...$query,
        ]);
    }

    /**
     * 普通文档不能写入问答知识库；向问答知识库手动添加文档时拒绝提交。
     */
    private function assertDocumentKnowledgeBase(KnowledgeBase $knowledgeBase): void
    {
        if ($knowledgeBase->category !== KnowledgeBaseCategory::Qa) {
            return;
        }

        throw new BusinessException(__('knowledge_base.documents.errors.not_document_knowledge_base'));
    }

    /**
     * 校验目标分组属于当前知识库；未传分组时回退到默认分组。
     */
    private function resolveTargetGroup(KnowledgeBase $knowledgeBase, ?string $groupId): KnowledgeGroup
    {
        if ($groupId === null) {
            $defaultGroup = $knowledgeBase->defaultDocumentGroup()->first();

            if ($defaultGroup) {
                return $defaultGroup;
            }

            throw ValidationException::withMessages([
                'group_id' => __('knowledge_base.documents.errors.default_group_missing'),
            ]);
        }

        $group = KnowledgeGroup::query()
            ->where('id', $groupId)
            ->where('knowledge_base_id', $knowledgeBase->id)
            ->first();

        if (! $group) {
            throw ValidationException::withMessages([
                'group_id' => __('knowledge_base.documents.errors.invalid_group'),
            ]);
        }

        return $group;
    }
}
