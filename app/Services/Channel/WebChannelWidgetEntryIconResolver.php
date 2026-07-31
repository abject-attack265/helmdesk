<?php

namespace App\Services\Channel;

use App\Data\Channel\Web\ChannelWebSettingsData;
use App\Data\Channel\Web\ChannelWebWidgetEntryData;
use App\Models\Attachment;
use App\Models\Channel;

/**
 * 解析网站渠道小部件入口自定义图标的可访问地址。
 */
class WebChannelWidgetEntryIconResolver
{
    /**
     * 为单个入口配置补齐自定义图标 URL。
     */
    public function resolve(ChannelWebWidgetEntryData $entry): ChannelWebWidgetEntryData
    {
        return $this->applyUrls($entry, $this->urlsForEntries([$entry]));
    }

    /**
     * 批量收集渠道入口图标地址，供列表组装 DTO 时复用。
     *
     * @param  iterable<Channel>  $channels
     * @return array<string, string|null>
     */
    public function urlsForChannels(iterable $channels): array
    {
        $entries = [];

        foreach ($channels as $channel) {
            $settings = $channel->settings instanceof ChannelWebSettingsData
                ? $channel->settings
                : ChannelWebSettingsData::defaults();

            if ($settings->widget->entry instanceof ChannelWebWidgetEntryData) {
                $entries[] = $settings->widget->entry;
            }
        }

        return $this->urlsForEntries($entries);
    }

    /**
     * 批量查询入口图标 ID 对应的可访问地址。
     *
     * @param  iterable<ChannelWebWidgetEntryData>  $entries
     * @return array<string, string|null>
     */
    public function urlsForEntries(iterable $entries): array
    {
        $ids = [];

        foreach ($entries as $entry) {
            if (filled($entry->default_icon_id)) {
                $ids[(string) $entry->default_icon_id] = true;
            }

            if (filled($entry->active_icon_id)) {
                $ids[(string) $entry->active_icon_id] = true;
            }
        }

        if ($ids === []) {
            return [];
        }

        return Attachment::query()
            ->whereIn('id', array_keys($ids))
            ->get()
            ->mapWithKeys(static fn (Attachment $attachment): array => [
                (string) $attachment->id => $attachment->full_url,
            ])
            ->all();
    }

    /**
     * 按已解析的地址表返回带展示 URL 的入口配置副本。
     *
     * @param  array<string, string|null>  $urlsById
     */
    public function applyUrls(ChannelWebWidgetEntryData $entry, array $urlsById): ChannelWebWidgetEntryData
    {
        return $entry->withIconUrls(
            defaultIconUrl: filled($entry->default_icon_id) ? ($urlsById[(string) $entry->default_icon_id] ?? null) : null,
            activeIconUrl: filled($entry->active_icon_id) ? ($urlsById[(string) $entry->active_icon_id] ?? null) : null,
        );
    }
}
