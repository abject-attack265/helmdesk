<?php

namespace App\Models;

use App\Enums\TagScope;
use Database\Factories\TagGroupFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string $name 标签组名
 * @property string $normalized_name 标准化组名
 * @property TagScope $scope 适用维度：conversation / contact
 * @property int $sort_order 组排序权重
 * @property string|null $created_by_user_id
 * @property string|null $updated_by_user_id
 * @property mixed $use_factory
 * @property int|null $tags_count
 * @property-read Collection|Tag[] $tags
 *
 * @method static \Database\Factories\TagGroupFactory<self> factory($count = null, $state = [])
 */
class TagGroup extends Model
{
    /**
     * 标签组，按适用维度（会话/联系人）归类标签。
     * 维度（scope）挂在组上，组内标签经由所属组继承维度；标签必属于且仅属于一个组。
     */
    /** @use HasFactory<TagGroupFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (TagGroup $group): void {
            $group->name = trim($group->name);
            $group->normalized_name = mb_strtolower($group->name);
        });

        static::updating(function (TagGroup $group): void {
            if ($group->isDirty('name')) {
                $group->name = trim($group->name);
                $group->normalized_name = mb_strtolower($group->name);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => TagScope::class,
            'sort_order' => 'integer',
        ];
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }
}
