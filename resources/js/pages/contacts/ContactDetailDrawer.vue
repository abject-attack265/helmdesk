<!--
  联系人详情抽屉，展示基本资料、联系方式、自定义字段和相关记录。
-->
<script setup lang="ts">
import TagSelector from '@/components/common/TagSelector.vue';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { useDateTime } from '@/composables/useDateTime';
import { useI18n } from '@/composables/useI18n';
import app from '@/routes/app';
import type { ContactDetailData, TagOptionData } from '@/types/generated';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import ContactCustomAttributesPanel from './ContactCustomAttributesPanel.vue';
import ContactHeaderCard from './ContactHeaderCard.vue';
import ContactIdentityManager from './ContactIdentityManager.vue';

const props = defineProps<{
  contactId: string;
  canMerge?: boolean;
  readOnly?: boolean;
  restoreProcessing?: boolean;
  includeTrashed?: boolean;
  availableTags?: TagOptionData[];
}>();

const emit = defineEmits<{
  requestMerge: [];
  requestRestore: [];
}>();

const { t } = useI18n();
const { formatDateTime } = useDateTime();

const contactDetail = ref<ContactDetailData | null>(null);
const loading = ref(false);
const detailError = ref('');
const importanceProcessing = ref(false);

const hasCustomAttributes = computed(() => {
  return (contactDetail.value?.custom_attributes ?? []).length > 0;
});

const contactTypeLogLabel = (value: string | null | undefined): string => {
  if (value === 'contact') {
    return t('联系人');
  }

  if (value === 'visitor') {
    return t('访客');
  }

  return value ?? '-';
};

const activityLogTitle = (
  action: string,
  relatedContactName: string | null,
  payload?: Record<string, unknown> | null,
): string => {
  if (action === 'created') {
    return payload?.origin === 'resolve_identity'
      ? t('系统创建了联系人')
      : t('已创建联系人');
  }

  if (action === 'updated') {
    return t('已更新联系人');
  }

  if (action === 'important_marked') {
    return t('已标为重点客户');
  }

  if (action === 'important_unmarked') {
    return t('已取消重点客户');
  }

  if (action === 'identity_added') {
    return t('已添加联系信息');
  }

  if (action === 'identity_replaced') {
    return t('已修改联系信息');
  }

  if (action === 'identity_deleted') {
    return t('已删除联系信息');
  }

  if (action === 'deleted') {
    return t('已删除联系人');
  }

  if (action === 'restored') {
    return t('已恢复联系人');
  }

  if (action === 'custom_attributes_updated') {
    return t('已更新自定义字段');
  }

  if (action === 'merged_into_other') {
    return relatedContactName
      ? `${t('已合并到联系人')}「${relatedContactName}」`
      : t('已合并到其他联系人');
  }

  if (action === 'merged_into_current') {
    return relatedContactName
      ? `${t('已合并联系人')}「${relatedContactName}」`
      : t('已合并一个联系人');
  }

  if (action === 'tag_attached') {
    return t('已添加标签');
  }

  if (action === 'tag_detached') {
    return t('已移除标签');
  }

  return t('其他操作');
};

