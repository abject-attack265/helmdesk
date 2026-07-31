<?php

namespace App\Actions\KnowledgeBase\Document;

use App\Enums\KnowledgeDocumentSourceType;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 以内联方式输出知识库文档原文件，供 PDF / DOCX 预览器读取。
 */
class StreamKnowledgeDocumentPreviewFileAction
{
    use AsAction;

    /**
     * 允许预览的扩展名到 Content-Type 的白名单映射。
     * 不直接信任客户端上报的 mime_type，避免 .html 伪装成可执行类型导致浏览器误解析。
     *
     * 注意：html/htm 故意以 text/plain 输出。该接口在应用自身 origin 上以 inline 直出文件，
     * 若按 text/html 返回，用户上传的恶意 .html（含 <script>）经"新窗口打开"直链将绕过前端 DOMPurify
     * 在后台 origin 下执行（存储型 XSS）。前端预览弹窗通过 fetch 读取文本后再经 DOMPurify 渲染，
     * 不依赖此处的 Content-Type，故降级为 text/plain 不影响弹窗内预览。
     */
    private const array PREVIEW_MIME_BY_EXTENSION = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'md' => 'text/markdown; charset=utf-8',
        'markdown' => 'text/markdown; charset=utf-8',
        'txt' => 'text/plain; charset=utf-8',
        'html' => 'text/plain; charset=utf-8',
        'htm' => 'text/plain; charset=utf-8',
    ];

    /**
     * 输出可预览的文档正文：upload 类型从附件流式读取；manual 类型直接吐 content 字段。
     */
    public function handle(KnowledgeDocument $document): StreamedResponse
    {
        $document->loadMissing('originalFile');

        if ($document->source_type === KnowledgeDocumentSourceType::Manual && ! $document->originalFile) {
            return $this->streamInlineContent($document);
        }

        abort_if(! $document->originalFile, 404);

        $attachment = $document->originalFile;
        $contentType = $this->resolvePreviewContentType($document, $attachment->extension);
        abort_if($contentType === null, 404);

        $stream = $attachment->filesystem()->readStream($attachment->object_key);
        abort_if(! is_resource($stream), 404);

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $contentType,
            'Content-Length' => (string) $attachment->byte_size,
            'Content-Disposition' => $this->inlineDisposition($attachment->original_name),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    private function streamInlineContent(KnowledgeDocument $document): StreamedResponse
    {
        $content = (string) $document->content;

        return response()->stream(function () use ($content): void {
            echo $content;
        }, 200, [
            'Content-Type' => 'text/markdown; charset=utf-8',
            'Content-Length' => (string) strlen($content),
            'Content-Disposition' => $this->inlineDisposition($document->original_filename),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * 接收预览文件请求，并限制在当前应用知识库文档内。
     */
    public function asController(Request $request, string $knowledgeBase, string $document): StreamedResponse
    {

        $kb = KnowledgeBase::query()

            ->findOrFail($knowledgeBase);

        $documentModel = KnowledgeDocument::query()
            ->where('knowledge_base_id', $kb->id)
            ->findOrFail($document);

        return $this->handle($documentModel);
    }

    /**
     * 构建 inline Content-Disposition，兼容中文文件名。
     */
    private function inlineDisposition(string $name): string
    {
        return sprintf("inline; filename*=UTF-8''%s", rawurlencode($name));
    }

    /**
     * 按扩展名白名单解析预览响应的 Content-Type，未命中返回 null。
     */
    private function resolvePreviewContentType(KnowledgeDocument $document, ?string $attachmentExtension): ?string
    {
        $candidates = [
            $attachmentExtension,
            $document->extension,
            pathinfo((string) $document->original_filename, PATHINFO_EXTENSION),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }

            $normalized = strtolower($candidate);
            if (isset(self::PREVIEW_MIME_BY_EXTENSION[$normalized])) {
                return self::PREVIEW_MIME_BY_EXTENSION[$normalized];
            }
        }

        return null;
    }
}
