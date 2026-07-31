<!--
  联系人列表页，使用 ShowContactListPagePropsData 提供搜索、筛选、详情和管理入口。
-->
<script setup lang="ts">
import ConfirmDeleteDialog from '@/components/common/ConfirmDeleteDialog.vue';
import FilterPopover from '@/components/common/FilterPopover.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import InputError from '@/components/common/InputError.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import PaginationNavigator from '@/components/common/PaginationNavigator.vue';
import PhoneDialCodeCombobox from '@/components/common/PhoneDialCodeCombobox.vue';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Sheet, SheetContent } from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { useI18n } from '@/composables/useI18n';
import { useVisitorDisplay } from '@/composables/useVisitorDisplay';
import AppLayout from '@/layouts/AppLayout.vue';
import { EMAIL_MAX_LENGTH, isLikelyValidEmail } from '@/lib/email';
import {
  buildPhoneNumber,
  getDefaultPhonePrefix,
  isLikelyValidDialCode,
  isLikelyValidLocalPhone,
  isLikelyValidPhone,
} from '@/lib/phone';
import app from '@/routes/app';
import type {
  ContactListType,
  FormCreateContactData,
  FormMergeContactsData,
  ListContactItemData,
  ShowContactListPagePropsData,
  TagMatchMode,
} from '@/types/generated';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

import ContactDetailDrawer from './ContactDetailDrawer.vue';
import ContactFilterAttributePanel from './ContactFilterAttributePanel.vue';
import ContactFilterTagPanel from './ContactFilterTagPanel.vue';
import ContactTableRow from './ContactTableRow.vue';

defineOptions({ layout: AppLayout });

const { locale, t } = useI18n();
const { formatVisitorName } = useVisitorDisplay();
const props = defineProps<ShowContactListPagePropsData>();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  {
    title: t('联系人'),
    href: app.contacts.index.url({
      type: 'all',
    }),
  },
  { title: t('列表') },
]);

const createOpen = ref(false);
const deletingContact = ref<ListContactItemData | null>(null);
const detailRefreshNonce = ref(0);
const mergeOpen = ref(false);
const searchInput = ref(props.search ?? '');
const includeTagIds = ref<string[]>(
  props.tag_filter.include.map((c) => c.tag_id),
);
const excludeTagIds = ref<string[]>(
  props.tag_filter.exclude.map((c) => c.tag_id),
);
const includeTagMode = ref<TagMatchMode>(props.tag_filter.include_mode);
const excludeTagMode = ref<TagMatchMode>(props.tag_filter.exclude_mode);
const untaggedOnly = ref<boolean>(props.tag_filter.untagged_only);
const importantOnly = ref<boolean>(props.important_only);
const filterPanelOpen = ref(false);
type FilterPanelTab = 'attributes' | 'tags' | 'type';
const activeFilterPanelTab = ref<FilterPanelTab>('type');

const changeType = (value: unknown): void => {
  if (typeof value !== 'string') {
    return;
  }

  selectedType.value = value as ContactListType;
  navigateWithFilters();
};
const selectedType = ref<ContactListType>(props.current_type);

const typeOptions = computed(() => props.contact_list_type_options);

const activeFilterCount = computed(() => {
  if (untaggedOnly.value) {
    return 1;
  }
  return includeTagIds.value.length + excludeTagIds.value.length;
});
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const readSelectedContactIdFromUrl = (): string | null => {
  if (typeof window === 'undefined') {
    return null;
  }

  return new URL(window.location.href).searchParams.get('contact');
};

const selectedContactId = ref<string | null>(readSelectedContactIdFromUrl());
const attributeFilterValues = ref<Record<string, unknown>>(
  JSON.parse(JSON.stringify(props.attribute_filters ?? {})) as Record<
    string,
    unknown
  >,
);

const createForm = useForm<FormCreateContactData>({
  name: null,
  email: null,
  phone: null,
});