const activityLogDescription = (
  action: string,
  values: string[],
  payload?: Record<string, unknown> | null,
): string | null => {
  if (action === 'created') {
    return values.length > 0
      ? `${t('初始联系信息')}: ${activityLogIdentitySummary(values)}`
      : null;
  }

  if (action === 'updated') {
    const fieldChanges = payload?.field_changes as
      Record<string, { old?: string | null; new?: string | null }> | undefined;

    if (!fieldChanges) {
      return null;
    }

    const summaries = Object.entries(fieldChanges).map(([field, change]) => {
      if (field === 'name') {
        return `${t('名称')}: ${change.old ?? '-'} -> ${change.new ?? '-'}`;
      }

      if (field === 'type') {
        return `${t('类型')}: ${contactTypeLogLabel(change.old)} -> ${contactTypeLogLabel(change.new)}`;
      }

      return `${field}: ${change.old ?? '-'} -> ${change.new ?? '-'}`;
    });

    return summaries.join('；');
  }

  if (action === 'identity_added') {
    return `${t('新增联系信息')}: ${activityLogIdentitySummary(values)}`;
  }

  if (action === 'identity_replaced') {
    const oldValue =
      typeof payload?.old_value === 'string' ? payload.old_value : null;
    const newValue =
      typeof payload?.new_value === 'string' ? payload.new_value : null;

    if (oldValue || newValue) {
      return `${oldValue ?? '-'} -> ${newValue ?? '-'}`;
    }

    return `${t('修改后的联系信息')}: ${activityLogIdentitySummary(values)}`;
  }

  if (action === 'identity_deleted') {
    return `${t('删除的联系信息')}: ${activityLogIdentitySummary(values)}`;
  }

  if (action === 'deleted') {
    return t('此联系人已进入回收站');
  }

  if (action === 'restored') {
    return t('此联系人已从回收站恢复');
  }

  if (action === 'merged_into_other' || action === 'merged_into_current') {
    return `${t('合并的联系信息')}: ${activityLogIdentitySummary(values)}`;
  }

  if (action === 'custom_attributes_updated') {
    const changed = payload?.changed as
      Array<{ key: string; old: unknown; new: unknown }> | undefined;
    if (changed && changed.length > 0) {
      return changed
        .map((c) => `${c.key}: ${c.old ?? '-'} → ${c.new ?? '-'}`)
        .join('；');
    }
    return null;
  }

  if (action === 'tag_attached' || action === 'tag_detached') {
    const tagName =
      typeof payload?.tag_name === 'string' ? payload.tag_name : null;

    return tagName ? `${t('标签')}: ${tagName}` : null;
  }

  return null;
};

const activityLogActorLabel = (
  actorName: string | null | undefined,
): string => {
  return `${t('操作人')}: ${actorName || t('系统')}`;
};

const activityLogIdentitySummary = (values: string[]): string => {
  if (values.length === 0) {
    return t('未记录联系信息');
  }

  if (values.length <= 3) {
    return values.join('、');
  }

  return `${values.slice(0, 3).join('、')} ${t('等')} ${values.length} ${t('项')}`;
};

const fetchDetail = async (id: string, silent = false) => {
  if (!silent) {
    loading.value = true;
  }
  detailError.value = '';
  try {
    const response = await fetch(
      app.contacts.show.url(
        {
          id,
        },
        {
          query: {
            include_trashed: props.includeTrashed ? 1 : undefined,
          },
        },
      ),
      {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      },
    );
    if (!response.ok) {
      throw new Error(t('联系人详情加载失败'));
    }
    contactDetail.value = await response.json();
  } catch (error) {
    contactDetail.value = null;
    detailError.value =
      error instanceof Error ? error.message : t('联系人详情加载失败');
    throw error;
  } finally {
    if (!silent) {
      loading.value = false;
    }
  }
};

watch(
  () => props.contactId,
  (newId) => {
    if (newId) {
      fetchDetail(newId);
    }
  },
);

onMounted(() => {
  if (props.contactId) {
    fetchDetail(props.contactId);
  }
});

const tagProcessing = ref(false);

const selectedTagIds = computed(() =>
  (contactDetail.value?.tags ?? []).map((t) => t.id),
);

const reloadContactList = async (): Promise<void> => {
  await new Promise<void>((resolve) => {
    router.reload({
      only: ['contact_list', 'contact_list_pagination'],
      onFinish: () => resolve(),
    });
  });
};

const toggleImportance = async (): Promise<void> => {
  if (props.readOnly || !contactDetail.value || importanceProcessing.value) {
    return;
  }

  importanceProcessing.value = true;
  try {
    await axios.put(
      app.contacts.importance.update.url({
        id: props.contactId,
      }),
      { is_important: !contactDetail.value.is_important },
    );

    await Promise.all([
      fetchDetail(props.contactId, true),
      reloadContactList(),
    ]);
  } finally {
    importanceProcessing.value = false;
  }
};

