<!-- 客服管理页，使用 ShowListTeammatePagePropsData 管理账号、邀请和接待状态。 -->
<script setup lang="ts">
import FilterPopover from '@/components/common/FilterPopover.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogFooter,
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
import { Switch } from '@/components/ui/switch';
import { useDateTime } from '@/composables/useDateTime';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import app from '@/routes/app';
import type { ShowListTeammatePagePropsData } from '@/types/generated';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ChevronDown, MoreHorizontal, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import TeammateFilterBasicPanel from './TeammateFilterBasicPanel.vue';

defineOptions({ layout: AppLayout });

type TeammateRow = ShowListTeammatePagePropsData['user_list'][number];
type InvitationRow =
  ShowListTeammatePagePropsData['pending_invitations'][number];

const ONLINE_STATUS_ONLINE = 1;
const { t } = useI18n();
const { formatDateTime } = useDateTime();
const props = defineProps<ShowListTeammatePagePropsData>();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('客服') },
]);
const searchInput = ref(props.current_search ?? '');
const onlineStatusFilter = ref(props.current_online_status ?? 'all');
const filterPanelOpen = ref(false);
const searchTimeout = ref<ReturnType<typeof setTimeout> | null>(null);
const removingTeammate = ref<TeammateRow | null>(null);
const removeForm = useForm({});
const updatingStatusIds = ref<Record<string, boolean>>({});

const totalActiveFilterCount = computed(() =>
  onlineStatusFilter.value !== 'all' ? 1 : 0,
);

