<!--
  知识库管理页，使用 ShowKnowledgeBaseListPagePropsData 管理文档、问答和分组，并测试查找结果。
-->
<script setup lang="ts">
import KnowledgeBase from '@/actions/App/Actions/KnowledgeBase';
import ConfirmDeleteDialog from '@/components/common/ConfirmDeleteDialog.vue';
import FilterPopover from '@/components/common/FilterPopover.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import PaginationNavigator from '@/components/common/PaginationNavigator.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/composables/useI18n';
import KnowledgeBasesLayout from '@/layouts/KnowledgeBasesLayout.vue';
import { appContentLayout } from '@/layouts/pageLayouts';
import KnowledgeBaseExplorerSidebar from '@/pages/knowledgeBase/KnowledgeBaseExplorerSidebar.vue';
import KnowledgeBaseFormPanel from '@/pages/knowledgeBase/KnowledgeBaseFormPanel.vue';
import KnowledgeDocumentFilterBasicPanel from '@/pages/knowledgeBase/KnowledgeDocumentFilterBasicPanel.vue';
import KnowledgeDocumentTable from '@/pages/knowledgeBase/KnowledgeDocumentTable.vue';
import KnowledgeDocumentUploadPanel from '@/pages/knowledgeBase/KnowledgeDocumentUploadPanel.vue';
import KnowledgeManualDocumentPanel from '@/pages/knowledgeBase/KnowledgeManualDocumentPanel.vue';
import KnowledgeQaDocumentPanel from '@/pages/knowledgeBase/KnowledgeQaDocumentPanel.vue';
import KnowledgeQaEntryTable from '@/pages/knowledgeBase/KnowledgeQaEntryTable.vue';
import KnowledgeRecallTestPanel from '@/pages/knowledgeBase/KnowledgeRecallTestPanel.vue';
import MoveToGroupDialog from '@/pages/knowledgeBase/MoveToGroupDialog.vue';
import experienceExtraction from '@/routes/app/manage/experience-extraction';
import type {
  KnowledgeBaseCategory,
  KnowledgeBaseData,
  ListKnowledgeDocumentItemData,
  ListKnowledgeQaEntryItemData,
  ShowKnowledgeBaseListPagePropsData,
} from '@/types/generated';
import { router, useForm } from '@inertiajs/vue3';
import { ChevronDown, Library, Search } from '@lucide/vue';
import {
  computed,
  defineAsyncComponent,
  onBeforeUnmount,
  onMounted,
  ref,
  watch,
} from 'vue';

const KnowledgeDocumentPreviewDialog = defineAsyncComponent(
  () => import('./KnowledgeDocumentPreviewDialog.vue'),
);

defineOptions({ layout: appContentLayout });

const props = defineProps<ShowKnowledgeBaseListPagePropsData>();
const { t } = useI18n();

const selectedKbId = ref<string | null>(
  props.selected_knowledge_base?.id ?? null,
);
const selectedGroupId = ref<string | null>(props.selected_group_id ?? null);
const selectedStatus = ref<string>(props.current_status ?? 'all');
const searchInput = ref(props.search ?? '');
const filterPanelOpen = ref(false);
const groupDialogOpen = ref(false);
type RightPage =
  | 'knowledge_base'
  | 'knowledge_base_form'
  | 'manual_document_form'
  | 'qa_entry_form'
  | 'recall_test';
type RightPanelQueryValue =
  | 'kb-create'
  | 'kb-edit'
  | 'manual-create'
  | 'manual-edit'
  | 'qa-create'
  | 'qa-edit'
  | 'recall';
interface FormDiscardGuard {
  confirmDiscardIfDirty: () => boolean;
  hasUnsavedChanges: () => boolean;
}

const activeRightPage = ref<RightPage>('knowledge_base');
const knowledgeBaseFormPanelRef = ref<FormDiscardGuard | null>(null);
const manualDocumentPanelRef = ref<FormDiscardGuard | null>(null);
const qaDocumentPanelRef = ref<FormDiscardGuard | null>(null);
const panelQueryParam = 'panel';
const categoryQueryParam = 'category';
const documentQueryParam = 'document';
const entryQueryParam = 'entry';
const creatableKnowledgeBaseCategories = new Set<KnowledgeBaseCategory>([
  'standard',
  'qa',
]);
const DOCUMENT_PROCESSING_STATUSES = new Set([
  'pending',
  'parsing',
  'parsed',
  'indexing',
]);
const QA_PROCESSING_STATUSES = new Set(['pending', 'indexing']);
const LIST_POLL_INTERVAL_MS = 3000;
let searchTimeout: ReturnType<typeof setTimeout> | null = null;
let listPollTimeout: ReturnType<typeof setTimeout> | null = null;
let listPollRequestActive = false;
let listPollingMounted = false;
let allowNextGetNavigation = false;
let removeBeforeListener: (() => void) | null = null;

watch(
  () => props.selected_knowledge_base?.id ?? null,
  (id) => {
    selectedKbId.value = id;
  },
);

watch(
  () => props.selected_group_id ?? null,
  (id) => {
    selectedGroupId.value = id;
  },
);

watch(
  () => props.current_status ?? 'all',
  (status) => {
    selectedStatus.value = status;
  },
);

watch(
  () => props.search ?? '',
  (search) => {
    if (searchTimeout) {
      clearTimeout(searchTimeout);
      searchTimeout = null;
    }
    searchInput.value = search;
  },
);

const selectedKb = computed(() => props.selected_knowledge_base);

const selectedKbIsQa = computed(() => selectedKb.value?.category === 'qa');

const statusOptions = computed(() =>
  selectedKbIsQa.value
    ? props.qa_status_options
    : props.document_status_options,
);

const currentListPagination = computed(() =>
  selectedKbIsQa.value
    ? props.qa_entry_list_pagination
    : props.document_list_pagination,
);

