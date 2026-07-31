<?php

namespace App\Models;

use Database\Factories\ConversationPageViewFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $conversation_id 所属会话
 * @property string|null $contact_id 访客联系人
 * @property string $url 访问页面 URL
 * @property string|null $title 页面标题
 * @property string|null $referrer 来源页 referrer
 * @property Carbon $viewed_at 页面访问时间
 * @property mixed $use_factory
 * @property int|null $conversations_count
 * @property-read Conversation $conversation
 *
 * @method static \Database\Factories\ConversationPageViewFactory<self> factory($count = null, $state = [])
 */
class ConversationPageView extends Model
{
    /**
     * 访客浏览轨迹模型：记录一次会话内访客访问过的页面，时间序列。
     */

    /** @use HasFactory<ConversationPageViewFactory> */
    use HasFactory, HasUlids;

    protected $guarded = [];

    /**
     * 返回浏览轨迹字段的类型转换配置。
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }

    /**
     * 关联轨迹所属会话。
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