const navigate = () => {
  const query: Record<string, string> = {};
  const search = searchInput.value.trim();

  if (search !== '') {
    query.search = search;
  }

  if (onlineStatusFilter.value !== 'all') {
    query.online_status = onlineStatusFilter.value;
  }

  router.get(app.manage.teammates.index.url(), query, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

watch(searchInput, () => {
  if ((props.current_search ?? '') === searchInput.value.trim()) {
    return;
  }

  if (searchTimeout.value !== null) {
    clearTimeout(searchTimeout.value);
  }

  searchTimeout.value = setTimeout(navigate, 250);
});

watch(
  () => props.current_search ?? '',
  (value) => {
    if (searchTimeout.value !== null) {
      clearTimeout(searchTimeout.value);
      searchTimeout.value = null;
    }

    searchInput.value = value;
  },
);

watch(
  () => props.current_online_status ?? 'all',
  (value) => {
    onlineStatusFilter.value = value;
  },
);

const updateOnlineStatusFilter = (value: string) => {
  onlineStatusFilter.value = value;
  navigate();
};

const clearAllFilters = () => {
  onlineStatusFilter.value = 'all';
  navigate();
};

const openRemoveDialog = (user: TeammateRow) => {
  removingTeammate.value = user;
};

const confirmRemoveTeammate = () => {
  const target = removingTeammate.value;
  if (!target) {
    return;
  }

  removeForm.delete(app.manage.teammates.destroy.url({ id: target.user_id }), {
    preserveScroll: true,
    onSuccess: () => {
      removingTeammate.value = null;
    },
  });
};

const resendingInvitationIds = ref<Record<string, boolean>>({});
const revokingInvitation = ref<InvitationRow | null>(null);
const revokeInvitationForm = useForm({});

const resendInvitation = (invitation: InvitationRow) => {
  resendingInvitationIds.value[invitation.id] = true;
  router.post(
    app.manage.teammates.invitations.resend.url({ invitation: invitation.id }),
    {},
    {
      preserveScroll: true,
      onFinish: () => {
        resendingInvitationIds.value[invitation.id] = false;
      },
    },
  );
};

const confirmRevokeInvitation = () => {
  if (!revokingInvitation.value) {
    return;
  }

  revokeInvitationForm.delete(
    app.manage.teammates.invitations.destroy.url({
      invitation: revokingInvitation.value.id,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        revokingInvitation.value = null;
      },
    },
  );
};

const handleOnlineStatusChange = (userId: string, status: number) => {
  updatingStatusIds.value[userId] = true;
  router.put(
    app.manage.teammates.onlineStatus.update.url({ id: userId }),
    { online_status: Number(status) },
    {
      preserveScroll: true,
      preserveState: true,
      onFinish: () => {
        updatingStatusIds.value[userId] = false;
      },
    },
  );
};
</script>

<template>
  <div class="contents">
    <Head :title="t('客服')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />

        <div class="flex flex-wrap items-start justify-between gap-4">
          <HeadingSmall :title="t('客服')" :description="t('添加和管理客服')" />
          <DropdownMenu v-if="props.can_create">
            <DropdownMenuTrigger as-child>
              <Button>
                {{ t('添加客服') }}
                <ChevronDown class="ml-1 h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-44">
              <DropdownMenuItem as-child>
                <Link :href="app.manage.teammates.create.url()">{{
                  t('创建客服账号')
                }}</Link>
              </DropdownMenuItem>
              <DropdownMenuItem as-child>
                <Link :href="app.manage.teammates.invite.url()">{{
                  t('邀请新客服')
                }}</Link>
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>

        <div class="flex flex-wrap items-end justify-end gap-3">
          <div class="flex items-center gap-3">
            <div class="relative">
              <Search
                class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
              />
              <Input
                v-model="searchInput"
                :placeholder="t('搜索名称或邮箱')"
                class="h-9 w-48 pl-9 lg:w-64"
              />
            </div>
            <FilterPopover
              v-model:open="filterPanelOpen"
              :active-count="totalActiveFilterCount"
              @clear="clearAllFilters"
            >
              <TeammateFilterBasicPanel
                :online-status="onlineStatusFilter"
                :online-status-options="props.online_status_options"
                @update:online-status="updateOnlineStatusFilter"
              />
            </FilterPopover>
          </div>
        </div>

        <div class="rounded-lg border">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="border-b bg-muted/30 text-muted-foreground">
                <tr class="text-left">
                  <th class="px-4 py-3">{{ t('头像') }}</th>
                  <th class="px-4 py-3">{{ t('姓名') }}</th>
                  <th class="px-4 py-3">{{ t('接待昵称') }}</th>
                  <th class="px-4 py-3">{{ t('邮箱') }}</th>
                  <th class="px-4 py-3">{{ t('权限数') }}</th>
                  <th class="px-4 py-3">{{ t('在线状态') }}</th>
                  <th class="px-4 py-3">{{ t('最近活跃') }}</th>
                  <th class="px-4 py-3 text-right">{{ t('操作') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="u in props.user_list"
                  :key="u.user_id"
                  class="border-t bg-background"
                >
                  <td class="px-4 py-3">
                    <Avatar class="h-9 w-9">
                      <AvatarImage v-if="u.user_avatar" :src="u.user_avatar" />
                      <AvatarFallback>{{
                        (u.user_name || '').slice(0, 1)
                      }}</AvatarFallback>
                    </Avatar>
                  </td>
                  <td class="px-4 py-3 font-medium">
                    {{ u.user_name }}
                    <Badge v-if="u.is_owner" variant="secondary" class="ml-2">{{
                      t('系统管理员')
                    }}</Badge>
                  </td>
                  <td class="px-4 py-3">{{ u.user_nickname || '-' }}</td>
                  <td class="px-4 py-3">{{ u.user_email }}</td>
                  <td class="px-4 py-3">
                    <Badge variant="secondary">{{ u.permission_count }}</Badge>
                  </td>
                  <td class="px-4 py-3">
                    <Switch
                      :model-value="
                        Number(u.user_online_status.value) ===
                        ONLINE_STATUS_ONLINE
                      "
                      :disabled="!u.can_edit || updatingStatusIds[u.user_id]"
                      :aria-label="u.user_online_status.label"
                      @update:model-value="
                        (checked) =>
                          handleOnlineStatusChange(
                            u.user_id,
                            checked ? ONLINE_STATUS_ONLINE : 0,
                          )
                      "
                    />
                  </td>
                  <td class="px-4 py-3">
                    {{
                      u.user_last_active_at
                        ? formatDateTime(u.user_last_active_at)
                        : '-'
                    }}
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex justify-end gap-2">
                      <Button
                        v-if="u.can_edit"
                        as-child
                        variant="outline"
                        size="sm"
                      >
                        <Link
                          :href="
                            app.manage.teammates.edit.url({ id: u.user_id })
                          "
                          >{{ t('编辑') }}</Link
                        >
                      </Button>
                      <DropdownMenu v-if="u.can_delete">
                        <DropdownMenuTrigger as-child>
                          <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8"
                            :aria-label="t('更多操作')"
                          >
                            <MoreHorizontal class="h-4 w-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-36">
                          <DropdownMenuItem
                            class="text-destructive focus:text-destructive"
                            :disabled="removeForm.processing"
                            @select="openRemoveDialog(u)"
                          >
                            {{ t('移除') }}
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>
                  </td>
                </tr>
                <tr v-if="props.user_list.length === 0">
                  <td
                    class="px-4 py-8 text-center text-muted-foreground"
                    colspan="8"
                  >
                    {{ t('还没有客服') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-if="props.pending_invitations.length > 0" class="space-y-3">
          <HeadingSmall
            :title="t('待接受邀请')"
            :description="t('已发出但对方尚未接受的邀请')"
          />

          <div class="rounded-lg border">
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="border-b bg-muted/30 text-muted-foreground">
                  <tr class="text-left">
                    <th class="px-4 py-3">{{ t('邮箱') }}</th>
                    <th class="px-4 py-3">{{ t('状态') }}</th>
                    <th class="px-4 py-3">{{ t('邀请人') }}</th>
                    <th class="px-4 py-3">{{ t('过期时间') }}</th>
                    <th class="px-4 py-3 text-right">{{ t('操作') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="invitation in props.pending_invitations"
                    :key="invitation.id"
                    class="border-t bg-background"
                  >
                    <td class="px-4 py-3 font-medium">
                      {{ invitation.email }}
                    </td>
                    <td class="px-4 py-3">
                      <Badge
                        :variant="
                          invitation.status === 'expired'
                            ? 'outline'
                            : 'secondary'
                        "
                      >
                        {{ invitation.status_label }}
                      </Badge>
                    </td>
                    <td class="px-4 py-3 text-muted-foreground">
                      {{ invitation.invited_by_name || '-' }}
                    </td>
                    <td class="px-4 py-3 text-muted-foreground">
                      {{ formatDateTime(invitation.expires_at) }}
                    </td>
                    <td class="px-4 py-3">
                      <div
                        v-if="invitation.can_manage"
                        class="flex justify-end gap-2"
                      >
                        <Button
                          variant="outline"
                          size="sm"
                          :disabled="resendingInvitationIds[invitation.id]"
                          @click="resendInvitation(invitation)"
                        >
                          {{ t('重新发送') }}
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          class="text-destructive"
                          @click="revokingInvitation = invitation"
                        >
                          {{ t('撤销') }}
                        </Button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <Dialog
        :open="removingTeammate !== null"
        @update:open="(open) => !open && (removingTeammate = null)"
      >
        <DialogContent>
          <DialogHeader
            ><DialogTitle>{{ t('移除这名客服？') }}</DialogTitle></DialogHeader
          >
          <div
            v-if="removingTeammate"
            class="rounded-md bg-muted/30 p-3 text-sm"
          >
            <div class="font-medium">{{ removingTeammate.user_name }}</div>
            <div class="mt-1 text-muted-foreground">
              {{ t('移除后，这名客服将无法进入系统后台，但账号仍会保留。') }}
            </div>
          </div>
          <DialogFooter class="gap-2">
            <DialogClose as-child
              ><Button variant="secondary" :disabled="removeForm.processing">{{
                t('取消')
              }}</Button></DialogClose
            >
            <Button
              variant="destructive"
              :disabled="removeForm.processing || !removingTeammate?.can_delete"
              @click="confirmRemoveTeammate"
            >
              {{ removeForm.processing ? t('移除中...') : t('移除客服') }}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog
        :open="revokingInvitation !== null"
        @update:open="(open) => !open && (revokingInvitation = null)"
      >
        <DialogContent>
          <DialogHeader
            ><DialogTitle>{{ t('确认撤销邀请？') }}</DialogTitle></DialogHeader
          >
          <div
            v-if="revokingInvitation"
            class="rounded-md bg-muted/30 p-3 text-sm"
          >
            <div class="font-medium">{{ revokingInvitation.email }}</div>
            <div class="mt-1 text-muted-foreground">
              {{ t('撤销后该邀请链接将立即失效。') }}
            </div>
          </div>
          <DialogFooter class="gap-2">
            <DialogClose as-child>
              <Button
                variant="secondary"
                :disabled="revokeInvitationForm.processing"
                >{{ t('取消') }}</Button
              >
            </DialogClose>
            <Button
              variant="destructive"
              :disabled="revokeInvitationForm.processing"
              @click="confirmRevokeInvitation"
            >
              {{
                revokeInvitationForm.processing ? t('撤销中...') : t('确认撤销')
              }}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  </div>
</template>
