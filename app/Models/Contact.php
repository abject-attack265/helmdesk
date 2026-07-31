<?php

namespace App\Models;

use App\Enums\ContactSource;
use App\Enums\ContactType;
use App\Enums\IdentityType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laravel\Scout\Searchable;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property ContactType $type 联系人类型：visitor 访客 / contact 客户
 * @property ContactSource $source 来源渠道
 * @property string|null $name
 * @property string $avatar_url
 * @property Carbon|null $avatar_synced_at 头像最近一次同步时间
 * @property string|null $locale 语言偏好
 * @property string|null $timezone 时区
 * @property string|null $country 国家
 * @property string|null $city 城市
 * @property string|null $primary_email 主邮箱
 * @property string|null $primary_phone 主手机号
 * @property array|null $ai_context AI 上下文摘要：供 AI 接待引用的结构化画像数据
 * @property string|null $note 内部备注
 * @property bool $is_important 是否标记为重点联系人
 * @property Carbon|null $important_at 标记重点的时间
 * @property string|null $important_by_user_id 标记重点的操作人 user_id
 * @property string|null $important_source 重点标记来源：manual 人工 / ai 等
 * @property Carbon|null $last_seen_at 最近活跃时间
 * @property mixed $use_factory
 * @property int|null $identities_count
 * @property int|null $activity_logs_count
 * @property int|null $conversations_count
 * @property int|null $custom_attribute_values_count
 * @property int|null $tags_count
 * @property-read Collection|ContactIdentity[] $identities
 * @property-read Collection|ContactActivityLog[] $activityLogs
 * @property-read Collection|Conversation[] $conversations
 * @property-read Collection|ContactAttributeValue[] $customAttributeValues
 * @property-read Collection|Tag[] $tags
 *
 * @method static \Database\Factories\ContactFactory<self> factory($count = null, $state = [])
 */
class Contact extends Model
{
    /**
     * 联系人主模型，承载访客沉淀后的资料、身份汇总和接待上下文。
     */
    use HasFactory, HasUlids, Searchable, SoftDeletes;

    public const string DEFAULT_AVATAR_URL = '/images/default-avatar.svg';

    protected $table = 'contacts';

    protected $guarded = [];

    /**
     * 返回联系人字段的类型转换配置。
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ContactType::class,
            'source' => ContactSource::class,
            'ai_context' => 'array',
            'is_important' => 'boolean',
            'important_at' => 'datetime',
            'avatar_synced_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * 是否已有区别于占位图的真实头像。建档时 avatar_url 一律写占位图，不会为 null。
     */
    public function hasCustomAvatar(): bool
    {
        return $this->avatar_url !== self::DEFAULT_AVATAR_URL;
    }

    /**
     * 关联联系人的渠道身份。
     */
    public function identities(): HasMany
    {
        return $this->hasMany(ContactIdentity::class);
    }

    /**
     * 关联联系人的活动日志。
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ContactActivityLog::class);
    }

    /**
     * 关联联系人的全部会话。
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * 关联联系人的自定义属性值。
     */
    public function customAttributeValues(): HasMany
    {
        return $this->hasMany(ContactAttributeValue::class);
    }

    /**
     * 关联联系人当前持有的标签。
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'contact_tag_assignments')
            ->withPivot('source', 'assigned_by_user_id', 'created_at');
    }

    /**
     * 返回联系人搜索索引文档。
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing(['identities', 'tags']);

        $identityValues = $this->identities
            ->whereNotNull('display_value')
            ->pluck('display_value')
            ->implode(' ');

        $tagNames = $this->tags
            ->whereNull('deleted_at')
            ->pluck('name')
            ->implode(' ');

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'source' => $this->source->value,
            'name' => $this->name ?? '',
            'identities' => $identityValues,
            'tags' => $tagNames,
            'created_at' => (int) ($this->created_at?->getTimestamp() ?? 0),
        ];
    }

    /**
     * 返回联系人姓名或匿名访客名称。
     */
    public function displayName(): string
    {
        if (filled($this->name)) {
            return (string) $this->name;
        }

        $suffix = strtoupper(substr((string) $this->id, -4));

        return __('contact.anonymous_visitor_with_suffix', ['suffix' => $suffix]);
    }

    /**
     * 从身份记录同步主邮箱、主手机号和搜索索引。
     */
    public function syncPrimaryFields(): void
    {
        $this->primary_email = $this->identities()
            ->where('type', IdentityType::Email)
            ->oldest()
            ->value('value');

        $this->primary_phone = $this->identities()
            ->where('type', IdentityType::Phone)
            ->oldest()
            ->value('value');

        $this->saveQuietly();
        $this->searchable();
    }
}