const deleteForm = useForm({});
const mergeForm = useForm<FormMergeContactsData>({
  target_contact_id: '',
  merged_contact_id: '',
});
const defaultPhonePrefix = computed(() => getDefaultPhonePrefix(locale.value));
const createPhoneDialCode = ref(defaultPhonePrefix.value);
const createPhoneLocalNumber = ref('');

const pageTitle = computed(
  () =>
    props.contact_list_type_options.find(
      (option) => String(option.value) === selectedType.value,
    )?.label ?? t('联系人'),
);

const selectedContact = computed(
  () =>
    props.contact_list.find(
      (contactItem) => contactItem.id === selectedContactId.value,
    ) ?? null,
);
const mergeCandidates = computed(() =>
  props.contact_list.filter(
    (contactItem) => contactItem.id !== selectedContactId.value,
  ),
);
const selectedMergeCandidate = computed(
  () =>
    mergeCandidates.value.find(
      (contactItem) => contactItem.id === mergeForm.merged_contact_id,
    ) ?? null,
);

const displayName = (c: ListContactItemData): string => {
  return formatVisitorName(c.name, c.id);
};

const createPhoneErrorMessage = computed(() => {
  const phone = createPhoneLocalNumber.value.trim();

  if (phone === '') {
    return createForm.errors.phone;
  }

  if (
    !isLikelyValidDialCode(createPhoneDialCode.value) ||
    !isLikelyValidLocalPhone(phone) ||
    !isLikelyValidPhone(buildPhoneNumber(createPhoneDialCode.value, phone))
  ) {
    return t('请输入有效的手机号');
  }

  return createForm.errors.phone;
});

const isCreatePhoneInvalid = computed(() => {
  const phone = createPhoneLocalNumber.value.trim();

  if (phone === '') {
    return false;
  }

  return (
    !isLikelyValidDialCode(createPhoneDialCode.value) ||
    !isLikelyValidLocalPhone(phone) ||
    !isLikelyValidPhone(buildPhoneNumber(createPhoneDialCode.value, phone))
  );
});

const createEmailErrorMessage = computed(() => {
  const email = (createForm.email ?? '').trim();

  if (email === '') {
    return createForm.errors.email;
  }

  if (!isLikelyValidEmail(email)) {
    return t('请输入有效的邮箱地址');
  }

  return createForm.errors.email;
});

const isCreateEmailInvalid = computed(() => {
  const email = (createForm.email ?? '').trim();

  if (email === '') {
    return false;
  }

  return !isLikelyValidEmail(email);
});

const mergeErrorMessage = computed(() => {
  const errors = mergeForm.errors as Record<string, string | undefined>;

  return errors.target_contact_id || errors.merged_contact_id;
});

const hasAttributeFilters = computed(
  () => props.attribute_filter_definitions.length > 0,
);
const activeAttributeFilterCount = computed(
  () => Object.keys(buildAttributeFilterQuery()).length,
);
const importantFilterCount = computed(() => (importantOnly.value ? 1 : 0));
const hasTagOptions = computed(() => props.available_tags.length > 0);
const totalActiveFilterCount = computed(
  () =>
    activeAttributeFilterCount.value +
    activeFilterCount.value +
    importantFilterCount.value,
);
const tagFilterCountLabel = computed(() => {
  if (activeFilterCount.value === 0) {
    return undefined;
  }

  return untaggedOnly.value && activeFilterCount.value === 1
    ? t('无标签')
    : activeFilterCount.value;
});
const filterBadgeLabel = computed(() =>
  untaggedOnly.value && totalActiveFilterCount.value === 1
    ? t('无标签')
    : totalActiveFilterCount.value,
);
const filterGroups = computed(() => [
  {
    value: 'type',
    label: t('基本'),
    count: importantFilterCount.value || undefined,
  },
  {
    value: 'attributes',
    label: t('自定义字段'),
    count: activeAttributeFilterCount.value || undefined,
    visible: hasAttributeFilters.value,
  },
  {
    value: 'tags',
    label: t('标签'),
    count: tagFilterCountLabel.value,
    visible: hasTagOptions.value,
  },
]);
const visibleFilterPanelTabs = computed(() =>
  filterGroups.value
    .filter((group) => group.visible !== false)
    .map((group) => group.value),
);