const hasProcessingListItem = computed(() =>
  selectedKbIsQa.value
    ? props.qa_entry_list.some((entry) =>
        QA_PROCESSING_STATUSES.has(entry.status),
      )
    : props.document_list.some((document) =>
        DOCUMENT_PROCESSING_STATUSES.has(document.status),
      ),
);

const hasActiveListQuery = computed(
  () => selectedStatus.value !== 'all' || searchInput.value.trim().length > 0,
);

const searchPlaceholder = computed(() =>
  selectedKbIsQa.value ? t('搜索问题或答案') : t('搜索文件名'),
);

const activeBasicFilterCount = computed(() =>
  selectedStatus.value !== 'all' ? 1 : 0,
);

const totalActiveFilterCount = computed(() => activeBasicFilterCount.value);

const selectedKbGroupOptions = computed(() => {
  const groups = selectedKb.value?.document_groups ?? [];
  const options: Array<{ id: string; label: string }> = [];

  for (const group of groups) {
    options.push({ id: group.id, label: group.name });

    for (const child of group.children ?? []) {
      options.push({ id: child.id, label: `${group.name} / ${child.name}` });
    }
  }

  return options;
});

const groupLabelById = computed(() => {
  return new Map(
    selectedKbGroupOptions.value.map((group) => [group.id, group.label]),
  );
});

const overflowTooltipKey = ref<string | null>(null);

function setOverflowTooltip(event: MouseEvent, key: string): void {
  const element = event.currentTarget as HTMLElement;
  overflowTooltipKey.value =
    element.scrollWidth > element.clientWidth ? key : null;
}

function clearOverflowTooltip(key: string): void {
  if (overflowTooltipKey.value === key) {
    overflowTooltipKey.value = null;
  }
}

function documentGroupLabel(groupId: string | null): string {
  if (!groupId) {
    return t('分组已不存在');
  }

  return groupLabelById.value.get(groupId) ?? t('分组已不存在');
}

function canMoveToAnotherGroup(groupId: string | null): boolean {
  return selectedKbGroupOptions.value.some((group) => group.id !== groupId);
}

function documentTypeLabel(doc: ListKnowledgeDocumentItemData): string {
  if (doc.source_type === 'manual') {
    return t('直接填写');
  }

  switch (doc.extension?.toLowerCase()) {
    case 'md':
    case 'markdown':
      return 'Markdown';
    case 'txt':
      return t('纯文本');
    case 'docx':
      return 'Word';
    case 'pdf':
      return 'PDF';
    case 'html':
    case 'htm':
      return 'HTML';
    default:
      return t('文件');
  }
}

function normalizeKnowledgeBaseCategory(
  category: string | null,
): KnowledgeBaseCategory {
  const value = category as KnowledgeBaseCategory | null;

  return value && creatableKnowledgeBaseCategories.has(value)
    ? value
    : 'standard';
}

function buildDocumentListQuery(
  kbId: string | null,
  groupId: string | null,
): Record<string, string> {
  const query: Record<string, string> = {};
  if (kbId) {
    query.kb = kbId;
  }
  if (groupId) {
    query.group = groupId;
  }
  if (selectedStatus.value !== 'all') {
    query.status = selectedStatus.value;
  }
  if (searchInput.value.trim() !== '') {
    query.search = searchInput.value.trim();
  }
  return query;
}

function normalizeStatusForKnowledgeBase(kbId: string | null): void {
  if (!kbId || selectedStatus.value === 'all') {
    return;
  }

  const knowledgeBase = findKnowledgeBaseById(kbId);
  if (!knowledgeBase) {
    return;
  }

  const options =
    knowledgeBase.category === 'qa'
      ? props.qa_status_options
      : props.document_status_options;
  const validStatuses = new Set(options.map((option) => String(option.value)));

  if (!validStatuses.has(selectedStatus.value)) {
    selectedStatus.value = 'all';
  }
}

function confirmLeaveActiveForm(): boolean {
  switch (activeRightPage.value) {
    case 'knowledge_base_form':
      return knowledgeBaseFormPanelRef.value?.confirmDiscardIfDirty() ?? true;
    case 'manual_document_form':
      return manualDocumentPanelRef.value?.confirmDiscardIfDirty() ?? true;
    case 'qa_entry_form':
      return qaDocumentPanelRef.value?.confirmDiscardIfDirty() ?? true;
    default:
      return true;
  }
}

function hasUnsavedActiveForm(): boolean {
  switch (activeRightPage.value) {
    case 'knowledge_base_form':
      return knowledgeBaseFormPanelRef.value?.hasUnsavedChanges() ?? false;
    case 'manual_document_form':
      return manualDocumentPanelRef.value?.hasUnsavedChanges() ?? false;
    case 'qa_entry_form':
      return qaDocumentPanelRef.value?.hasUnsavedChanges() ?? false;
    default:
      return false;
  }
}

function navigateTo(kbId: string | null, groupId: string | null): void {
  if (!confirmLeaveActiveForm()) {
    return;
  }

  clearListPolling();
  activeRightPage.value = 'knowledge_base';
  normalizeStatusForKnowledgeBase(kbId);
  selectedKbId.value = kbId;
  selectedGroupId.value = groupId;

  allowNextGetNavigation = true;
  router.get(
    KnowledgeBase.ListKnowledgeBasesAction.url({
      query: buildDocumentListQuery(kbId, groupId),
    }),
    {},
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      onFinish: scheduleListPolling,
    },
  );
}

function buildDocumentListPageUrl(page: number): string {
  const query = buildDocumentListQuery(
    selectedKbId.value,
    selectedGroupId.value,
  );
  if (page > 1) {
    query.page = String(page);
  }
  return KnowledgeBase.ListKnowledgeBasesAction.url({ query });
}

watch(searchInput, () => {
  if ((props.search ?? '') === searchInput.value.trim()) {
    return;
  }

  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }

  searchTimeout = setTimeout(() => {
    navigateTo(selectedKbId.value, selectedGroupId.value);
  }, 250);
});

