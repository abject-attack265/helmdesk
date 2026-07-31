<!--
  应用仪表板首页，使用 ShowDashboardPagePropsData 展示近期数据、当前状态和客服接待情况。
-->
<script setup lang="ts">
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import type {
  DashboardPeriodStatsData,
  ShowDashboardPagePropsData,
} from '@/types/generated';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps<ShowDashboardPagePropsData>();
const { t } = useI18n();

const formatNumber = (value: number): string => value.toLocaleString();
const formatPercent = (value: number | null): string =>
  value === null ? '—' : `${value}%`;
const shortDate = (date: string): string => date.slice(5);

type DashboardPeriodKey = 'today' | 'yesterday' | 'prev7' | 'prev30';

const periodLabels: Record<DashboardPeriodKey, string> = {
  today: t('今天'),
  yesterday: t('昨天'),
  prev7: t('过去 7 天（不含今天）'),
  prev30: t('过去 30 天（不含今天）'),
};

function periodLabel(key: string): string {
  if (!Object.hasOwn(periodLabels, key)) {
    throw new Error(`未知的仪表板统计周期：${key}`);
  }

  return periodLabels[key as DashboardPeriodKey];
}

const periodCards = computed(() =>
  props.periods.map((period: DashboardPeriodStatsData) => ({
    key: period.key,
    label: periodLabel(period.key),
    metrics: [
      { label: t('新会话'), value: formatNumber(period.new_conversations) },
      { label: t('好评率'), value: formatPercent(period.csat_positive_rate) },
      {
        label: t('客服会话'),
        value: formatNumber(period.human_conversations),
      },
      { label: t('AI 用量'), value: formatNumber(period.tokens) },
    ],
  })),
);

const snapshotCards = computed(() => [
  {
    label: t('待处理会话'),
    value: formatNumber(props.kpis.pending_conversations),
    sub: t('等待客服接待'),
  },
  {
    label: t('在线客服'),
    value: formatNumber(props.kpis.online_agents),
    sub: t('当前在线'),
  },
  {
    label: t('知识库文档'),
    value: formatNumber(props.kpis.knowledge_documents),
    sub: t('已添加'),
  },
]);

// 所有客服的单日最高会话数用于统一单元格深浅。
const maxDailyHandled = computed(() =>
  Math.max(1, ...props.agent_performance.flatMap((row) => row.daily)),
);

// 没有会话的单元格留空，其余按当天会话数显示深浅。
function heatCellStyle(value: number): Record<string, string> {
  if (value === 0) {
    return {};
  }
  const pct = Math.round(14 + (value / maxDailyHandled.value) * 86);
  return {
    backgroundColor: `color-mix(in srgb, var(--foreground) ${pct}%, transparent)`,
  };
}

// 热力图提示跟随光标，便于查看较窄单元格的数据。
const cellTooltip = ref<{ x: number; y: number; text: string } | null>(null);

function showCellTooltip(
  event: MouseEvent,
  name: string,
  date: string,
  value: number,
): void {
  cellTooltip.value = {
    x: event.clientX,
    y: event.clientY,
    text: `${name} · ${shortDate(date)}：${formatNumber(value)}`,
  };
}

function moveCellTooltip(event: MouseEvent): void {
  if (cellTooltip.value) {
    cellTooltip.value.x = event.clientX;
    cellTooltip.value.y = event.clientY;
  }
}

function hideCellTooltip(): void {
  cellTooltip.value = null;
}
</script>

<template>
  <div class="contents">
    <Head :title="t('仪表板')" />

    <div class="space-y-6 px-4 py-6 sm:px-6">
      <HeadingSmall
        :title="t('仪表板')"
        :description="t('查看近期会话、评价、AI 用量和客服接待情况')"
      />

      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div
          v-for="card in periodCards"
          :key="card.key"
          class="rounded-lg border bg-card p-4"
        >
          <p class="text-sm font-medium">{{ card.label }}</p>
          <dl class="mt-3 space-y-2">
            <div
              v-for="metric in card.metrics"
              :key="metric.label"
              class="flex items-baseline justify-between gap-2"
            >
              <dt class="text-xs text-muted-foreground">{{ metric.label }}</dt>
              <dd class="text-sm font-semibold tabular-nums">
                {{ metric.value }}
              </dd>
            </div>
          </dl>
        </div>
      </div>

      <div class="grid gap-3 sm:grid-cols-3">
        <div
          v-for="card in snapshotCards"
          :key="card.label"
          class="rounded-lg border bg-card p-4"
        >
          <p class="text-xs text-muted-foreground">{{ card.label }}</p>
          <p class="mt-1 text-2xl font-semibold tabular-nums">
            {{ card.value }}
          </p>
          <p class="mt-1 text-xs text-muted-foreground">{{ card.sub }}</p>
        </div>
      </div>

      <div class="rounded-lg border bg-card p-4">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
          <p class="text-sm font-medium">{{ t('客服接待情况') }}</p>
          <p class="text-xs text-muted-foreground">
            {{ t('颜色越深，当天接待的会话越多') }}
          </p>
        </div>

        <div
          v-if="props.agent_performance.length === 0"
          class="py-8 text-center text-sm text-muted-foreground"
        >
          {{ t('暂无客服接待数据') }}
        </div>

        <div v-else class="overflow-x-auto">
          <div class="min-w-160">
            <div
              class="flex items-center gap-3 border-b pb-2 text-xs text-muted-foreground"
            >
              <div class="w-20 shrink-0">{{ t('客服') }}</div>
              <div
                class="flex flex-1 items-center justify-between tabular-nums"
              >
                <span>{{ shortDate(props.daily_dates[0]) }}</span>
                <span>{{ t('近 30 天') }}</span>
                <span>{{
                  shortDate(props.daily_dates[props.daily_dates.length - 1])
                }}</span>
              </div>
              <div class="w-12 shrink-0 text-right">{{ t('会话数') }}</div>
              <div class="w-14 shrink-0 text-right">{{ t('好评率') }}</div>
            </div>

            <div
              v-for="row in props.agent_performance"
              :key="row.user_id"
              class="flex items-center gap-3 border-b py-2 last:border-b-0"
            >
              <div class="w-20 shrink-0 truncate text-sm" :title="row.name">
                {{ row.name }}
              </div>
              <div
                class="grid flex-1 gap-0.5"
                :style="{
                  gridTemplateColumns: `repeat(${row.daily.length}, minmax(0, 1fr))`,
                }"
              >
                <div
                  v-for="(value, index) in row.daily"
                  :key="index"
                  class="h-5 rounded-[2px] border border-border/50"
                  :style="heatCellStyle(value)"
                  @mouseenter="
                    showCellTooltip(
                      $event,
                      row.name,
                      props.daily_dates[index],
                      value,
                    )
                  "
                  @mousemove="moveCellTooltip"
                  @mouseleave="hideCellTooltip"
                />
              </div>
              <div class="w-12 shrink-0 text-right text-sm tabular-nums">
                {{ formatNumber(row.handled) }}
              </div>
              <div class="w-14 shrink-0 text-right text-sm tabular-nums">
                {{ formatPercent(row.positive_rate) }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <div
        v-if="cellTooltip"
        class="pointer-events-none fixed z-50 rounded-md border bg-popover px-2 py-1 text-xs whitespace-nowrap text-popover-foreground tabular-nums shadow-md"
        :style="{
          left: `${cellTooltip.x + 12}px`,
          top: `${cellTooltip.y + 12}px`,
        }"
      >
        {{ cellTooltip.text }}
      </div>
    </Teleport>
  </div>
</template>
