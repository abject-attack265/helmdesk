<?php

namespace App\Casts;

use App\Casts\Concerns\DecodesJsonArrays;
use App\Data\Conversation\ChannelContext\ConversationChannelContextData;
use App\Data\Conversation\ChannelContext\TelegramConversationChannelContextData;
use App\Data\Conversation\ChannelContext\WebConversationChannelContextData;
use App\Data\Conversation\ChannelContext\WechatOfficialAccountConversationChannelContextData;
use App\Enums\ChannelType;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Spatie\LaravelData\Data;

/**
 * 会话 channel_context JSON 列的多态 cast。
 *
 * 会话本身没有渠道类型字段，因此判别信息内嵌在 JSON 的 channel_type 中（自描述），
 * 按它选择对应的上下文 Data 反序列化。空值代表"尚未采集"，是合法状态返回 null；
 * JSON 存在但判别字段缺失/非法属于数据损坏，直接失败而非静默回退。
 *
 * @implements CastsAttributes<ConversationChannelContextData|null, ConversationChannelContextData|null>
 */
class ConversationChannelContextCast implements CastsAttributes
{
    use DecodesJsonArrays;

    /**
     * 按 channel_type 把 JSON 列反序列化为对应渠道的上下文 Data。
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?ConversationChannelContextData
    {
        $decoded = $this->decodeJsonArray($value);

        if ($decoded === []) {
            return null;
        }

        return match ($this->resolveType($decoded)) {
            ChannelType::Web => WebConversationChannelContextData::from($decoded),
            ChannelType::Telegram => TelegramConversationChannelContextData::from($decoded),
            ChannelType::WechatOfficialAccount => WechatOfficialAccountConversationChannelContextData::from($decoded),
        };
    }

    /**
     * 把上下文 Data / 数组序列化为 JSON 字符串写回数据库。
     *
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        if ($value instanceof Data) {
            return [$key => $value->toJson()];
        }

        if (is_array($value)) {
            return [$key => json_encode($value, JSON_THROW_ON_ERROR)];
        }

        throw new InvalidArgumentException('channel_context 只接受 Data、数组或 null。');
    }

    /**
     * 从已解码的 JSON 中解析渠道类型；判别字段缺失或非法时直接失败。
     *
     * @param  array<string, mixed>  $decoded
     */
    private function resolveType(array $decoded): ChannelType
    {
        $type = $decoded['channel_type'] ?? null;

        if (! is_string($type) || $type === '') {
            throw new InvalidArgumentException('channel_context 缺少 channel_type 判别字段。');
        }

        return ChannelType::from($type);
    }
}