function updateStatusFilter(status: string): void {
  selectedStatus.value = status;
  navigateTo(selectedKbId.value, selectedGroupId.value);
}

function clearAllFilters(): void {
  selectedStatus.value = 'all';
  navigateTo(selectedKbId.value, selectedGroupId.value);
}

function clearListPolling(): void {
  if (listPollTimeout !== null) {
    clearTimeout(listPollTimeout);
    listPollTimeout = null;
  }
}

function shouldPollCurrentList(): boolean {
  return (
    listPollingMounted &&
    activeRightPage.value === 'knowledge_base' &&
    !groupDialogOpen.value &&
    !uploadProcessing.value &&
    selectedKb.value !== null &&
    hasProcessingListItem.value
  );
}

function scheduleListPolling(): void {
  clearListPolling();

  if (!shouldPollCurrentList() || listPollRequestActive) {
    return;
  }

  listPollTimeout = setTimeout(() => {
    listPollTimeout = null;

    if (!shouldPollCurrentList() || listPollRequestActive) {
      return;
    }

    router.reload({
      only: selectedKbIsQa.value
        ? ['qa_entry_list', 'qa_entry_list_pagination']
        : ['document_list', 'document_list_pagination'],
      onStart: () => {
        listPollRequestActive = true;
      },
      onFinish: () => {
        listPollRequestActive = false;
        scheduleListPolling();
      },
    });

    if (!listPollRequestActive) {
      scheduleListPolling();
    }
  }, LIST_POLL_INTERVAL_MS);
}

function selectKb(kbId: string): void {
  if (selectedKbId.value === kbId) {
    if (selectedGroupId.value !== null) {
      navigateTo(kbId, null);
    }
    return;
  }
  navigateTo(kbId, null);
}

function selectGroup(kbId: string, groupId: string | null): void {
  if (selectedKbId.value === kbId && selectedGroupId.value === groupId) {
    return;
  }
  navigateTo(kbId, groupId);
}

const isKnowledgeBaseContentPage = computed(() =>
  [
    'knowledge_base',
    'manual_document_form',
    'qa_entry_form',
    'recall_test',
  ].includes(activeRightPage.value),
);

/** 侧边栏知识库行高亮：内容页高亮选中库；编辑表单页高亮被编辑的库。 */
const activeKbRowId = computed(() =>
  isKnowledgeBaseContentPage.value ||
  (activeRightPage.value === 'knowledge_base_form' && editingKb.value)
    ? selectedKbId.value
    : null,
);

/** 侧边栏分组级高亮（含「全部文档/问答」行）；仅内容页参与。 */
const sidebarGroupSelection = computed(() =>
  isKnowledgeBaseContentPage.value && selectedKbId.value
    ? { kbId: selectedKbId.value, groupId: selectedGroupId.value }
    : null,
);

function clearTransientListState(): void {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
    searchTimeout = null;
  }
  filterPanelOpen.value = false;
  previewDialogOpen.value = false;
  previewDocumentTarget.value = null;
}

const createCategory = ref<KnowledgeBaseCategory>('standard');
const knowledgeBaseFormMode = ref<'create' | 'edit'>('create');
const editingKb = ref<KnowledgeBaseData | null>(null);

const categoryOptions = computed(() => props.category_options);

const activeCategoryLabel = computed(() => {
  if (editingKb.value) {
    return editingKb.value.category_label;
  }

  return (
    categoryOptions.value.find((o) => o.value === createCategory.value)
      ?.label ?? ''
  );
});

function openCreateDialog(category: KnowledgeBaseCategory): void {
  if (!confirmLeaveActiveForm()) {
    return;
  }

  clearTransientListState();
  knowledgeBaseFormMode.value = 'create';
  createCategory.value = category;
  editingKb.value = null;
  activeRightPage.value = 'knowledge_base_form';
}

function openEditDialog(kb: KnowledgeBaseData): void {
  if (!confirmLeaveActiveForm()) {
    return;
  }

  clearTransientListState();
  selectedKbId.value = kb.id;
  selectedGroupId.value = null;
  knowledgeBaseFormMode.value = 'edit';
  createCategory.value = normalizeKnowledgeBaseCategory(kb.category);
  editingKb.value = kb;
  activeRightPage.value = 'knowledge_base_form';
}

function openRecallTestPage(): void {
  if (!selectedKb.value || !confirmLeaveActiveForm()) {
    return;
  }
  clearTransientListState();
  activeRightPage.value = 'recall_test';
}

function returnToKnowledgeBasePage(): void {
  if (!confirmLeaveActiveForm()) {
    return;
  }

  finishRightPanel();
}

function finishRightPanel(): void {
  clearTransientListState();
  activeRightPage.value = 'knowledge_base';
}

/** 从问答库「添加」下拉进入该库的经验提炼任务列表（提炼结果采纳后写入当前问答库）。 */
function goToExperienceExtraction(): void {
  if (!selectedKbId.value) {
    return;
  }

  router.visit(
    experienceExtraction.index.url({
      knowledgeBase: selectedKbId.value,
    }),
  );
}

const previewDocumentTarget = ref<ListKnowledgeDocumentItemData | null>(null);
const previewDialogOpen = ref(false);
const uploadDialogOpen = ref(false);
const uploadProcessing = ref(false);

function openDocumentPreview(document: ListKnowledgeDocumentItemData): void {
  previewDocumentTarget.value = document;
  previewDialogOpen.value = true;
}

function updatePreviewDialog(open: boolean): void {
  previewDialogOpen.value = open;
}

function openUploadDialog(): void {
  if (!selectedKb.value || !confirmLeaveActiveForm()) {
    return;
  }
  clearTransientListState();
  uploadProcessing.value = false;
  uploadDialogOpen.value = true;
}

