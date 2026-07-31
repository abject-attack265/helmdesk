<?php

namespace App\Services\KnowledgeBase;

use App\Enums\KnowledgeDocumentSourceType;
use App\Models\KnowledgeDocument;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * 将上传附件或手动正文写入本地临时文件，供文档解析器读取。
 */
class KnowledgeDocumentSourceMaterializer
{
    /**
     * 返回临时文件路径和解析所需的格式信息。
     *
     * @return array{
     *     path: string,
     *     mime_type: ?string,
     *     extension: ?string,
     * }
     */
    public function materialize(KnowledgeDocument $document): array
    {
        $document->loadMissing('originalFile');

        if ($document->source_type === KnowledgeDocumentSourceType::Upload) {
            if ($document->originalFile === null) {
                throw new RuntimeException(sprintf(
                    'Knowledge document [%s] has no original attachment.',
                    $document->id,
                ));
            }

            $attachment = $document->originalFile;
            $extension = (string) ($attachment->extension ?: $document->extension ?: 'bin');
            $path = $this->createTempPath((string) $document->id, $extension);

            $source = $attachment->filesystem()->readStream($attachment->object_key);
            if (! is_resource($source)) {
                throw new RuntimeException(sprintf(
                    'Failed to open attachment stream for knowledge document [%s].',
                    $document->id,
                ));
            }

            $dest = fopen($path, 'wb');
            if (! is_resource($dest)) {
                fclose($source);
                throw new RuntimeException(sprintf(
                    'Failed to allocate temporary file at [%s].',
                    $path,
                ));
            }

            try {
                stream_copy_to_stream($source, $dest);
            } finally {
                fclose($source);
                fclose($dest);
            }

            return [
                'path' => $path,
                'mime_type' => (string) ($attachment->mime_type ?: $document->mime_type),
                'extension' => $extension,
            ];
        }

        if (! filled($document->content)) {
            throw new RuntimeException(sprintf(
                'Manual knowledge document [%s] has empty content.',
                $document->id,
            ));
        }

        $content = (string) $document->content;
        $extension = (string) ($document->extension ?: 'md');
        $path = $this->createTempPath((string) $document->id, $extension);
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException(sprintf(
                'Failed to write manual document content to [%s].',
                $path,
            ));
        }

        return [
            'path' => $path,
            'mime_type' => (string) ($document->mime_type ?: 'text/markdown'),
            'extension' => $extension,
        ];
    }

    /**
     * 删除解析临时文件，失败时记录 warning。
     */
    public function cleanup(string $path): void
    {
        if (! is_file($path)) {
            Log::warning('[knowledge] 文档解析临时文件不存在', ['path' => $path]);

            return;
        }

        if (! @unlink($path)) {
            Log::warning('[knowledge] 文档解析临时文件删除失败', ['path' => $path]);
        }
    }

    /**
     * 生成位于 storage/app/private/knowledge-temp 下的临时文件路径，并保证父目录存在。
     */
    private function createTempPath(string $documentId, string $extension): string
    {
        $dir = storage_path('app/private/knowledge-temp');
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException(sprintf('Failed to create temp dir [%s].', $dir));
        }

        $extension = preg_replace('/[^a-zA-Z0-9]/', '', $extension) ?: 'bin';
        $unique = bin2hex(random_bytes(8));

        return $dir.DIRECTORY_SEPARATOR.$documentId.'-'.$unique.'.'.$extension;
    }
}
