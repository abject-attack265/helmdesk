<!--
  联系人详情头部，展示头像、名称和类型。
  页面提供重点标记、合并、恢复和编辑入口。
-->
<script setup lang="ts">
import InputError from '@/components/common/InputError.vue';
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
import { useDateTime } from '@/composables/useDateTime';
import { useI18n } from '@/composables/useI18n';
import { useVisitorDisplay } from '@/composables/useVisitorDisplay';
import { getAvatarInitial } from '@/lib/initials';
import app from '@/routes/app';
import type {
  ContactDetailData,
  FormUpdateContactData,
} from '@/types/generated';
import { useForm } from '@inertiajs/vue3';
import { Star } from '@lucide/vue';
import { ref } from 'vue';

const props = defineProps<{
  contactId: string;
  contactDetail: ContactDetailData;
  canMerge?: boolean;
  readOnly?: boolean;
  restoreProcessing?: boolean;
  importanceProcessing?: boolean;
}>();

const emit = defineEmits<{
  requestMerge: [];
  requestRestore: [];
  requestToggleImportance: [];
  requestRefresh: [];
}>();

const { t } = useI18n();
const { formatDateTime } = useDateTime();
const { formatVisitorName } = useVisitorDisplay();

const editOpen = ref(false);

const editForm = useForm<FormUpdateContactData>({
  name: null,
  type: null,
  note: null,
  country: null,
  city: null,
});

const nameInitial = (detail: ContactDetailData): string =>
  getAvatarInitial(detail.name);

const openEdit = () => {
  editForm.name = props.contactDetail.name;
  editForm.type = String(props.contactDetail.type.value);
  editForm.clearErrors();
  editOpen.value = true;
};

const submitEdit = () => {
  editForm.put(
    app.contacts.update.url({
      id: props.contactId,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        editOpen.value = false;
        emit('requestRefresh');
      },
    },
  );
};
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-start gap-3">
      <Avatar class="h-12 w-12">
        <AvatarImage :src="contactDetail.avatar_url" />
        <AvatarFallback class="text-lg">
          {{ nameInitial(contactDetail) }}
        </AvatarFallback>
      </Avatar>
      <div class="min-w-0 flex-1">
        <h4 class="truncate text-lg font-semibold">
          {{ formatVisitorName(contactDetail.name, contactDetail.id) }}
        </h4>
        <div
          class="flex flex-wrap items-center gap-1.5 text-sm text-muted-foreground"
        >
          <Badge
            :variant="
              String(contactDetail.type.value) === 'contact'
                ? 'default'
                : 'secondary'
            "
          >
            {{ contactDetail.type.label }}
          </Badge>
          <span>·</span>
          <span>{{ contactDetail.source.label }}</span>
          <span>·</span>
          <span>{{
            formatDateTime(contactDetail.created_at, 'YYYY-MM-DD')
          }}</span>
          <template v-if="contactDetail.deleted_at">
            <span>·</span>
            <span
              >{{ t('移入回收站时间') }}
              {{ formatDateTime(contactDetail.deleted_at, 'YYYY-MM-DD') }}</span
            >
          </template>
        </div>
      </div>
    </div>

    <div class="flex flex-wrap gap-2">
      <Button
        v-if="!readOnly"
        variant="outline"
        size="sm"
        :disabled="importanceProcessing"
        :aria-pressed="contactDetail.is_important"
        @click="emit('requestToggleImportance')"
      >
        <Star
          class="mr-1 size-3.5"
          :class="{ 'fill-current': contactDetail.is_important }"
        />
        {{ contactDetail.is_important ? t('取消重点客户') : t('标为重点客户') }}
      </Button>
      <Badge
        v-else-if="contactDetail.is_important"
        variant="outline"
        class="gap-1.5 px-2"
      >
        <Star class="size-3.5 fill-current" />
        {{ t('重点客户') }}
      </Badge>
      <Button
        v-if="readOnly && contactDetail.deleted_at"
        variant="outline"
        size="sm"
        :disabled="restoreProcessing"
        @click="emit('requestRestore')"
      >
        {{ restoreProcessing ? t('恢复中...') : t('恢复') }}
      </Button>
      <Button
        v-if="!readOnly"
        variant="outline"
        size="sm"
        :disabled="!canMerge"
        @click="emit('requestMerge')"
      >
        {{ t('合并联系人') }}
      </Button>
      <Dialog v-if="!readOnly" v-model:open="editOpen">
        <DialogTrigger as-child>
          <Button variant="outline" size="sm" @click="openEdit">
            {{ t('编辑') }}
          </Button>
        </DialogTrigger>
        <DialogContent>
          <DialogHeader class="space-y-3">
            <DialogTitle>{{ t('编辑联系人') }}</DialogTitle>
          </DialogHeader>
          <form class="space-y-4" @submit.prevent="submitEdit">
            <div class="space-y-2">
              <Label for="edit-name">{{ t('名称') }}</Label>
              <Input
                id="edit-name"
                :model-value="editForm.name ?? ''"
                :disabled="editForm.processing"
                maxlength="255"
                @update:model-value="editForm.name = ($event as string) || null"
              />
              <InputError :message="editForm.errors.name" />
            </div>
            <div class="space-y-2">
              <Label for="edit-type">{{ t('联系人类型') }}</Label>
              <Select v-model="editForm.type" :disabled="editForm.processing">
                <SelectTrigger id="edit-type" class="h-9">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="visitor">{{ t('访客') }}</SelectItem>
                  <SelectItem value="contact">{{ t('联系人') }}</SelectItem>
                </SelectContent>
              </Select>
              <InputError :message="editForm.errors.type" />
            </div>

            <DialogFooter class="gap-2">
              <DialogClose as-child>
                <Button
                  type="button"
                  variant="secondary"
                  :disabled="editForm.processing"
                >
                  {{ t('取消') }}
                </Button>
              </DialogClose>
              <Button type="submit" :disabled="editForm.processing">
                {{ t('保存') }}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  </div>
</template>
