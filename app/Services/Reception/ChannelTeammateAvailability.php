<?php

namespace App\Services\Reception;

use App\Data\Reception\Config\ReceptionBusinessHoursData;
use App\Data\Reception\Config\ReceptionBusinessHoursDayData;
use App\Data\Reception\HumanServiceStatusData;
use App\Enums\HumanServiceUnavailableReason;
use App\Enums\UserOnlineStatus;
use App\Models\Channel;
use App\Models\User;
use App\Services\LocalePreference;
use Carbon\Carbon;

/**
 * 判断渠道当前「人工是否可接待」。
 *
 * 可达条件：应用内至少有一名客服处于 Online 状态，且当前时刻在接待方案配置的人工服务时间内。
 * 未配置营业时间时，只检查人工接待人员状态。
 *
 * 注意：此服务只判断客服接待能力，不影响 AI 接待，AI 始终可用。
 */
class ChannelTeammateAvailability
{
    /**
     * 注入接待方案策略解析器。
     */
    public function __construct(
        private readonly ReceptionPlanStrategyResolver $strategyResolver,
    ) {}

    /**
     * 判断渠道当前是否有人工可接待。
     *
     * 未配置营业时间时只跳过时间窗口限制，仍需至少一名可接待人员。
     */
    public function isTeammateAvailable(Channel $channel): bool
    {
        return $this->serviceStatus($channel)->human_available;
    }

    /**
     * 返回当前渠道人工客服运行时状态，供路由决策与 AI 系统提示词使用。
     */
    public function serviceStatus(Channel $channel, ?Carbon $at = null, ?string $locale = null): HumanServiceStatusData
    {
        $locale = $this->normalizeLocale($locale);
        $businessHours = $this->businessHours($channel);
        $timezone = $businessHours?->timezone ?? 'UTC';
        $now = ($at ?? Carbon::now())->copy()->setTimezone($timezone);
        $withinBusinessHours = $businessHours === null || $businessHours->isWithinSchedule($now);
        $hasOnlineTeammate = $this->hasOnlineTeammate($channel);
        $humanAvailable = $withinBusinessHours && $hasOnlineTeammate;

        $reason = null;
        if (! $withinBusinessHours) {
            $reason = HumanServiceUnavailableReason::OutsideBusinessHours;
        } elseif (! $hasOnlineTeammate) {
            $reason = HumanServiceUnavailableReason::NoOnlineTeammate;
        }

        return new HumanServiceStatusData(
            timezone: $timezone,
            now_local: $now->format('Y-m-d H:i'),
            business_hours_enabled: $businessHours !== null,
            within_business_hours: $withinBusinessHours,
            has_online_teammate: $hasOnlineTeammate,
            human_available: $humanAvailable,
            unavailable_reason: $humanAvailable ? null : $reason,
            business_hours_summary: $this->businessHoursSummary($businessHours, $locale),
            next_available_at: $businessHours === null ? null : $this->nextAvailableAt($businessHours, $now),
        );
    }

    /**
     * 按当前人工服务状态返回 handoff_to_human 工具应该给模型的提示文案。
     */
    public function handoffNotice(Channel $channel, HumanServiceStatusData $status): string
    {
        $strategy = $this->strategyResolver->forChannel($channel);

        if ($status->human_available) {
            return $strategy->handoff_available_notice;
        }

        if ($status->unavailable_reason === HumanServiceUnavailableReason::OutsideBusinessHours) {
            return $strategy->business_hours->outside_hours_notice;
        }

        return $strategy->handoff_no_teammate_notice;
    }

    /**
     * 生成可注入 AI 系统提示词的人工服务状态说明。
     */
    public function runtimeInstruction(HumanServiceStatusData $status, ?string $locale = null): string
    {
        $locale = $this->normalizeLocale($locale);
        $available = $status->human_available
            ? __('reception.human_service_runtime.yes', [], $locale)
            : __('reception.human_service_runtime.no', [], $locale);
        $withinHours = $status->within_business_hours
            ? __('reception.human_service_runtime.yes', [], $locale)
            : __('reception.human_service_runtime.no', [], $locale);
        $online = $status->has_online_teammate
            ? __('reception.human_service_runtime.yes', [], $locale)
            : __('reception.human_service_runtime.no', [], $locale);

        $lines = [
            __('reception.human_service_runtime.heading', [], $locale),
            __('reception.human_service_runtime.current_local_time', ['time' => $status->now_local, 'timezone' => $status->timezone], $locale),
            __('reception.human_service_runtime.business_hours', ['summary' => $status->business_hours_summary], $locale),
            __('reception.human_service_runtime.within_business_hours', ['value' => $withinHours], $locale),
            __('reception.human_service_runtime.has_online_teammate', ['value' => $online], $locale),
            __('reception.human_service_runtime.human_available', ['value' => $available], $locale),
            __('reception.human_service_runtime.answer_scope', [], $locale),
            __('reception.human_service_runtime.call_handoff_tool', [], $locale),
            __('reception.human_service_runtime.handoff_terminal', [], $locale),
        ];

        if (filled($status->next_available_at)) {
            $lines[] = __('reception.human_service_runtime.next_available_at', ['time' => $status->next_available_at], $locale);
        }

        return implode("\n", $lines);
    }

