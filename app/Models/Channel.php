<?php

namespace App\Models;

use App\Casts\ChannelSettingsCast;
use App\Data\Channel\Telegram\ChannelTelegramSettingsData;
use App\Data\Channel\Web\ChannelWebSettingsData;
use App\Enums\ChannelType;
use Database\Factories\ChannelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property ChannelType $type 渠道类型：web / telegram / wechat_oa
 * @property string $name 渠道名称
 * @property string|null $description 渠道说明
 * @property string $code 渠道公开定位码
 * @property string|null $reception_plan_id 绑定的接待方案
 * @property Data|null $settings 渠道配置 JSON
 * @property string|null $first_embed_host widget 首次嵌入的宿主域名
 * @property Carbon|null $first_embed_at widget 首次嵌入时间
 * @property string|null $last_embed_host widget 最近一次嵌入的宿主域名
 * @property Carbon|null $last_embed_at widget 最近一次嵌入时间
 * @property mixed $use_factory
 * @property int|null $reception_plans_count
 * @property-read ReceptionPlan|null $receptionPlan
 *
 * @method static \Database\Factories\ChannelFactory<self> factory($count = null, $state = [])
 */
class Channel extends Model
{
    /**
     * 渠道模型，保存访客入口的接待方案与渠道配置。
     * 渠道绑定接待方案（reception_plan_id）并自动跟随其最新已发布版本；新会话创建时按解析出的最新版锁定快照。
     */

    /** @use HasFactory<ChannelFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $guarded = [];

    /**
     * 返回渠道字段类型转换配置。
     */
    protected function casts(): array
    {
        return [
            'type' => ChannelType::class,
            'settings' => ChannelSettingsCast::class,
            'first_embed_at' => 'datetime',
            'last_embed_at' => 'datetime',
        ];
    }

    /**
     * 渠道绑定的接待方案；渠道运行时自动使用该方案的最新已发布版本。
     */
    public function receptionPlan(): BelongsTo
    {
        return $this->belongsTo(ReceptionPlan::class, 'reception_plan_id');
    }

    /**
     * 返回 Telegram 渠道设置。
     */
    public function telegramSettings(): ChannelTelegramSettingsData
    {
        return $this->settings;
    }

    /**
     * 返回网站渠道设置。
     */
    public function webSettings(): ChannelWebSettingsData
    {
        return $this->settings;
    }

    /**
     * 注册渠道 code 生成逻辑。
     */
    protected static function booted(): void
    {
        static::creating(function (Channel $channel) {
            if (! filled($channel->code)) {
                $channel->code = static::generateUniqueCode($channel->type ?? ChannelType::Web);
            }
        });
    }

    /**
     * 生成指定渠道类型的唯一公开 code。
     */
    private static function generateUniqueCode(ChannelType $type): string
    {
        $prefix = match ($type) {
            ChannelType::Web => 'wch',
            ChannelType::Telegram => 'tg',
            ChannelType::WechatOfficialAccount => 'wxoa',
        };

        do {
            $code = $prefix.'_'.Str::lower(Str::random(12));
        } while (static::query()->where('code', $code)->exists());

        return $code;
    }
}
