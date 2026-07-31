<?php

namespace App\Enums;

/**
 * 消息翻译执行结果，用于区分真实失败和无需处理的跳过状态。
 */
enum MessageTranslationOutcome: string
{
    case Translated = 'translated';
    case Skipped = 'skipped';
    case Failed = 'failed';

    /**
     * 判断是否产生了消息内容或翻译元数据更新。
     */
    public function isTranslated(): bool
    {
        return $this === self::Translated;
    }

    /**
     * 判断是否由翻译供应商或配置异常导致失败。
     */
    public function isFailed(): bool
    {
        return $this === self::Failed;
    }
}