function updateUploadDialog(open: boolean): void {
  if (!open && uploadProcessing.value) {
    return;
  }

  uploadDialogOpen.value = open;
}

function refreshListAfterUpload(): void {
  const query: Record<string, string> = {};
  if (selectedKbId.value) {
    query.kb = selectedKbId.value;
  }
  if (selectedGroupId.value) {
    query.group = selectedGroupId.value;
  }

  clearListPolling();
  listPollRequestActive = false;
  allowNextGetNavigation = true;
  router.get(
    KnowledgeBase.ListKnowledgeBasesAction.url({ query }),
    {},
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      onFinish: scheduleListPolling,
    },
  );
}

const manualDialogMode = ref<'create' | 'edit'>('create');
const manualDialogTarget = ref<ListKnowledgeDocumentItemData | null>(null);

function openManualCreateDialog(): void {
  if (!selectedKb.value || !confirmLeaveActiveForm()) {
    return;
  }
  clearTransientListState();
  manualDialogMode.value = 'create';
  manualDialogTarget.value = null;
  activeRightPage.value = 'manual_document_form';
}

function openManualEditDialog(doc: ListKnowledgeDocumentItemData): void {
  if (
    !selectedKb.value ||
    doc.source_type !== 'manual' ||
    !confirmLeaveActiveForm()
  ) {
    return;
  }
  clearTransientListState();
  manualDialogMode.value = 'edit';
  manualDialogTarget.value = doc;
  activeRightPage.value = 'manual_document_form';
}

const manualDialogDefaultGroupId = computed(() => {
  if (selectedGroupId.value) {
    return selectedGroupId.value;
  }
  const defaultGroup = (selectedKb.value?.document_groups ?? []).find(
    (group) => group.is_default,
  );
  return defaultGroup?.id ?? selectedKbGroupOptions.value[0]?.id ?? null;
});

const qaDialogMode = ref<'create' | 'edit'>('create');
const qaDialogTarget = ref<ListKnowledgeQaEntryItemData | null>(null);

function openQaCreateDialog(): void {
  if (!selectedKb.value || !selectedKbIsQa.value || !confirmLeaveActiveForm()) {
    return;
  }
  clearTransientListState();
  qaDialogMode.value = 'create';
  qaDialogTarget.value = null;
  activeRightPage.value = 'qa_entry_form';
}

function openQaEditDialog(entry: ListKnowledgeQaEntryItemData): void {
  if (!selectedKb.value || !selectedKbIsQa.value || !confirmLeaveActiveForm()) {
    return;
  }
  clearTransientListState();
  qaDialogMode.value = 'edit';
  qaDialogTarget.value = entry;
  activeRightPage.value = 'qa_entry_form';
}

/**
 * 面包屑随右栏虚拟子页联动：知识库根可点回列表，选中知识库可点回其文档列表，
 * 末项标识当前虚拟子页（表单/检索测试等）。
 */
const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => {
  const root: PageBreadcrumbItem = {
    title: t('知识库'),
    onClick: () => navigateTo(null, null),
  };

  if (activeRightPage.value === 'knowledge_base_form') {
    if (knowledgeBaseFormMode.value === 'edit' && editingKb.value) {
      return [
        root,
        { title: editingKb.value.name, onClick: returnToKnowledgeBasePage },
        { title: t('编辑知识库') },
      ];
    }

    return [root, { title: t('添加知识库') }];
  }

  if (selectedKb.value) {
    const kbCrumb: PageBreadcrumbItem = {
      title: selectedKb.value.name,
      onClick: returnToKnowledgeBasePage,
    };

    if (activeRightPage.value === 'manual_document_form') {
      return [
        root,
        kbCrumb,
        {
          title:
            manualDialogMode.value === 'edit' ? t('编辑内容') : t('添加内容'),
        },
      ];
    }

    if (activeRightPage.value === 'qa_entry_form') {
      return [
        root,
        kbCrumb,
        {
          title: qaDialogMode.value === 'edit' ? t('编辑问答') : t('添加问答'),
        },
      ];
    }

    if (activeRightPage.value === 'recall_test') {
      return [root, kbCrumb, { title: t('测试知识库') }];
    }

    return [root, { title: selectedKb.value.name }];
  }

  return [root];
});

function findKnowledgeBaseById(kbId: string | null): KnowledgeBaseData | null {
  if (!kbId) {
    return null;
  }

  return props.knowledge_base_list.find((kb) => kb.id === kbId) ?? null;
}

function findCurrentDocumentById(
  documentId: string | null,
): ListKnowledgeDocumentItemData | null {
  if (!documentId) {
    return null;
  }

  return props.document_list.find((doc) => doc.id === documentId) ?? null;
}

function findCurrentQaEntryById(
  entryId: string | null,
): ListKnowledgeQaEntryItemData | null {
  if (!entryId) {
    return null;
  }

  return props.qa_entry_list.find((entry) => entry.id === entryId) ?? null;
}

function resetRightPanelState(): void {
  activeRightPage.value = 'knowledge_base';
  knowledgeBaseFormMode.value = 'create';
  editingKb.value = null;
  manualDialogMode.value = 'create';
  manualDialogTarget.value = null;
  qaDialogMode.value = 'create';
  qaDialogTarget.value = null;
}