const syncActiveFilterPanelTab = () => {
  if (!visibleFilterPanelTabs.value.includes(activeFilterPanelTab.value)) {
    activeFilterPanelTab.value = 'type';
  }
};

const buildAttributeFilterQuery = (): Record<string, unknown> => {
  const normalizedFilters: Record<string, unknown> = {};

  for (const definition of props.attribute_filter_definitions) {
    const rawValue = attributeFilterValues.value[definition.key];

    if (definition.type === 'single_select') {
      if (typeof rawValue === 'string' && rawValue !== '') {
        normalizedFilters[definition.key] = rawValue;
      }

      continue;
    }

    if (definition.type === 'boolean') {
      if (typeof rawValue === 'boolean') {
        normalizedFilters[definition.key] = rawValue;
      }

      continue;
    }

    if (definition.type === 'number' || definition.type === 'date') {
      if (
        !rawValue ||
        typeof rawValue !== 'object' ||
        Array.isArray(rawValue)
      ) {
        continue;
      }

      const currentValue = rawValue as Record<string, unknown>;
      const normalizedBounds = Object.fromEntries(
        Object.entries(currentValue).filter(
          ([, value]) => value !== '' && value !== null && value !== undefined,
        ),
      );

      if (Object.keys(normalizedBounds).length > 0) {
        normalizedFilters[definition.key] = normalizedBounds;
      }
    }
  }

  return normalizedFilters;
};

const buildAttributeFilterQueryParams = (
  filters: Record<string, unknown>,
): Record<string, string | number | boolean> => {
  const queryParams: Record<string, string | number | boolean> = {};

  for (const [key, value] of Object.entries(filters)) {
    if (
      typeof value === 'string' ||
      typeof value === 'number' ||
      typeof value === 'boolean'
    ) {
      queryParams[`attribute_filters[${key}]`] = value;
      continue;
    }

    if (!value || typeof value !== 'object' || Array.isArray(value)) {
      continue;
    }

    for (const [boundary, boundaryValue] of Object.entries(value)) {
      if (
        typeof boundaryValue === 'string' ||
        typeof boundaryValue === 'number' ||
        typeof boundaryValue === 'boolean'
      ) {
        queryParams[`attribute_filters[${key}][${boundary}]`] = boundaryValue;
      }
    }
  }

  return queryParams;
};

let attributeFilterTimeout: ReturnType<typeof setTimeout> | null = null;

const syncSelectedContactInUrl = (contactId: string | null) => {
  if (typeof window === 'undefined') {
    return;
  }

  const url = new URL(window.location.href);

  if (contactId) {
    url.searchParams.set('contact', contactId);
  } else {
    url.searchParams.delete('contact');
  }

  window.history.replaceState(window.history.state, '', url.toString());
};

/** 构造标签筛选参数；只看无标签时清除包含和排除条件。 */
const buildTagQueryParams = (): Record<string, unknown> => {
  if (untaggedOnly.value) {
    return {
      untagged_only: 1,
      include_tag_ids: undefined,
      include_tag_mode: undefined,
      exclude_tag_ids: undefined,
      exclude_tag_mode: undefined,
    };
  }

  return {
    untagged_only: undefined,
    include_tag_ids:
      includeTagIds.value.length > 0 ? includeTagIds.value : undefined,
    include_tag_mode:
      includeTagIds.value.length > 0 ? includeTagMode.value : undefined,
    exclude_tag_ids:
      excludeTagIds.value.length > 0 ? excludeTagIds.value : undefined,
    exclude_tag_mode:
      excludeTagIds.value.length > 0 ? excludeTagMode.value : undefined,
  };
};