const handleAttachTag = async (tagId: string) => {
  if (tagProcessing.value) {
    return;
  }

  tagProcessing.value = true;
  try {
    await axios.post(
      app.contacts.tags.attach.url({
        id: props.contactId,
      }),
      { tag_id: tagId },
    );
    await Promise.all([
      fetchDetail(props.contactId, true),
      reloadContactList(),
    ]);
  } finally {
    tagProcessing.value = false;
  }
};

const handleDetachTag = async (tagId: string) => {
  if (tagProcessing.value) {
    return;
  }

  tagProcessing.value = true;
  try {
    await axios.delete(
      app.contacts.tags.detach.url({
        id: props.contactId,
        tagId,
      }),
    );
    await Promise.all([
      fetchDetail(props.contactId, true),
      reloadContactList(),
    ]);
  } finally {
    tagProcessing.value = false;
  }
};

const formatAiContext = (
  ctx: Record<string, unknown> | null,
): { key: string; value: string }[] => {
  if (!ctx) {
    return [];
  }
  return Object.entries(ctx)
    .filter(([key]) => !key.startsWith('_'))
    .map(([key, value]) => ({
      key,
      value: typeof value === 'string' ? value : JSON.stringify(value),
    }));
};
</script>

<template>
  <div class="flex h-full min-h-0 flex-col bg-background">
    <div class="border-b p-4 pr-12">
      <h3 class="font-semibold">{{ t('联系人详情') }}</h3>
    </div>

    <div v-if="loading" class="space-y-4 p-4">
      <div class="flex items-center gap-3">
        <div class="h-12 w-12 animate-pulse rounded-full bg-muted" />
        <div class="space-y-2">
          <div class="h-4 w-24 animate-pulse rounded bg-muted" />
          <div class="h-3 w-32 animate-pulse rounded bg-muted" />
        </div>
      </div>
      <div class="h-20 animate-pulse rounded bg-muted" />
      <div class="h-20 animate-pulse rounded bg-muted" />
    </div>

    <div v-else-if="detailError" class="p-4 text-sm text-destructive">
      {{ detailError }}
    </div>

    <div v-else-if="contactDetail" class="min-h-0 flex-1 overflow-y-auto">
      <div class="space-y-4 p-4">
        <ContactHeaderCard
          :contact-id="contactId"
          :contact-detail="contactDetail"
          :can-merge="canMerge"
          :read-only="readOnly"
          :restore-processing="restoreProcessing"
          :importance-processing="importanceProcessing"
          @request-merge="emit('requestMerge')"
          @request-restore="emit('requestRestore')"
          @request-toggle-importance="toggleImportance"
          @request-refresh="fetchDetail(contactId, true)"
        />

        <Separator />

        <ContactIdentityManager
          :contact-id="contactId"
          :contact-detail="contactDetail"
          :read-only="readOnly"
          @request-refresh="fetchDetail(contactId, true)"
        />

        <div v-if="!readOnly && availableTags && availableTags.length > 0">
          <Separator class="mb-4" />
          <h5 class="mb-3 text-sm font-semibold">{{ t('标签') }}</h5>
          <TagSelector
            :options="availableTags"
            :selected-tag-ids="selectedTagIds"
            :disabled="tagProcessing"
            @attach="handleAttachTag"
            @detach="handleDetachTag"
          />
          <div
            v-if="selectedTagIds.length === 0"
            class="mt-2 text-sm text-muted-foreground"
          >
            {{ t('暂无标签') }}
          </div>
        </div>

        <div
          v-else-if="
            readOnly && contactDetail.tags && contactDetail.tags.length > 0
          "
        >
          <Separator class="mb-4" />
          <h5 class="mb-3 text-sm font-semibold">{{ t('标签') }}</h5>
          <div class="flex flex-wrap gap-1.5">
            <Badge
              v-for="tag in contactDetail.tags"
              :key="tag.id"
              class="flex items-center gap-1.5 border bg-background text-foreground shadow-sm"
            >
              <span
                class="h-2 w-2 shrink-0 rounded-full"
                :style="{ backgroundColor: tag.color ?? '#94a3b8' }"
              />
              {{ tag.name }}
            </Badge>
          </div>
        </div>

        <div v-if="contactDetail.ai_context">
          <h5 class="mb-3 text-sm font-semibold">
            {{ t('客户信息摘要') }}
          </h5>
          <div class="space-y-2">
            <div
              v-for="item in formatAiContext(contactDetail.ai_context)"
              :key="item.key"
              class="rounded-md border px-3 py-2 text-sm"
            >
              <span class="font-medium text-foreground">{{ item.key }}</span>
              <span class="ml-2 text-muted-foreground">{{ item.value }}</span>
            </div>
            <div
              v-if="formatAiContext(contactDetail.ai_context).length === 0"
              class="py-4 text-center text-sm text-muted-foreground"
            >
              {{ t('暂无客户信息摘要') }}
            </div>
          </div>
        </div>

        <div
          v-if="
            contactDetail.locale ||
            contactDetail.timezone ||
            contactDetail.country ||
            contactDetail.city
          "
        >
          <Separator class="mb-4" />
          <h5 class="mb-3 text-sm font-semibold">{{ t('地区与语言') }}</h5>
          <div class="space-y-1 text-sm">
            <div v-if="contactDetail.locale" class="flex justify-between">
              <span class="text-muted-foreground">{{ t('语言') }}</span>
              <span>{{ contactDetail.locale }}</span>
            </div>
            <div v-if="contactDetail.timezone" class="flex justify-between">
              <span class="text-muted-foreground">{{ t('时区') }}</span>
              <span>{{ contactDetail.timezone }}</span>
            </div>
            <div v-if="contactDetail.country" class="flex justify-between">
              <span class="text-muted-foreground">{{ t('国家') }}</span>
              <span>{{ contactDetail.country }}</span>
            </div>
            <div v-if="contactDetail.city" class="flex justify-between">
              <span class="text-muted-foreground">{{ t('城市') }}</span>
              <span>{{ contactDetail.city }}</span>
            </div>
          </div>
        </div>

        <template v-if="hasCustomAttributes">
          <Separator />
          <ContactCustomAttributesPanel
            :contact-id="contactId"
            :contact-detail="contactDetail"
            :read-only="readOnly"
            @request-refresh="fetchDetail(contactId, true)"
          />
        </template>

        <Separator />

        <div>
          <h5 class="mb-3 text-sm font-semibold">{{ t('操作记录') }}</h5>
          <div class="space-y-2">
            <div
              v-for="activityLog in contactDetail.activity_logs"
              :key="activityLog.id"
              class="rounded-md border px-3 py-3 text-sm"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="space-y-1">
                  <div class="font-medium text-foreground">
                    {{
                      activityLogTitle(
                        activityLog.action,
                        activityLog.related_contact_name,
                        activityLog.payload,
                      )
                    }}
                  </div>
                  <div
                    v-if="
                      activityLogDescription(
                        activityLog.action,
                        activityLog.identity_values,
                        activityLog.payload,
                      )
                    "
                    class="text-muted-foreground"
                  >
                    {{
                      activityLogDescription(
                        activityLog.action,
                        activityLog.identity_values,
                        activityLog.payload,
                      )
                    }}
                  </div>
                  <div class="text-xs text-muted-foreground">
                    {{ activityLogActorLabel(activityLog.actor_name) }}
                  </div>
                </div>
                <div class="shrink-0 text-xs text-muted-foreground">
                  {{ formatDateTime(activityLog.created_at) }}
                </div>
              </div>
            </div>
            <div
              v-if="contactDetail.activity_logs.length === 0"
              class="py-4 text-center text-sm text-muted-foreground"
            >
              {{ t('暂无操作记录') }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