function applyRightPanelStateFromUrl(): void {
  if (typeof window === 'undefined') {
    return;
  }

  const url = new URL(window.location.href);
  const panel = url.searchParams.get(
    panelQueryParam,
  ) as RightPanelQueryValue | null;

  resetRightPanelState();

  if (panel === 'recall') {
    if (selectedKb.value) {
      activeRightPage.value = 'recall_test';
    }
    return;
  }

  if (panel === 'kb-create') {
    knowledgeBaseFormMode.value = 'create';
    createCategory.value = normalizeKnowledgeBaseCategory(
      url.searchParams.get(categoryQueryParam),
    );
    activeRightPage.value = 'knowledge_base_form';
    return;
  }

  if (panel === 'kb-edit') {
    const kb = findKnowledgeBaseById(selectedKbId.value);
    if (kb) {
      knowledgeBaseFormMode.value = 'edit';
      createCategory.value = normalizeKnowledgeBaseCategory(kb.category);
      editingKb.value = kb;
      activeRightPage.value = 'knowledge_base_form';
    }
    return;
  }

  if (panel === 'manual-create') {
    if (selectedKb.value && !selectedKbIsQa.value) {
      manualDialogMode.value = 'create';
      activeRightPage.value = 'manual_document_form';
    }
    return;
  }

  if (panel === 'manual-edit') {
    const doc = findCurrentDocumentById(
      url.searchParams.get(documentQueryParam),
    );
    if (
      selectedKb.value &&
      !selectedKbIsQa.value &&
      doc?.source_type === 'manual'
    ) {
      manualDialogMode.value = 'edit';
      manualDialogTarget.value = doc;
      activeRightPage.value = 'manual_document_form';
    }
    return;
  }

  if (panel === 'qa-create') {
    if (selectedKb.value && selectedKbIsQa.value) {
      qaDialogMode.value = 'create';
      activeRightPage.value = 'qa_entry_form';
    }
    return;
  }

  if (panel === 'qa-edit') {
    const entry = findCurrentQaEntryById(url.searchParams.get(entryQueryParam));
    if (selectedKb.value && selectedKbIsQa.value && entry) {
      qaDialogMode.value = 'edit';
      qaDialogTarget.value = entry;
      activeRightPage.value = 'qa_entry_form';
    }
  }
}

function clearRightPanelQueryParams(url: URL): void {
  url.searchParams.delete(panelQueryParam);
  url.searchParams.delete(categoryQueryParam);
  url.searchParams.delete(documentQueryParam);
  url.searchParams.delete(entryQueryParam);
}

function syncKnowledgeBaseScopeParams(url: URL): void {
  if (selectedKbId.value) {
    url.searchParams.set('kb', selectedKbId.value);
  } else {
    url.searchParams.delete('kb');
  }

  if (selectedGroupId.value) {
    url.searchParams.set('group', selectedGroupId.value);
  } else {
    url.searchParams.delete('group');
  }
}

function writeKnowledgeBaseUrlState(): void {
  if (typeof window === 'undefined') {
    return;
  }

  const url = new URL(window.location.href);

  syncKnowledgeBaseScopeParams(url);
  clearRightPanelQueryParams(url);

  if (activeRightPage.value === 'recall_test') {
    url.searchParams.set(panelQueryParam, 'recall');
  } else if (activeRightPage.value === 'knowledge_base_form') {
    if (knowledgeBaseFormMode.value === 'edit' && editingKb.value) {
      url.searchParams.set('kb', editingKb.value.id);
      url.searchParams.delete('group');
      url.searchParams.set(panelQueryParam, 'kb-edit');
    } else {
      url.searchParams.set(panelQueryParam, 'kb-create');
      url.searchParams.set(categoryQueryParam, createCategory.value);
    }
  } else if (activeRightPage.value === 'manual_document_form') {
    url.searchParams.set(
      panelQueryParam,
      manualDialogMode.value === 'edit' ? 'manual-edit' : 'manual-create',
    );
    if (manualDialogMode.value === 'edit' && manualDialogTarget.value) {
      url.searchParams.set(documentQueryParam, manualDialogTarget.value.id);
    }
  } else if (activeRightPage.value === 'qa_entry_form') {
    url.searchParams.set(
      panelQueryParam,
      qaDialogMode.value === 'edit' ? 'qa-edit' : 'qa-create',
    );
    if (qaDialogMode.value === 'edit' && qaDialogTarget.value) {
      url.searchParams.set(entryQueryParam, qaDialogTarget.value.id);
    }
  }

  window.history.replaceState(window.history.state, '', url.toString());
}

applyRightPanelStateFromUrl();

onMounted(() => {
  listPollingMounted = true;
  normalizeStatusForKnowledgeBase(selectedKbId.value);
  writeKnowledgeBaseUrlState();
  scheduleListPolling();
  removeBeforeListener = router.on('before', (event) => {
    if (event.detail.visit.method !== 'get') {
      return;
    }
    if (uploadProcessing.value) {
      event.preventDefault();
      return;
    }
    if (allowNextGetNavigation) {
      allowNextGetNavigation = false;
      return;
    }
    if (!confirmLeaveActiveForm()) {
      event.preventDefault();
    }
  });
  window.addEventListener('beforeunload', onBeforeUnload);
});

watch(
  [
    hasProcessingListItem,
    activeRightPage,
    () => selectedKb.value?.id ?? null,
    selectedKbIsQa,
    groupDialogOpen,
    uploadProcessing,
  ],
  scheduleListPolling,
);

onBeforeUnmount(() => {
  listPollingMounted = false;
  clearListPolling();
  removeBeforeListener?.();
  window.removeEventListener('beforeunload', onBeforeUnload);
  if (searchTimeout !== null) {
    clearTimeout(searchTimeout);
    searchTimeout = null;
  }
});

function onBeforeUnload(event: BeforeUnloadEvent): void {
  if (!hasUnsavedActiveForm() && !uploadProcessing.value) {
    return;
  }

  event.preventDefault();
  event.returnValue = '';
}

watch(
  [
    selectedKbId,
    selectedGroupId,
    activeRightPage,
    createCategory,
    knowledgeBaseFormMode,
    () => editingKb.value?.id ?? null,
    manualDialogMode,
    () => manualDialogTarget.value?.id ?? null,
    qaDialogMode,
    () => qaDialogTarget.value?.id ?? null,
  ],
  writeKnowledgeBaseUrlState,
);

const deleteDocumentTarget = ref<ListKnowledgeDocumentItemData | null>(null);
const deleteDocumentForm = useForm({});
const reindexDocumentForm = useForm({});

