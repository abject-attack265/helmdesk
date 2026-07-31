<?php

namespace App\Data\Conversation\ChannelContext;

use Spatie\LaravelData\Data;

/**
 * 会话渠道上下文的多态基类。
 *
 * 各渠道变体通过 channel_type 标识自身结构，供序列化与前端展示时判别。
 */
abstract class ConversationChannelContextData extends Data {}
