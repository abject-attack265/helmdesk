<!--
  经验提炼任务会话页，消费 ShowExperienceExtractionConversationsPagePropsData。
  展示任务关联会话并支持查看原始对话。
-->
<script setup lang="ts">
import KnowledgeBase from '@/actions/App/Actions/KnowledgeBase';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import ConversationDetailSheet from '@/components/conversation/ConversationDetailSheet.vue';
import { useDateTime } from '@/composables/useDateTime';
import { useI18n } from '@/composables/useI18n';
import { useKnowledgeBaseExplorerNavigation } from '@/composables/useKnowledgeBaseExplorerNavigation';
import KnowledgeBasesLayout from '@/layouts/KnowledgeBasesLayout.vue';
import { appContentLayout } from '@/layouts/pageLayouts';
import ConversationRow from '@/pages/experiences/ConversationRow.vue';
import KnowledgeBaseExplorerSidebar from '@/pages/knowledgeBase/KnowledgeBaseExplorerSidebar.vue';
import experienceExtraction from '@/routes/app/manage/experience-extraction';
import type { ShowExperienceExtractionConversationsPagePropsData } from '@/types/generated';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineOptions({ layout: appContentLayout });

const props = defineProps<ShowExperienceExtractionConversationsPagePropsData>();

const { t } = useI18n();
const { formatDateTime } = useDateTime();
const explorerNavigation = useKnowledgeBaseExplorerNavigation();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  {
    title: t('知识库'),
    href: KnowledgeBase.ListKnowledgeBasesAction.url(),
  },
  {
    title: props.extraction.knowledge_base.name,
    href: KnowledgeBase.ListKnowledgeBasesAction.url({
      query: { kb: props.extraction.knowledge_base.id },
    }),
  },
  {
    title: t('经验提炼'),
    href: experienceExtraction.index.url({
      knowledgeBase: props.extraction.knowledge_base.id,
    }),
  },
  { title: t('会话列表') },
]);

const viewingConversationId = ref<string | null>(null);
</script>

<template>
  <div class="contents">
    <Head :title="t('经验提炼-会话列表')" />

    <KnowledgeBasesLayout>
      <template #sidebar="{ closeMobileExplorer }">
        <KnowledgeBaseExplorerSidebar
          :knowledge-bases="props.sidebar.knowledge_base_list"
          :category-options="props.sidebar.category_options"
          :active-kb-id="props.extraction.knowledge_base.id"
          @navigate="closeMobileExplorer"
          @select-kb="explorerNavigation.openKb"
          @select-group="explorerNavigation.openGroup"
          @create="explorerNavigation.openCreateKb"
          @edit="explorerNavigation.openEditKb"
        />
      </template>

      <div class="space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />

        <HeadingSmall
          :title="t('经验提炼-会话列表')"
          :description="`${formatDateTime(extraction.created_at)} · ${extraction.status_label} · ${t('会话 {count} 个', { count: conversations.length })}`"
        />

        <div class="rounded-lg border">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="border-b bg-muted/30 text-muted-foreground">
                <tr class="text-left">
                  <th class="px-4 py-3">{{ t('主题') }}</th>
                  <th class="px-4 py-3">{{ t('访客') }}</th>
                  <th class="px-4 py-3">{{ t('最后消息') }}</th>
                  <th class="px-4 py-3">{{ t('关闭时间') }}</th>
                  <th class="px-4 py-3">{{ t('人工消息') }}</th>
                  <th class="px-4 py-3 text-right">{{ t('操作') }}</th>
                </tr>
              </thead>
              <tbody>
                <ConversationRow
                  v-for="conversation in conversations"
                  :key="conversation.id"
                  :conversation="conversation"
                  @view="viewingConversationId = $event"
                />
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </KnowledgeBasesLayout>

    <ConversationDetailSheet v-model="viewingConversationId" />
  </div>
</template>