const buildContactListPageUrl = (page: number): string => {
  return app.contacts.index.url(
    {
      type: selectedType.value,
    },
    {
      query: {
        page,
        search: props.search || undefined,
        contact: selectedContactId.value || undefined,
        important: props.important_only ? 1 : undefined,
        ...buildAttributeFilterQueryParams(props.attribute_filters ?? {}),
        ...buildTagQueryParams(),
      },
    },
  );
};

const navigateWithFilters = (overrides?: Record<string, unknown>) => {
  const normalizedAttributeFilters = buildAttributeFilterQuery();

  router.get(
    app.contacts.index.url({
      type: selectedType.value,
    }),
    {
      search: searchInput.value || undefined,
      contact: selectedContactId.value || undefined,
      important: importantOnly.value ? 1 : undefined,
      ...buildAttributeFilterQueryParams(normalizedAttributeFilters),
      ...buildTagQueryParams(),
      ...overrides,
    },
    { preserveState: true, replace: true },
  );
};

const clearAllFilters = () => {
  if (attributeFilterTimeout) {
    clearTimeout(attributeFilterTimeout);
    attributeFilterTimeout = null;
  }

  attributeFilterValues.value = {};
  includeTagIds.value = [];
  excludeTagIds.value = [];
  untaggedOnly.value = false;
  importantOnly.value = false;
  navigateWithFilters();
};

const updateImportantOnly = (value: boolean): void => {
  importantOnly.value = value;
  navigateWithFilters({ important: value ? 1 : undefined });
};

watch(searchInput, (val) => {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }
  searchTimeout = setTimeout(() => {
    navigateWithFilters({ search: val || undefined });
  }, 300);
});

watch(
  () => props.search,
  (value) => {
    searchInput.value = value ?? '';
  },
);

watch(
  () => props.tag_filter,
  (value) => {
    includeTagIds.value = value.include.map((c) => c.tag_id);
    excludeTagIds.value = value.exclude.map((c) => c.tag_id);
    includeTagMode.value = value.include_mode;
    excludeTagMode.value = value.exclude_mode;
    untaggedOnly.value = value.untagged_only;
  },
  { deep: true },
);

watch(
  () => props.important_only,
  (value) => {
    importantOnly.value = value;
  },
);

watch(selectedContactId, (contactId) => {
  syncSelectedContactInUrl(contactId);
});

const handleAttributeValuesUpdate = (next: Record<string, unknown>) => {
  attributeFilterValues.value = next;
};

watch(
  () => props.attribute_filters,
  (filters) => {
    attributeFilterValues.value = JSON.parse(
      JSON.stringify(filters ?? {}),
    ) as Record<string, unknown>;
  },
);

watch(
  [filterPanelOpen, hasAttributeFilters, hasTagOptions],
  ([isOpen]) => {
    if (isOpen) {
      syncActiveFilterPanelTab();
    }
  },
  { immediate: true },
);

watch(defaultPhonePrefix, (value) => {
  if (createPhoneLocalNumber.value.trim() !== '') {
    return;
  }

  createPhoneDialCode.value = value;
});

watch(createOpen, (open) => {
  if (open) {
    createPhoneDialCode.value = defaultPhonePrefix.value;

    return;
  }

  if (createForm.processing) {
    return;
  }

  createForm.reset();
  createForm.clearErrors();
  createPhoneDialCode.value = defaultPhonePrefix.value;
  createPhoneLocalNumber.value = '';
});

const submitCreate = () => {
  createForm.phone = createPhoneLocalNumber.value.trim()
    ? buildPhoneNumber(createPhoneDialCode.value, createPhoneLocalNumber.value)
    : null;

  if (createForm.email !== null) {
    const trimmedEmail = createForm.email.trim();
    createForm.email = trimmedEmail === '' ? null : trimmedEmail;
  }

  if (isCreatePhoneInvalid.value) {
    createForm.setError('phone', t('请输入有效的手机号'));

    return;
  }

  if (isCreateEmailInvalid.value) {
    createForm.setError('email', t('请输入有效的邮箱地址'));

    return;
  }

  createForm.clearErrors('phone', 'email');
  createForm.post(app.contacts.store.url(), {
    preserveScroll: true,
    onSuccess: () => {
      createForm.reset();
      createPhoneDialCode.value = defaultPhonePrefix.value;
      createPhoneLocalNumber.value = '';
      createOpen.value = false;
    },
  });
};

