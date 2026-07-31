<?php

namespace App\Console\Commands;

use App\Actions\Attachment\DeleteAttachmentAction;
use App\Enums\AttachmentStatus;
use App\Models\Attachment;
use Illuminate\Console\Command;

/**
 * 清理过期未绑定附件对象和软删除附件记录。
 */
class CleanupAttachmentsCommand extends Command
{
    /** @var string 命令名称和参数签名。 */
    protected $signature = 'attachments:cleanup';

    /** @var string 命令说明。 */
    protected $description = 'Remove stale unbound attachments and purge soft-deleted rows.';

    /**
     * 执行附件清理任务。
     */
    public function handle(): int
    {
        // 签名失效后再删除一次临时 key，回收 finalize 后复用旧签名产生的隔离对象。
        $expiredUploadKeys = Attachment::query()
            ->whereNotNull('upload_object_key')
            ->where('upload_expires_at', '<', now())
            ->get();

        foreach ($expiredUploadKeys as $attachment) {
            $attachment->filesystem()->delete($attachment->upload_object_key);
            $attachment->update([
                'upload_object_key' => null,
                'upload_expires_at' => null,
            ]);
        }

        // 未绑定附件：放弃的占位、校验失败对象或上传后未绑定对象，过期/超时即回收。
        $orphans = Attachment::query()
            ->whereIn('status', [
                AttachmentStatus::Pending,
                AttachmentStatus::Uploaded,
                AttachmentStatus::Failed,
            ])
            ->whereNull('attachable_id')
            ->where(function ($query): void {
                $query
                    ->where('expires_at', '<', now())
                    ->orWhere('uploaded_at', '<', now()->subDay());
            })
            ->get();

        foreach ($orphans as $attachment) {
            DeleteAttachmentAction::run($attachment);
        }

        $deleted = Attachment::onlyTrashed()
            ->where('status', AttachmentStatus::Deleted)
            ->where('deleted_at', '<', now()->subDays(7))
            ->get();

        foreach ($deleted as $attachment) {
            $attachment->forceDelete();
        }

        $this->components->info(sprintf(
            'Cleaned upload keys: %d, deleted orphan attachments: %d, purged rows: %d',
            $expiredUploadKeys->count(),
            $orphans->count(),
            $deleted->count(),
        ));

        return self::SUCCESS;
    }
}
