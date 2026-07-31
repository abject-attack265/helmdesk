<?php

namespace App\Models;

use App\Enums\AttributeValueSource;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $contact_id 所属联系人 contacts.id
 * @property string $definition_id 属性定义 attribute_definitions.id
 * @property array $value_json 属性值：按属性类型存储的结构化值（标量/数组）
 * @property AttributeValueSource $source 赋值来源：manual 人工 / api / import / workflow / ai / merge / channel
 * @property float|null $confidence 置信度：AI 赋值时的可信度评分
 * @property string|null $updated_by_user_id 最后更新人 user_id：系统/AI 来源时为空
 * @property mixed $use_factory
 * @property int|null $contacts_count
 * @property int|null $definitions_count
 * @property int|null $updated_by_users_count
 * @property-read Contact $contact
 * @property-read AttributeDefinition $definition
 * @property-read User|null $updatedByUser
 *
 * @method static \Database\Factories\ContactAttributeValueFactory<self> factory($count = null, $state = [])
 */
class ContactAttributeValue extends Model
{
    /**
     * 联系人自定义属性值模型，保存联系人数据字段在当前应用内的取值。
     */
    use HasFactory, HasUlids;

    protected $table = 'contact_attribute_values';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_json' => 'array',
            'source' => AttributeValueSource::class,
            'confidence' => 'float',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(AttributeDefinition::class, 'definition_id')->withTrashed();
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id')->withTrashed();
    }

    public function value(): mixed
    {
        return data_get($this->value_json, 'value');
    }
}
