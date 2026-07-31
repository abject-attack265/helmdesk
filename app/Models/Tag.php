<?php

namespace App\Models;

use App\Enums\TagSource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string $tag_group_id 所属标签组 ID（标签必属于一个组，维度经由组继承）
 * @property string $name 标签名
 * @property string $normalized_name 标准化标签名
 * @property string|null $color 标签颜色
 * @property string|null $description 标签描述
 * @property TagSource $source 标签来源
 * @property bool $is_locked 是否锁定
 * @property string|null $created_by_user_id
 * @property string|null $updated_by_user_id
 * @property mixed $use_factory
 * @property int|null $tag_groups_count
 * @property int|null $contacts_count
 * @property int|null $conversations_count
 * @property-read TagGroup $tagGroup
 * @property-read Collection|Contact[] $contacts
 * @property-read Collection|Conversation[] $conversations
 *
 * @method static \Database\Factories\TagFactory<self> factory($count = null, $state = [])
 */
class Tag extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'tags';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (Tag $tag): void {
            $tag->name = trim($tag->name);
            $tag->normalized_name = mb_strtolower($tag->name);
        });

        static::updating(function (Tag $tag): void {
            if ($tag->isDirty('name')) {
                $tag->name = trim($tag->name);
                $tag->normalized_name = mb_strtolower($tag->name);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => TagSource::class,
            'is_locked' => 'boolean',
        ];
    }

    public function tagGroup(): BelongsTo
    {
        return $this->belongsTo(TagGroup::class);
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_tag_assignments')
            ->withPivot('source', 'assigned_by_user_id', 'created_at');
    }

    /**
     * 打到本标签上的会话（经由 conversation_tag_assignments）；不含已被人工抑制的记录。
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_tag_assignments')
            ->withPivot('source', 'confidence', 'reason', 'assigned_by_user_id', 'based_on_seq_no', 'created_at')
            ->wherePivotNull('removed_at');
    }
}