const openDeleteDialog = (c: ListContactItemData) => {
  deletingContact.value = c;
};

const closeDeleteDialog = (open: boolean) => {
  if (open || deleteForm.processing) {
    return;
  }
  deletingContact.value = null;
};

const submitDelete = () => {
  if (!deletingContact.value) {
    return;
  }
  deleteForm.delete(
    app.contacts.destroy.url({
      id: deletingContact.value.id,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        if (
          selectedContactId.value &&
          selectedContactId.value === deletingContact.value?.id
        ) {
          selectedContactId.value = null;
        }
        deletingContact.value = null;
      },
    },
  );
};

const openMergeDialog = () => {
  if (!selectedContactId.value || mergeCandidates.value.length === 0) {
    return;
  }

  mergeForm.clearErrors();
  mergeForm.target_contact_id = selectedContactId.value;
  mergeForm.merged_contact_id = '';
  mergeOpen.value = true;
};

const closeMergeDialog = (open: boolean) => {
  if (open || mergeForm.processing) {
    return;
  }

  mergeForm.reset();
  mergeForm.clearErrors();
  mergeOpen.value = false;
};

const submitMerge = () => {
  if (!selectedContactId.value || !mergeForm.merged_contact_id) {
    return;
  }

  mergeForm.target_contact_id = selectedContactId.value;
  mergeForm.post(app.contacts.merge.url(), {
    preserveScroll: true,
    onSuccess: () => {
      mergeForm.reset();
      mergeForm.clearErrors();
      mergeOpen.value = false;
      detailRefreshNonce.value += 1;
    },
  });
};

const openDetail = (c: ListContactItemData) => {
  selectedContactId.value = c.id;
};

const closeDetail = () => {
  selectedContactId.value = null;
};

const onDetailOpenChange = (open: boolean) => {
  if (!open) {
    closeDetail();
  }
};
</script>

