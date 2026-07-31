<?php

namespace App\Models;

use App\Enums\AttachmentPurpose;
use App\Enums\AttachmentStatus;
use App\Services\Storage\AttachmentUrlResolver;
use App\Services\Storage\StorageProfileDisk;
use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $uploaded_by_user_id 上传者用户；系统 / 访客上传可为空
 * @property string $storage_profile_id 文件存储配置 ID
 * @property string $object_key 对象存储中的文件 key
 * @property string|null $upload_object_key 直传签名对应的临时对象 key
 * @property string $original_name 上传时的原始文件名
 * @property string $mime_type MIME 类型
 * @property string|null $extension 文件扩展名
 * @property int $byte_size 文件大小（字节）
 * @property string|null $checksum_sha256 文件 SHA256 校验和
 * @property string|null $etag 对象存储返回的 ETag
 * @property AttachmentPurpose $purpose 用途：avatar / channel_icon / conversation_image / conversation_file / knowledge_document / import / other
 * @property AttachmentStatus $status 生命周期状态：pending / uploaded / attached / failed / expired / deleted
 * @property string|null $session_token_hash 访客上传会话 token 的 sha256；绑定到业务对象时复核归属
 * @property string|null $attachable_type
 * @property string|null $attachable_id
 * @property array|null $metadata 扩展元数据（图片尺寸等）
 * @property Carbon|null $uploaded_at 上传完成时间
 * @property Carbon|null $attached_at 附着到业务对象的时间
 * @property Carbon|null $expires_at 过期时间，过期可被回收
 * @property Carbon|null $upload_expires_at 直传签名失效时间，失效后最终清理临时对象
 * @property string $full_url
 * @property mixed $use_factory
 * @property int|null $attachables_count
 * @property int|null $uploaded_bies_count
 * @property-read Model|null $attachable
 * @property-read StorageProfile $storageProfile
 * @property-read User|null $uploadedBy
 *
 * @method static \Database\Factories\AttachmentFactory<self> factory($count = null, $state = [])
 */
class Attachment extends Model
{
    /**
     * 附件模型，记录上传文件的存储位置、归属对象和访问地址。
     */
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'attachments';

    protected $guarded = [];

    protected $appends = [
        'full_url',
    ];

    /** @var string 对象内容寻址、永不覆盖，可被浏览器与 CDN 永久缓存。 */
    public const string CACHE_CONTROL = 'public, max-age=31536000, immutable';

    /**
     * 可安全内联渲染的图片类型；其它类型（含 SVG 等可执行内容）一律按附件下载。
     *
     * @var list<string>
     */
    public const array INLINE_IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => AttachmentPurpose::class,
            'status' => AttachmentStatus::class,
            'byte_size' => 'integer',
            'metadata' => 'array',
            'uploaded_at' => 'datetime',
            'attached_at' => 'datetime',
            'expires_at' => 'datetime',
            'upload_expires_at' => 'datetime',
        ];
    }

    /**
     * 关联附件绑定的业务模型。
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 指定 MIME 是否可安全内联渲染（仅白名单图片类型；SVG 等强制下载）。
     */
    public static function isInlineMime(string $mimeType): bool
    {
        return in_array($mimeType, self::INLINE_IMAGE_MIME_TYPES, true);
    }

    /**
     * 生成对象的 Content-Disposition：可内联图片用 inline，其余按原始文件名下载。
     */
    public static function dispositionFor(string $mimeType, string $originalName): string
    {
        $disposition = self::isInlineMime($mimeType) ? 'inline' : 'attachment';

        return $disposition."; filename*=UTF-8''".rawurlencode($originalName);
    }

    /**
     * 解析附件完整访问地址。
     */
    public function getFullUrlAttribute(): string
    {
        return app(AttachmentUrlResolver::class)->url($this);
    }

    /**
     * 关联上传附件的用户。
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id')->withTrashed();
    }

    public function storageProfile(): BelongsTo
    {
        return $this->belongsTo(StorageProfile::class, 'storage_profile_id');
    }

    /**
     * 按 ID 查找附件的公开访问地址。
     */
    public static function findUrl(?string $id): ?string
    {
        if (! filled($id)) {
            return null;
        }

        return static::query()->find($id)?->full_url;
    }

    public function filesystem(): FilesystemAdapter
    {
        return StorageProfileDisk::build($this->storageProfile);
    }
}
