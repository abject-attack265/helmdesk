<?php

namespace App\Actions\KnowledgeBase\Document;

use App\Actions\KnowledgeBase\Indexing\DispatchKnowledgeDocumentPipelineAction;
use App\Data\KnowledgeBase\FormUploadKnowledgeDocumentData;
use App\Data\KnowledgeBase\ListKnowledgeDocumentItemData;
use App\Enums\AttachmentPurpose;
use App\Enums\AttachmentStatus;
use App\Enums\KnowledgeBaseCategory;
use App\Enums\KnowledgeDocumentParseStatus;
use App\Enums\KnowledgeDocumentSourceType;
use App\Enums\KnowledgeDocumentStatus;
use App\Exceptions\BusinessException;
use App\Models\Attachment;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeGroup;
use App\Services\Storage\AttachmentPathGenerator;
use App\Services\Storage\StorageProfileDisk;
use App\Services\Storage\StorageProfileResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * 上传一个或多个文档到指定知识库（可选分组），校验扩展名、保存文件元数据和原文件。
 */
class UploadKnowledgeDocumentAction
{
    use AsAction;

    /**
     * 注入文档原文件存储所需服务。
     */
    public function __construct(
        private readonly AttachmentPathGenerator $pathGenerator,
        private readonly StorageProfileResolver $profileResolver,
        private readonly DispatchKnowledgeDocumentPipelineAction $pipeline,
    ) {}

    /**
     * 可以作为文本直接读入 content 字段的扩展名。其它格式仅保留元数据，留给下游解析。
     */
    private const array TEXT_EXTENSIONS = [
        'md',
        'markdown',
        'txt',
    ];