<template>
  <div class="contents">
    <Head :title="pageTitle" />

    <div class="px-4 py-6 sm:px-6">
      <div class="space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />

        <div class="flex items-start justify-between gap-4">
          <HeadingSmall
            :title="t('联系人')"
            :description="t('查看联系人资料和联系方式')"
          />

          <div class="flex items-center gap-2">
            <Dialog v-model:open="createOpen">
              <DialogTrigger as-child>
                <Button>
                  {{ t('添加联系人') }}
                </Button>
              </DialogTrigger>
              <DialogContent>
                <DialogHeader class="space-y-3">
                  <DialogTitle>{{ t('添加联系人') }}</DialogTitle>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitCreate">
                  <div class="space-y-2">
                    <Label for="create-name">{{ t('名称') }}</Label>
                    <Input
                      id="create-name"
                      :model-value="createForm.name ?? ''"
                      :disabled="createForm.processing"
                      maxlength="255"
                      @update:model-value="
                        createForm.name = ($event as string) || null
                      "
                    />
                    <InputError :message="createForm.errors.name" />
                  </div>

                  <div class="space-y-2">
                    <Label for="create-email">{{ t('邮箱') }}</Label>
                    <Input
                      id="create-email"
                      :model-value="createForm.email ?? ''"
                      type="email"
                      inputmode="email"
                      autocomplete="email"
                      :maxlength="EMAIL_MAX_LENGTH"
                      :disabled="createForm.processing"
                      @update:model-value="
                        createForm.email = ($event as string) || null
                      "
                    />
                    <InputError :message="createEmailErrorMessage" />
                  </div>

                  <div class="space-y-2">
                    <Label for="create-phone">{{ t('手机号') }}</Label>
                    <div class="flex gap-2">
                      <PhoneDialCodeCombobox
                        v-model="createPhoneDialCode"
                        class="w-36 shrink-0"
                        :disabled="createForm.processing"
                      />
                      <Input
                        id="create-phone"
                        type="tel"
                        inputmode="tel"
                        :model-value="createPhoneLocalNumber"
                        :disabled="createForm.processing"
                        @update:model-value="
                          createPhoneLocalNumber = $event as string
                        "
                      />
                    </div>
                    <InputError :message="createPhoneErrorMessage" />
                  </div>

                  <DialogFooter class="gap-2">
                    <DialogClose as-child>
                      <Button
                        type="button"
                        variant="secondary"
                        :disabled="createForm.processing"
                      >
                        {{ t('取消') }}
                      </Button>
                    </DialogClose>
                    <Button
                      type="submit"
                      :disabled="
                        createForm.processing ||
                        isCreatePhoneInvalid ||
                        isCreateEmailInvalid
                      "
                    >
                      {{ t('保存') }}
                    </Button>
                  </DialogFooter>
                </form>
              </DialogContent>
            </Dialog>

            <Button variant="outline" as-child>
              <Link :href="app.contacts.trash.url()">
                {{ t('回收站') }}
              </Link>
            </Button>
          </div>
        </div>

        <div class="flex flex-wrap items-end justify-end gap-3">
          <div class="flex items-center gap-3">
            <div class="relative">
              <Search
                class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
              />
              <Input v-model="searchInput" class="h-9 w-48 pl-9 lg:w-64" />
            </div>

            <FilterPopover
              v-model:open="filterPanelOpen"
              v-model:group="activeFilterPanelTab"
              :active-count="totalActiveFilterCount"
              :active-badge-label="filterBadgeLabel"
              :groups="filterGroups"
              default-group="type"
              @clear="clearAllFilters"
            >
              <template #type>
                <div class="space-y-3 p-4">
                  <div class="text-xs font-medium text-muted-foreground">
                    {{ t('联系人类型') }}
                  </div>
                  <Select
                    :model-value="selectedType"
                    @update:model-value="changeType"
                  >
                    <SelectTrigger class="w-full">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem
                        v-for="option in typeOptions"
                        :key="String(option.value)"
                        :value="String(option.value)"
                      >
                        {{ option.label }}
                      </SelectItem>
                    </SelectContent>
                  </Select>

                  <div class="flex items-center justify-between gap-3 pt-1">
                    <Label
                      for="contact-important-only"
                      class="text-sm font-medium"
                    >
                      {{ t('只看重点客户') }}
                    </Label>
                    <Switch
                      id="contact-important-only"
                      :model-value="importantOnly"
                      @update:model-value="updateImportantOnly"
                    />
                  </div>
                </div>
              </template>

              <template #attributes>
                <ContactFilterAttributePanel
                  :definitions="props.attribute_filter_definitions"
                  :model-value="attributeFilterValues"
                  @update:model-value="handleAttributeValuesUpdate"
                  @navigate="navigateWithFilters()"
                />
              </template>

              <template #tags>
                <ContactFilterTagPanel
                  :available-tags="props.available_tags"
                  :match-mode-options="props.tag_match_mode_options"
                  :include-ids="includeTagIds"
                  :exclude-ids="excludeTagIds"
                  :include-mode="includeTagMode"
                  :exclude-mode="excludeTagMode"
                  :untagged-only="untaggedOnly"
                  @update:include-ids="includeTagIds = $event"
                  @update:exclude-ids="excludeTagIds = $event"
                  @update:include-mode="includeTagMode = $event"
                  @update:exclude-mode="excludeTagMode = $event"
                  @update:untagged-only="untaggedOnly = $event"
                  @navigate="navigateWithFilters()"
                />
              </template>
            </FilterPopover>
          </div>
        </div>

        <div class="min-w-0 rounded-lg border">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="border-b bg-muted/30 text-muted-foreground">
                <tr class="text-left">
                  <th class="px-4 py-3">{{ t('名称') }}</th>
                  <th class="px-4 py-3">{{ t('联系方式') }}</th>
                  <th class="px-4 py-3">{{ t('外部系统编号') }}</th>
                  <th class="px-4 py-3">{{ t('类型') }}</th>
                  <th class="px-4 py-3">{{ t('首次来源') }}</th>
                  <th class="px-4 py-3">{{ t('最近活跃') }}</th>
                  <th class="w-[18rem] px-4 py-3 text-right whitespace-nowrap">
                    {{ t('操作') }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <ContactTableRow
                  v-for="c in props.contact_list"
                  :key="c.id"
                  :contact="c"
                  :is-selected="selectedContactId === c.id"
                  :delete-processing="deleteForm.processing"
                  @open-detail="openDetail"
                  @request-delete="openDeleteDialog"
                />

                <tr v-if="props.contact_list.length === 0">
                  <td
                    class="px-4 py-8 text-center text-muted-foreground"
                    colspan="6"
                  >
                    {{ t('暂无联系人') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div
            v-if="props.contact_list_pagination.last_page > 1"
            class="border-t p-4"
          >
            <PaginationNavigator
              :pagination="props.contact_list_pagination"
              :page-url="buildContactListPageUrl"
            />
          </div>
        </div>
      </div>
    </div>

    <Sheet :open="selectedContactId !== null" @update:open="onDetailOpenChange">
      <SheetContent side="right" class="w-full gap-0 p-0 sm:max-w-xl">
        <ContactDetailDrawer
          v-if="selectedContactId"
          :key="`${selectedContactId}:${detailRefreshNonce}`"
          :contact-id="selectedContactId"
          :can-merge="mergeCandidates.length > 0"
          :available-tags="props.available_tags"
          @request-merge="openMergeDialog"
        />
      </SheetContent>
    </Sheet>

    <ConfirmDeleteDialog
      :open="deletingContact !== null"
      :title="t('将此联系人移到回收站？')"
      :detail-title="deletingContact ? displayName(deletingContact) : ''"
      :detail-description="t('联系人会移到回收站，历史会话和资料仍会保留。')"
      :processing="deleteForm.processing"
      @update:open="closeDeleteDialog"
      @confirm="submitDelete"
    />

    <Dialog :open="mergeOpen" @update:open="closeMergeDialog">
      <DialogContent>
        <DialogHeader class="space-y-3">
          <DialogTitle>{{ t('合并这两个联系人？') }}</DialogTitle>
        </DialogHeader>

        <form class="space-y-4" @submit.prevent="submitMerge">
          <div class="space-y-2">
            <Label for="merge-contact">{{
              t('选择要并入当前联系人的记录')
            }}</Label>
            <Select
              v-model="mergeForm.merged_contact_id"
              :disabled="mergeForm.processing"
            >
              <SelectTrigger id="merge-contact" class="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="contactItem in mergeCandidates"
                  :key="contactItem.id"
                  :value="contactItem.id"
                >
                  {{ displayName(contactItem) }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div
            v-if="selectedMergeCandidate"
            class="space-y-3 rounded-md bg-muted/30 p-3 text-sm"
          >
            <div>
              <div class="font-medium">{{ t('合并后保留') }}</div>
              <div class="text-muted-foreground">
                {{
                  selectedContact
                    ? displayName(selectedContact)
                    : t('当前联系人')
                }}
              </div>
            </div>
            <div>
              <div class="font-medium">{{ t('合并后移到回收站') }}</div>
              <div class="text-muted-foreground">
                {{ displayName(selectedMergeCandidate) }}
              </div>
            </div>
          </div>

          <InputError :message="mergeErrorMessage" />

          <DialogFooter class="gap-2">
            <DialogClose as-child>
              <Button
                type="button"
                variant="secondary"
                :disabled="mergeForm.processing"
              >
                {{ t('取消') }}
              </Button>
            </DialogClose>
            <Button type="submit" :disabled="mergeForm.processing">
              {{ mergeForm.processing ? t('合并中...') : t('确认合并') }}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  </div>
</template>
