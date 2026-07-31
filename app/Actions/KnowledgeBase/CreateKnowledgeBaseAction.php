<?php

namespace App\Actions\KnowledgeBase;

use App\Data\KnowledgeBase\FormCreateKnowledgeBaseData;
use App\Enums\AttachmentPurpose;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeGroup;
use App\Models\User;
use App\Services\Storage\AttachmentBindingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 创建应用知识库基础信息。
 */
class CreateKnowledgeBaseAction
{
    use AsAction;

    /**
     * 注入附件绑定服务。
     */
    public function __construct(
        private readonly AttachmentBindingService $attachments,
    ) {}

    /**
     * 创建知识库并限定同一应用内知识库名称唯一。
     */
    public function handle(User $actor, FormCreateKnowledgeBaseData $data): KnowledgeBase
    {
        $name = trim($data->name);
        $this->ensureNameIsAvailable($name);
        $this->attachments->assertAssignable(
            attachable: new KnowledgeBase,
            attachmentId: $data->avatar_id,
            currentAttachmentId: null,
            actor: $actor,
            allowedPurposes: [AttachmentPurpose::Avatar],
            messageKey: 'knowledge_base.messages.invalid_attachment',
        );

        return DB::transaction(function () use ($actor, $data, $name): KnowledgeBase {
            $knowledgeBase = KnowledgeBase::query()->create([
                'name' => $name,
                'avatar_id' => filled($data->avatar_id) ? $data->avatar_id : null,
                'description' => filled($data->description) ? $data->description : null,
                'category' => $data->category,
            ]);
            $this->attachments->syncAttachment($knowledgeBase, 'avatar_id', null, $actor);

            KnowledgeGroup::query()->create([
                'knowledge_base_id' => $knowledgeBase->id,
                'parent_id' => null,
                'name' => KnowledgeBase::DEFAULT_GROUP_NAME,
                'is_default' => true,
                'sort_order' => 0,
            ]);

            return $knowledgeBase;
        });
    }

    /**
     * 接收创建知识库表单并返回知识库列表页。
     */
    public function asController(Request $request): RedirectResponse
    {

        $knowledgeBase = $this->handle($request->user(), FormCreateKnowledgeBaseData::from($request));

        return redirect()->route('app.manage.knowledge-bases.index', [
            'kb' => $knowledgeBase->id,
        ]);
    }

    /**
     * 校验当前应用内知识库名称是否可用。
     */
    private function ensureNameIsAvailable(string $name): void
    {
        $exists = KnowledgeBase::query()

            ->where('name', $name)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => __('knowledge_base.messages.name_exists'),
            ]);
        }
    }
}