function reindexDocument(doc: ListKnowledgeDocumentItemData): void {
  const kb = selectedKb.value;
  if (!kb) {
    return;
  }
  reindexDocumentForm.post(
    KnowledgeBase.Indexing.ReindexKnowledgeDocumentAction.url({
      knowledgeBase: kb.id,
      document: doc.id,
    }),
    {
      preserveScroll: true,
    },
  );
}

function overallBadgeVariant(
  status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
  switch (status) {
    case 'failed':
      return 'destructive';
    case 'succeeded':
    case 'indexed':
      return 'default';
    case 'idle':
      return 'outline';
    default:
      return 'secondary';
  }
}

const deleteQaEntryTarget = ref<ListKnowledgeQaEntryItemData | null>(null);
const deleteQaEntryForm = useForm({});
const moveDocumentTarget = ref<ListKnowledgeDocumentItemData | null>(null);
const moveDocumentForm = useForm({
  group_id: '',
});
const moveQaEntryTarget = ref<ListKnowledgeQaEntryItemData | null>(null);
const moveQaEntryForm = useForm({
  group_id: '',
});

const moveDocumentGroupOptions = computed(() =>
  selectedKbGroupOptions.value.filter(
    (group) => group.id !== moveDocumentTarget.value?.group_id,
  ),
);

const moveQaEntryGroupOptions = computed(() =>
  selectedKbGroupOptions.value.filter(
    (group) => group.id !== moveQaEntryTarget.value?.group_id,
  ),
);

function openMoveDocumentDialog(doc: ListKnowledgeDocumentItemData): void {
  moveDocumentTarget.value = doc;
  moveDocumentForm.group_id = '';
  moveDocumentForm.clearErrors();
}

function moveDocument(): void {
  const target = moveDocumentTarget.value;
  const kb = selectedKb.value;
  if (!target || !kb || !moveDocumentForm.group_id) {
    return;
  }

  if (target.group_id === moveDocumentForm.group_id) {
    moveDocumentForm.setError('group_id', t('请选择其他分组'));
    return;
  }

  moveDocumentForm.put(
    KnowledgeBase.Document.MoveKnowledgeDocumentAction.url({
      knowledgeBase: kb.id,
      document: target.id,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        moveDocumentTarget.value = null;
      },
    },
  );
}

function openMoveQaEntryDialog(entry: ListKnowledgeQaEntryItemData): void {
  moveQaEntryTarget.value = entry;
  moveQaEntryForm.group_id = '';
  moveQaEntryForm.clearErrors();
}

function moveQaEntry(): void {
  const target = moveQaEntryTarget.value;
  const kb = selectedKb.value;
  if (!target || !kb || !moveQaEntryForm.group_id) {
    return;
  }

  if (target.group_id === moveQaEntryForm.group_id) {
    moveQaEntryForm.setError('group_id', t('请选择其他分组'));
    return;
  }

  moveQaEntryForm.put(
    KnowledgeBase.Qa.MoveKnowledgeQaEntryAction.url({
      knowledgeBase: kb.id,
      entry: target.id,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        moveQaEntryTarget.value = null;
      },
    },
  );
}

function confirmDeleteDocument(): void {
  const target = deleteDocumentTarget.value;
  const kb = selectedKb.value;
  if (!target || !kb) {
    return;
  }
  deleteDocumentForm.delete(
    KnowledgeBase.Document.DeleteKnowledgeDocumentAction.url({
      knowledgeBase: kb.id,
      document: target.id,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        deleteDocumentTarget.value = null;
      },
    },
  );
}

function confirmDeleteQaEntry(): void {
  const target = deleteQaEntryTarget.value;
  const kb = selectedKb.value;
  if (!target || !kb) {
    return;
  }
  deleteQaEntryForm.delete(
    KnowledgeBase.Qa.DeleteKnowledgeQaEntryAction.url({
      knowledgeBase: kb.id,
      entry: target.id,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        deleteQaEntryTarget.value = null;
      },
    },
  );
}
</script>