    /**
     * 取接待方案人工服务时间配置，未配置时返回 null。
     */
    private function businessHours(Channel $channel): ?ReceptionBusinessHoursData
    {
        return $this->strategyResolver->forChannel($channel)->business_hours;
    }

    /**
     * 判断应用内是否存在至少一名可接待人员。
     */
    private function hasOnlineTeammate(Channel $channel): bool
    {
        return User::query()
            ->whereHas('membership', fn ($query) => $query->where('online_status', UserOnlineStatus::Online->value))
            ->exists();
    }

    /**
     * 将周营业时间格式化为模型可引用的短文本。
     */
    private function businessHoursSummary(?ReceptionBusinessHoursData $businessHours, string $locale): string
    {
        if ($businessHours === null) {
            return __('reception.human_service_runtime.business_hours_not_set', [], $locale);
        }

        $days = [];
        foreach ($this->scheduleDays($businessHours) as $day) {
            $days[] = $this->dayLabel($day->day, $locale).' '.($day->enabled ? "{$day->open}-{$day->close}" : __('reception.human_service_runtime.closed', [], $locale));
        }

        return $days === []
            ? __('reception.human_service_runtime.business_hours_empty', [], $locale)
            : implode(__('reception.human_service_runtime.summary_separator', [], $locale), $days);
    }

    /**
     * 计算下一次营业开始时间，按营业时间时区返回本地文本。
     */
    private function nextAvailableAt(ReceptionBusinessHoursData $businessHours, Carbon $now): ?string
    {
        foreach (range(0, 7) as $offset) {
            $date = $now->copy()->startOfDay()->addDays($offset);
            $isoDay = (int) $date->isoFormat('E');

            foreach ($this->scheduleDays($businessHours) as $day) {
                if ($day->day !== $isoDay || ! $day->enabled) {
                    continue;
                }

                [$hour, $minute] = array_map('intval', explode(':', $day->open));
                $candidate = $date->copy()->setTime($hour, $minute);
                if ($candidate->gt($now)) {
                    return $candidate->format('Y-m-d H:i');
                }
            }
        }

        return null;
    }

    /**
     * 将营业时间配置归一为 Day Data 列表并按 ISO 周次升序排列。
     *
     * @return list<ReceptionBusinessHoursDayData>
     */
    private function scheduleDays(ReceptionBusinessHoursData $businessHours): array
    {
        $days = array_map(
            static fn (mixed $day): ReceptionBusinessHoursDayData => $day instanceof ReceptionBusinessHoursDayData
                ? $day
                : ReceptionBusinessHoursDayData::from($day),
            $businessHours->schedule,
        );

        usort($days, static fn (ReceptionBusinessHoursDayData $a, ReceptionBusinessHoursDayData $b): int => $a->day <=> $b->day);

        return $days;
    }

    /**
     * 返回 ISO 周次中文短标签。
     */
    private function dayLabel(int $day, string $locale): string
    {
        return match ($day) {
            1 => __('reception.human_service_runtime.weekdays.monday', [], $locale),
            2 => __('reception.human_service_runtime.weekdays.tuesday', [], $locale),
            3 => __('reception.human_service_runtime.weekdays.wednesday', [], $locale),
            4 => __('reception.human_service_runtime.weekdays.thursday', [], $locale),
            5 => __('reception.human_service_runtime.weekdays.friday', [], $locale),
            6 => __('reception.human_service_runtime.weekdays.saturday', [], $locale),
            7 => __('reception.human_service_runtime.weekdays.sunday', [], $locale),
        };
    }

    /**
     * 将外部语言标识归一到 Laravel 语言目录，未传时使用访客端默认语言。
     */
    private function normalizeLocale(?string $locale): string
    {
        return LocalePreference::normalizeLaravel($locale ?? LocalePreference::DEFAULT_LARAVEL_LOCALE);
    }
}
