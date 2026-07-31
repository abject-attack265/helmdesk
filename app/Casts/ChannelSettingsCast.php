<?php

namespace App\Casts;

use App\Casts\Concerns\DecodesJsonArrays;
use App\Data\Channel\Telegram\ChannelTelegramSettingsData;
use App\Data\Channel\Web\ChannelWebSettingsData;
use App\Data\Channel\WechatOfficialAccount\ChannelWechatOfficialAccountSettingsData;
use App\Enums\ChannelType;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use Spatie\LaravelData\Data;

/**
 * 渠道 settings JSON 列的类型分流 cast。
 *
 * 不同渠道类型的设置结构不同，按 channels.type 选择对应的 Data 类反序列化；
 * JSON 中缺省的字段由各 Data 的构造函数默认值补齐。
 *
 * settings 内含的渠道密钥按字段加密存储，
 * 非敏感配置仍为明文以便运维查看；列本身保持 JSON 类型，不引入整列加密。
 *
 * @implements CastsAttributes<Data, Data>
 */
class ChannelSettingsCast implements CastsAttributes
{
    use DecodesJsonArrays;

    /**
     * 各渠道类型 settings 中需要加密存储的密钥字段（snake_case，与序列化后的 JSON key 对齐）。
     *
     * @var array<string, list<string>>
     */
    private const array SECRET_KEYS_BY_TYPE = [
        ChannelType::Web->value => ['user_token_secret'],
        ChannelType::Telegram->value => ['bot_token', 'webhook_secret'],
        ChannelType::WechatOfficialAccount->value => ['app_secret', 'token', 'aes_key'],
    ];

    /**
     * 按渠道类型把 JSON 列反序列化为对应的设置 Data，并解密其中的密钥字段。
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): Data
    {
        $type = $this->resolveType($attributes);
        $decoded = $this->decryptSecretKeys($this->decodeJsonArray($value), $type);

        return match ($type) {
            ChannelType::Telegram => ChannelTelegramSettingsData::from($decoded),
            ChannelType::Web => ChannelWebSettingsData::from($decoded),
            ChannelType::WechatOfficialAccount => ChannelWechatOfficialAccountSettingsData::from($decoded),
        };
    }

    /**
     * 把设置 Data / 数组序列化为 JSON 字符串写回数据库，密钥字段加密后再编码。
     *
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        $array = $this->toArray($value);
        $encrypted = $this->encryptSecretKeys($array, $this->resolveType($attributes));

        return [$key => json_encode($encrypted, JSON_THROW_ON_ERROR)];
    }

    /**
     * 把待写入的值统一转成关联数组。
     *
     * @return array<string, mixed>
     */
    private function toArray(mixed $value): array
    {
        if ($value instanceof Data) {
            return $value->toArray();
        }

        if (is_array($value)) {
            return $value;
        }

        throw new InvalidArgumentException('settings 只接受 Data、数组或 null。');
    }

    /**
     * 加密 settings 中的非空密钥字段。
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function encryptSecretKeys(array $settings, ChannelType $type): array
    {
        foreach (self::SECRET_KEYS_BY_TYPE[$type->value] ?? [] as $secretKey) {
            $secret = $settings[$secretKey] ?? null;
            if (is_string($secret) && $secret !== '') {
                $settings[$secretKey] = Crypt::encryptString($secret);
            }
        }

        return $settings;
    }

    /**
     * 解密 settings 中的密钥字段（空值跳过）。
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function decryptSecretKeys(array $settings, ChannelType $type): array
    {
        foreach (self::SECRET_KEYS_BY_TYPE[$type->value] ?? [] as $secretKey) {
            $secret = $settings[$secretKey] ?? null;
            if (is_string($secret) && $secret !== '') {
                $settings[$secretKey] = Crypt::decryptString($secret);
            }
        }

        return $settings;
    }

    /**
     * 从模型原始属性解析渠道类型；类型缺失或非法时直接失败，不静默回退。
     *
     * @param  array<string, mixed>  $attributes
     */
    private function resolveType(array $attributes): ChannelType
    {
        return ChannelType::from($attributes['type']);
    }
}
