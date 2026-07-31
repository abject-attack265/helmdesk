<?php

namespace App\Models;

use App\Enums\IdentityType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string $contact_id 所属联系人 contacts.id
 * @property IdentityType $type 身份类型：session 会话 / email / phone / external_id 外部 ID
 * @property string $namespace 命名空间：用于区分同类型不同渠道来源（如不同 IM 平台）
 * @property string $value 身份原始值：用于唯一匹配（如 token、邮箱、外部用户 ID）
 * @property string|null $display_value 展示值：面向坐席的可读身份
 * @property string|null $unreachable_at 渠道侧确认不可达的时间（如 Telegram 用户拉黑 bot），群发受众自动剔除
 * @property string|null $unreachable_reason 不可达原因（渠道侧错误描述）
 * @property mixed $use_factory
 * @property int|null $contacts_count
 * @property-read Contact $contact
 *
 * @method static \Database\Factories\ContactIdentityFactory<self> factory($count = null, $state = [])
 */
class ContactIdentity extends Model
{
    /**
     * 联系人身份标识模型，保存邮箱、手机号、会话 token 或外部 ID 等可匹配身份。
     */
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'contact_identities';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => IdentityType::class,
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