    /**
     * 校验上传文件类型，落库为单个知识库文档。
     */
    public function handle(KnowledgeBase $knowledgeBase, UploadedFile $file, ?string $groupId = null, ?string $uploaderUserId = null): KnowledgeDocument
    {
        $this->assertDocumentKnowledgeBase($knowledgeBase);

        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, FormUploadKnowledgeDocumentData::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'files' => __('knowledge_base.documents.errors.unsupported_extension'),
            ]);
        }

        $group = $this->resolveTargetGroup($knowledgeBase, $groupId);

        $content = $this->readTextContent($file, $extension);
        $byteSize = (int) $file->getSize();
        if ($byteSize <= 0 && $content !== null) {
            $byteSize = strlen($content);
        }

        $checksum = hash_file('sha256', $file->getRealPath());
        if ($checksum === false) {
            throw new RuntimeException(sprintf(
                'Failed to calculate checksum for uploaded file [%s].',
                $file->getClientOriginalName(),
            ));
        }

        $document = DB::transaction(function () use ($knowledgeBase, $file, $group, $uploaderUserId, $extension, $byteSize, $checksum, $content): KnowledgeDocument {
            /** @var KnowledgeDocument $document */
            $document = KnowledgeDocument::query()->create([
                'knowledge_base_id' => $knowledgeBase->id,
                'group_id' => $group->id,
                'uploaded_by_user_id' => $uploaderUserId,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType() ?: 'application/octet-stream',
                'byte_size' => $byteSize,
                'extension' => $extension,
                'checksum_sha256' => $checksum,
                'source_type' => KnowledgeDocumentSourceType::Upload,
                'status' => KnowledgeDocumentStatus::Pending,
                'error_message' => null,
                'content' => $content,
                'parse_status' => KnowledgeDocumentParseStatus::Pending,
            ]);

            $this->storeOriginalFile($document, $file, $uploaderUserId, $checksum);

            return $document->fresh(['originalFile'])
                ?? throw new LogicException('Knowledge document disappeared during upload.');
        });

        $this->pipeline->handle($document, forceReparse: true);

        Log::info('[knowledge] 知识文档上传完成', [
            'knowledge_base_id' => (string) $knowledgeBase->id,
            'document_id' => (string) $document->id,
            'extension' => $extension,
            'byte_size' => $byteSize,
        ]);

        return $document->fresh(['originalFile', 'knowledgeBase'])
            ?? throw new LogicException('Knowledge document disappeared after pipeline dispatch.');
    }

    /**
     * 普通文档不能写入问答知识库；向问答知识库上传文档时拒绝提交。
     */
    private function assertDocumentKnowledgeBase(KnowledgeBase $knowledgeBase): void
    {
        if ($knowledgeBase->category !== KnowledgeBaseCategory::Qa) {
            return;
        }

        throw new BusinessException(__('knowledge_base.documents.errors.not_document_knowledge_base'));
    }

    /**
     * 处理上传文档表单提交：
     *  - axios（弹窗串行调用）：返回 JSON，前端逐个文件展示状态。
     *  - Inertia / 表单回退：批量入库后重定向到列表。
     */
    public function asController(Request $request, string $knowledgeBase): RedirectResponse|JsonResponse
    {

        $kb = KnowledgeBase::query()

            ->findOrFail($knowledgeBase);

        $data = FormUploadKnowledgeDocumentData::from($request);
        $groupId = filled($data->group_id) ? $data->group_id : null;
        $group = $this->resolveTargetGroup($kb, $groupId);

        $userId = (string) $request->user()?->id;

        if ($request->expectsJson()) {
            return $this->handleJsonRequest($kb, $data, (string) $group->id, $userId);
        }

        return $this->handleInertiaRequest($kb, $data, (string) $group->id, $userId);
    }

    /**
     * axios 弹窗调用：当前每次只会上传一个文件，逐个返回创建好的文档 Data。
     */
    private function handleJsonRequest(KnowledgeBase $kb, FormUploadKnowledgeDocumentData $data, string $groupId, string $userId): JsonResponse
    {
        $documents = collect($data->files)
            ->map(fn (UploadedFile $file) => $this->handle($kb, $file, $groupId, $userId))
            ->map(fn (KnowledgeDocument $document) => ListKnowledgeDocumentItemData::fromModel($document, $kb))
            ->all();

        return response()->json(['documents' => $documents]);
    }

    /**
     * Inertia / 表单回退入口：批量入库后跳回列表。
     */
    private function handleInertiaRequest(KnowledgeBase $kb, FormUploadKnowledgeDocumentData $data, string $groupId, string $userId): RedirectResponse
    {
        foreach ($data->files as $file) {
            $this->handle($kb, $file, $groupId, $userId);
        }

        return redirect()->route('app.manage.knowledge-bases.index', [
            'kb' => $kb->id,
            'group' => $groupId,
        ]);
    }

    /**
     * 校验目标分组属于当前知识库；不属于则报错。
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

    /**
     * 文本类扩展名的文件直接读入正文（去除 UTF-8 BOM），其它格式返回 null 留给下游解析。
     */
    private function readTextContent(UploadedFile $file, string $extension): ?string
    {
        if (! in_array($extension, self::TEXT_EXTENSIONS, true)) {
            return null;
        }

        $raw = (string) file_get_contents($file->getRealPath());
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        return $raw;
    }

    /**
     * 将知识库文档原文件写入对象存储（带公开 CDN 直读所需的 Content-Type/Disposition/缓存头），并作为附件绑定到文档。
     */
    private function storeOriginalFile(KnowledgeDocument $document, UploadedFile $file, ?string $uploaderUserId, ?string $checksum): Attachment
    {
        $profile = $this->profileResolver->resolveForNewUpload();
        $attachmentId = (string) Str::ulid();
        $mimeType = $file->getClientMimeType() ?: 'application/octet-stream';
        $objectKey = $this->pathGenerator->generate(
            attachmentId: $attachmentId,
            purpose: AttachmentPurpose::KnowledgeDocument,
            originalName: $file->getClientOriginalName(),
            mimeType: $mimeType,
        );

        $disk = StorageProfileDisk::build($profile);
        $stream = fopen($file->getRealPath(), 'rb');
        try {
            if (! is_resource($stream) || ! $disk->put($objectKey, $stream, [
                'ContentType' => $mimeType,
                'ContentDisposition' => Attachment::dispositionFor($mimeType, $document->original_filename),
                'CacheControl' => Attachment::CACHE_CONTROL,
            ])) {
                throw ValidationException::withMessages([
                    'files' => __('attachments.errors.persist_failed'),
                ]);
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return Attachment::query()->create([
            'id' => $attachmentId,
            'uploaded_by_user_id' => $uploaderUserId,
            'storage_profile_id' => $profile->id,
            'object_key' => $objectKey,
            'original_name' => $document->original_filename,
            'mime_type' => $mimeType,
            'extension' => $this->pathGenerator->extension($document->original_filename, $mimeType),
            'byte_size' => $document->byte_size,
            'checksum_sha256' => $checksum,
            'purpose' => AttachmentPurpose::KnowledgeDocument,
            'status' => AttachmentStatus::Attached,
            'attachable_type' => $document->getMorphClass(),
            'attachable_id' => $document->getKey(),
            'metadata' => [],
            'uploaded_at' => now(),
            'attached_at' => now(),
        ]);
    }
}
