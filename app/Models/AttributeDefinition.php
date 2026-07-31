<?php

namespace App\Models;

use App\Enums\AttributeType;
use Illuminate\Database\Eloquent\Builder;
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
 * @property string $key 属性键名：应用内唯一，程序化引用标识
 * @property string $name 属性显示名称
 * @property string|null $description 属性说明
 * @property AttributeType $type 属性类型：text / textarea / number / date / boolean / single_select / multi_select
 * @property array|null $config 类型配置：如单选/多选的可选项列表、数值范围等
 * @property int $display_order 展示排序，越小越靠前
 * @property bool $is_filterable 是否可用于列表筛选
 * @property bool $is_api_writable 是否允许通过 API 写入
 * @property bool $is_ai_readable 是否允许 AI 读取
 * @property bool $is_ai_writable 是否允许 AI 写入
 * @property Carbon|null $deleted_at
 * @property mixed $use_factory
 * @property int|null $contact_attribute_values_count
 * @property-read Collection|ContactAttributeValue[] $contactAttributeValues
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AttributeDefinition active()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AttributeDefinition ordered()
 * @method static \Database\Factories\AttributeDefinitionFactory<self> factory($count = null, $state = [])
 */
class AttributeDefinition extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'attribute_definitions';

    protected $guarded = [];

    public const array RESERVED_KEYS = [
        'name',
        'type',
        'source',
        'primary_email',
        'primary_phone',
        'locale',
        'timezone',
        'country',
        'city',
        'last_seen_at',
        'email',
        'phone',
        'external_id',
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AttributeType::class,
            'config' => 'array',
            'display_order' => 'integer',
            'is_filterable' => 'boolean',
            'is_api_writable' => 'boolean',
            'is_ai_readable' => 'boolean',
            'is_ai_writable' => 'boolean',
        ];
    }

    public function contactAttributeValues(): HasMany
    {
        return $this->hasMany(ContactAttributeValue::class, 'definition_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->withoutTrashed();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('created_at');
    }

    public function usesOptions(): bool
    {
        return $this->type->usesOptions();
    }
}
