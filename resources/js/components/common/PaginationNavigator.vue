<script setup lang="ts">
import { buttonVariants } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { cn } from '@/lib/utils';
import type { SimplePaginationData } from '@/types/generated';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

type PaginationItem =
  | {
      type: 'page';
      page: number;
    }
  | {
      type: 'ellipsis';
      key: string;
    };

const props = defineProps<{
  pagination: SimplePaginationData;
  pageUrl: (page: number) => string;
  /** 翻页时保留页面组件本地状态（如跨页勾选），默认按普通访问重新挂载。 */
  preserveState?: boolean;
}>();

const { t } = useI18n();

const hasPrev = computed(() => props.pagination.current_page > 1);
const hasNext = computed(
  () => props.pagination.current_page < props.pagination.last_page,
);

const pageItems = computed<PaginationItem[]>(() => {
  const currentPage = props.pagination.current_page;
  const lastPage = props.pagination.last_page;

  if (lastPage <= 7) {
    return Array.from({ length: lastPage }, (_, index) => ({
      type: 'page',
      page: index + 1,
    }));
  }

  const visiblePages = new Set<number>([1, lastPage, currentPage]);

  for (const page of [currentPage - 1, currentPage + 1]) {
    if (page > 1 && page < lastPage) {
      visiblePages.add(page);
    }
  }

  if (currentPage <= 3) {
    visiblePages.add(2);
    visiblePages.add(3);
    visiblePages.add(4);
  }

  if (currentPage >= lastPage - 2) {
    visiblePages.add(lastPage - 1);
    visiblePages.add(lastPage - 2);
    visiblePages.add(lastPage - 3);
  }

  const sortedPages = [...visiblePages]
    .filter((page) => page >= 1 && page <= lastPage)
    .sort((left, right) => left - right);

  const items: PaginationItem[] = [];

  sortedPages.forEach((page, index) => {
    const previousPage = sortedPages[index - 1];

    if (previousPage && page - previousPage > 1) {
      items.push({
        type: 'ellipsis',
        key: `ellipsis-${previousPage}-${page}`,
      });
    }

    items.push({
      type: 'page',
      page,
    });
  });

  return items;
});
</script>

<template>
  <div
    v-if="props.pagination.last_page > 1"
    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
  >
    <div class="text-sm text-muted-foreground">
      {{ t('第') }} {{ props.pagination.current_page }} /
      {{ props.pagination.last_page }} {{ t('页，共') }}
      {{ props.pagination.total }} {{ t('条') }}
    </div>

    <div class="flex flex-wrap items-center justify-end gap-2">
      <Link
        v-if="hasPrev"
        :href="props.pageUrl(props.pagination.current_page - 1)"
        :preserve-state="props.preserveState"
        :class="buttonVariants({ variant: 'outline', size: 'sm' })"
      >
        {{ t('上一页') }}
      </Link>

      <template
        v-for="item in pageItems"
        :key="item.type === 'page' ? item.page : item.key"
      >
        <span
          v-if="item.type === 'ellipsis'"
          class="px-1 text-sm text-muted-foreground"
        >
          ...
        </span>

        <component
          :is="item.page === props.pagination.current_page ? 'button' : Link"
          v-else
          :href="
            item.page === props.pagination.current_page
              ? null
              : props.pageUrl(item.page)
          "
          :preserve-state="
            item.page === props.pagination.current_page
              ? undefined
              : props.preserveState
          "
          :class="
            cn(
              buttonVariants({
                variant:
                  item.page === props.pagination.current_page
                    ? 'default'
                    : 'outline',
                size: 'sm',
              }),
              'min-w-8 px-3',
            )
          "
          :aria-current="
            item.page === props.pagination.current_page ? 'page' : undefined
          "
          :disabled="item.page === props.pagination.current_page"
          :tabindex="
            item.page === props.pagination.current_page ? -1 : undefined
          "
          type="button"
        >
          {{ item.page }}
        </component>
      </template>

      <Link
        v-if="hasNext"
        :href="props.pageUrl(props.pagination.current_page + 1)"
        :preserve-state="props.preserveState"
        :class="buttonVariants({ variant: 'outline', size: 'sm' })"
      >
        {{ t('下一页') }}
      </Link>
    </div>
  </div>
</template>