<template>
  <div class="contents">
    <KnowledgeBasesLayout>
      <template #sidebar="{ closeMobileExplorer }">
        <KnowledgeBaseExplorerSidebar
          :knowledge-bases="props.knowledge_base_list"
          :category-options="categoryOptions"
          :active-kb-id="activeKbRowId"
          :group-selection="sidebarGroupSelection"
          :editing-kb-id="
            activeRightPage === 'knowledge_base_form'
              ? (editingKb?.id ?? null)
              : null
          "
          :create-active="
            activeRightPage === 'knowledge_base_form' && !editingKb
          "
          :destructive-actions-disabled="
            activeRightPage !== 'knowledge_base' || uploadDialogOpen
          "
          @navigate="closeMobileExplorer"
          @group-dialog-open="groupDialogOpen = $event"
          @select-kb="selectKb"
          @select-group="selectGroup"
          @create="openCreateDialog"
          @edit="openEditDialog"
        />
      </template>

      <PageBreadcrumb :items="breadcrumbItems" class="mb-6" />

      <KnowledgeBaseFormPanel
        v-if="activeRightPage === 'knowledge_base_form'"
        ref="knowledgeBaseFormPanelRef"
        :key="`kb-form:${knowledgeBaseFormMode}:${editingKb?.id ?? createCategory}`"
        :mode="knowledgeBaseFormMode"
        :category="createCategory"
        :category-label="activeCategoryLabel"
        :knowledge-base="editingKb"
        @cancel="finishRightPanel"
        @saved="finishRightPanel"
      />

      <KnowledgeManualDocumentPanel
        v-else-if="activeRightPage === 'manual_document_form' && selectedKb"
        ref="manualDocumentPanelRef"
        :key="`manual:${manualDialogMode}:${manualDialogTarget?.id ?? selectedKb.id}`"
        :mode="manualDialogMode"
        :knowledge-base-id="selectedKb.id"
        :group-options="selectedKbGroupOptions"
        :default-group-id="manualDialogDefaultGroupId"
        :document="manualDialogTarget"
        @cancel="finishRightPanel"
        @saved="finishRightPanel"
      />

      <KnowledgeQaDocumentPanel
        v-else-if="activeRightPage === 'qa_entry_form' && selectedKb"
        ref="qaDocumentPanelRef"
        :key="`qa:${qaDialogMode}:${qaDialogTarget?.id ?? selectedKb.id}`"
        :mode="qaDialogMode"
        :knowledge-base-id="selectedKb.id"
        :group-options="selectedKbGroupOptions"
        :default-group-id="manualDialogDefaultGroupId"
        :entry="qaDialogTarget"
        @cancel="finishRightPanel"
        @saved="finishRightPanel"
      />

      <KnowledgeRecallTestPanel
        v-else-if="activeRightPage === 'recall_test' && selectedKb"
        :key="`recall:${selectedKb.id}`"
        :knowledge-base-id="selectedKb.id"
        :mode-options="props.search_mode_options"
      />

      <div v-else>
        <div class="mb-6 flex items-start justify-between gap-4">
          <header v-if="selectedKb" class="min-w-0 flex-1">
            <div class="mb-0.5 flex items-center gap-2">
              <h3 class="truncate text-base font-medium">
                {{ selectedKb.name }}
              </h3>
              <Badge variant="secondary" class="shrink-0">
                {{ selectedKb.category_label }}
              </Badge>
            </div>
            <p class="text-sm text-muted-foreground">
              {{
                selectedKb.description ||
                (selectedKbIsQa
                  ? t('管理知识库中的问题和答案。')
                  : t('管理知识库中的文档。'))
              }}
            </p>
          </header>
          <div v-else class="min-w-0 flex-1"></div>

          <div v-if="selectedKb" class="flex shrink-0 items-center gap-2">
            <template v-if="selectedKbIsQa">
              <DropdownMenu>
                <DropdownMenuTrigger as-child>
                  <Button type="button">
                    {{ t('添加') }}
                    <ChevronDown class="ml-1.5 h-4 w-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-48">
                  <DropdownMenuItem @select="openQaCreateDialog">
                    {{ t('填写问答') }}
                  </DropdownMenuItem>
                  <DropdownMenuItem @select="goToExperienceExtraction">
                    {{ t('经验提炼') }}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </template>
            <template v-else>
              <DropdownMenu>
                <DropdownMenuTrigger as-child>
                  <Button type="button">
                    {{ t('添加') }}
                    <ChevronDown class="ml-1.5 h-4 w-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-48">
                  <DropdownMenuItem @select="openUploadDialog">
                    {{ t('上传文档') }}
                  </DropdownMenuItem>
                  <DropdownMenuItem @select="openManualCreateDialog">
                    {{ t('填写内容') }}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </template>
            <Button type="button" variant="outline" @click="openRecallTestPage">
              {{ t('测试知识库') }}
            </Button>
          </div>
        </div>

        <div v-if="!selectedKb" class="flex items-center justify-center py-20">
          <div class="space-y-2 text-center">
            <Library class="mx-auto h-12 w-12 text-muted-foreground/40" />
            <p class="text-sm text-muted-foreground">
              {{
                props.knowledge_base_list.length === 0
                  ? t('请先添加一个知识库')
                  : t('请选择一个知识库')
              }}
            </p>
          </div>
        </div>

        <div v-else class="space-y-6">
          <div class="flex flex-wrap items-center justify-end gap-3">
            <div class="relative">
              <Search
                class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
              />
              <Input
                v-model="searchInput"
                class="h-9 w-48 pl-9 lg:w-64"
                :placeholder="searchPlaceholder"
                :aria-label="searchPlaceholder"
              />
            </div>
            <FilterPopover
              v-model:open="filterPanelOpen"
              :active-count="totalActiveFilterCount"
              @clear="clearAllFilters"
            >
              <KnowledgeDocumentFilterBasicPanel
                :status="selectedStatus"
                :status-options="statusOptions"
                @update:status="updateStatusFilter"
              />
            </FilterPopover>
          </div>

          <div class="min-w-0 rounded-lg border">
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="border-b bg-muted/30 text-muted-foreground">
                  <tr class="text-left">
                    <th class="w-[38%] px-4 py-3">
                      {{ selectedKbIsQa ? t('问题') : t('文件名') }}
                    </th>
                    <th v-if="!selectedKbIsQa" class="px-4 py-3">
                      {{ t('文件类型') }}
                    </th>
                    <th class="px-4 py-3">{{ t('分组') }}</th>
                    <th v-if="!selectedKbIsQa" class="px-4 py-3">
                      {{ t('大小') }}
                    </th>
                    <th class="px-4 py-3">{{ t('状态') }}</th>
                    <th class="w-40 px-4 py-3 text-right whitespace-nowrap">
                      {{ t('操作') }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-if="
                      selectedKbIsQa
                        ? props.qa_entry_list.length === 0
                        : props.document_list.length === 0
                    "
                  >
                    <td
                      :colspan="selectedKbIsQa ? 4 : 6"
                      class="px-4 py-8 text-center text-muted-foreground"
                    >
                      {{
                        hasActiveListQuery
                          ? selectedKbIsQa
                            ? t('没有找到相关问答')
                            : t('没有找到相关文档')
                          : selectedKbIsQa
                            ? t('还没有问答')
                            : t('还没有文档')
                      }}
                    </td>
                  </tr>
                  <KnowledgeQaEntryTable
                    v-else-if="selectedKbIsQa"
                    :entries="props.qa_entry_list"
                    :delete-processing="deleteQaEntryForm.processing"
                    :overflow-tooltip-key="overflowTooltipKey"
                    :group-label="documentGroupLabel"
                    :can-move="canMoveToAnotherGroup"
                    :badge-variant="overallBadgeVariant"
                    @set-overflow-tooltip="setOverflowTooltip"
                    @clear-overflow-tooltip="clearOverflowTooltip"
                    @edit="openQaEditDialog"
                    @move="openMoveQaEntryDialog"
                    @delete="deleteQaEntryTarget = $event"
                  />
                  <KnowledgeDocumentTable
                    v-else
                    :documents="props.document_list"
                    :delete-processing="deleteDocumentForm.processing"
                    :reindex-processing="reindexDocumentForm.processing"
                    :overflow-tooltip-key="overflowTooltipKey"
                    :group-label="documentGroupLabel"
                    :can-move="canMoveToAnotherGroup"
                    :type-label="documentTypeLabel"
                    :badge-variant="overallBadgeVariant"
                    @set-overflow-tooltip="setOverflowTooltip"
                    @clear-overflow-tooltip="clearOverflowTooltip"
                    @preview="openDocumentPreview"
                    @edit="openManualEditDialog"
                    @move="openMoveDocumentDialog"
                    @reindex="reindexDocument"
                    @delete="deleteDocumentTarget = $event"
                  />
                </tbody>
              </table>
            </div>

            <div
              v-if="currentListPagination.last_page > 1"
              class="border-t p-3"
            >
              <PaginationNavigator
                :pagination="currentListPagination"
                :page-url="buildDocumentListPageUrl"
              />
            </div>
          </div>
        </div>
      </div>
    </KnowledgeBasesLayout>

    <Suspense>
      <KnowledgeDocumentPreviewDialog
        v-if="selectedKb && previewDocumentTarget"
        :open="previewDialogOpen"
        :knowledge-base-id="selectedKb.id"
        :document="previewDocumentTarget"
        @update:open="updatePreviewDialog"
      />

      <template #fallback>
        <Dialog :open="previewDialogOpen" @update:open="updatePreviewDialog">
          <DialogContent
            class="flex h-[min(92dvh,860px)] w-[min(94vw,1180px)] max-w-none flex-col gap-0 p-0 sm:max-w-none"
          >
            <DialogHeader class="border-b py-3 pr-14 pl-4">
              <DialogTitle class="truncate text-base">
                {{ previewDocumentTarget?.original_filename ?? t('文档预览') }}
              </DialogTitle>
              <DialogDescription>
                {{ t('查看文档内容') }}
              </DialogDescription>
            </DialogHeader>
            <div
              role="status"
              class="flex min-h-0 flex-1 items-center justify-center bg-muted/20"
            >
              <Spinner class="size-6" />
              <span class="sr-only">{{ t('正在加载…') }}</span>
            </div>
          </DialogContent>
        </Dialog>
      </template>
    </Suspense>

    <MoveToGroupDialog
      :open="moveDocumentTarget !== null"
      select-id="move-document-group"
      :groups="moveDocumentGroupOptions"
      v-model:group-id="moveDocumentForm.group_id"
      :error="moveDocumentForm.errors.group_id"
      :processing="moveDocumentForm.processing"
      @update:open="moveDocumentTarget = $event ? moveDocumentTarget : null"
      @confirm="moveDocument"
    />

    <MoveToGroupDialog
      :open="moveQaEntryTarget !== null"
      select-id="move-qa-entry-group"
      :groups="moveQaEntryGroupOptions"
      v-model:group-id="moveQaEntryForm.group_id"
      :error="moveQaEntryForm.errors.group_id"
      :processing="moveQaEntryForm.processing"
      @update:open="moveQaEntryTarget = $event ? moveQaEntryTarget : null"
      @confirm="moveQaEntry"
    />

    <Dialog :open="uploadDialogOpen" @update:open="updateUploadDialog">
      <DialogContent
        class="max-h-[calc(100dvh-2rem)] overflow-hidden p-0 sm:max-w-2xl"
        :show-close-button="!uploadProcessing"
      >
        <div
          class="max-h-[calc(100dvh-2rem)] [scrollbar-gutter:stable] overflow-y-auto p-6"
        >
          <DialogHeader class="space-y-3 pr-8">
            <DialogTitle>{{ t('上传文档') }}</DialogTitle>
            <DialogDescription>
              {{
                t(
                  '支持 Word、PDF、TXT、Markdown 和 HTML；单个文件不超过 20 MB，一次最多 20 个。',
                )
              }}
            </DialogDescription>
          </DialogHeader>

          <KnowledgeDocumentUploadPanel
            v-if="selectedKb"
            :key="`upload-dialog:${selectedKb.id}:${selectedGroupId ?? 'all'}`"
            class="mt-5"
            :knowledge-base-id="selectedKb.id"
            :group-id="selectedGroupId"
            :show-heading="false"
            @cancel="updateUploadDialog(false)"
            @completed="refreshListAfterUpload"
            @update:processing="uploadProcessing = $event"
          />
        </div>
      </DialogContent>
    </Dialog>

    <ConfirmDeleteDialog
      :open="deleteDocumentTarget !== null"
      :title="t('删除这个文档？')"
      :detail-title="deleteDocumentTarget?.original_filename ?? ''"
      :detail-description="t('删除后无法恢复，知识库将不再使用这份文档。')"
      :processing="deleteDocumentForm.processing"
      @update:open="deleteDocumentTarget = null"
      @confirm="confirmDeleteDocument"
    />

    <ConfirmDeleteDialog
      :open="deleteQaEntryTarget !== null"
      :title="t('删除这条问答？')"
      :detail-title="deleteQaEntryTarget?.question ?? ''"
      :detail-description="
        t('删除后无法恢复，其他问法和全部答案也会一并删除。')
      "
      :processing="deleteQaEntryForm.processing"
      @update:open="deleteQaEntryTarget = null"
      @confirm="confirmDeleteQaEntry"
    />
  </div>
</template>
