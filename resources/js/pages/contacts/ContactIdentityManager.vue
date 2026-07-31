<!--
  联系人联系方式与账号面板，用于查看、添加、修改和删除联系信息。
-->
<script setup lang="ts">
import InputError from '@/components/common/InputError.vue';
import PhoneDialCodeCombobox from '@/components/common/PhoneDialCodeCombobox.vue';
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
import { useI18n } from '@/composables/useI18n';
import { EMAIL_MAX_LENGTH, isLikelyValidEmail } from '@/lib/email';
import {
  buildPhoneNumber,
  getDefaultPhonePrefix,
  isLikelyValidDialCode,
  isLikelyValidLocalPhone,
  isLikelyValidPhone,
  splitPhoneNumber,
} from '@/lib/phone';
import app from '@/routes/app';
import type {
  ContactDetailData,
  ContactIdentityData,
  FormCreateContactIdentityData,
  FormReplaceContactIdentityData,
} from '@/types/generated';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
  contactId: string;
  contactDetail: ContactDetailData;
  readOnly?: boolean;
}>();

const emit = defineEmits<{
  requestRefresh: [];
}>();

const { locale, t } = useI18n();

const addIdentityOpen = ref(false);
const replacingIdentity = ref<ContactIdentityData | null>(null);
const deletingIdentity = ref<ContactIdentityData | null>(null);
const defaultPhonePrefix = computed(() => getDefaultPhonePrefix(locale.value));
const identityPhoneDialCode = ref(defaultPhonePrefix.value);
const identityPhoneLocalNumber = ref('');
const replacePhoneDialCode = ref(defaultPhonePrefix.value);
const replacePhoneLocalNumber = ref('');

const identityForm = useForm<FormCreateContactIdentityData>({
  type: 'email',
  value: '',
  namespace: null,
});
const replaceIdentityForm = useForm<FormReplaceContactIdentityData>({
  value: '',
});
const deleteIdentityForm = useForm({});

const identityTypeOptions = [
  { value: 'email', label: t('邮箱') },
  { value: 'phone', label: t('手机号') },
];

const identityValueErrorMessage = computed(() => {
  if (identityForm.type === 'phone') {
    const phone = identityPhoneLocalNumber.value.trim();

    if (phone === '') {
      return identityForm.errors.value;
    }

    if (
      !isLikelyValidDialCode(identityPhoneDialCode.value) ||
      !isLikelyValidLocalPhone(phone) ||
      !isLikelyValidPhone(buildPhoneNumber(identityPhoneDialCode.value, phone))
    ) {
      return t('请输入有效的手机号');
    }

    return identityForm.errors.value;
  }

  if (identityForm.type === 'email') {
    const email = identityForm.value.trim();

    if (email === '') {
      return identityForm.errors.value;
    }

    if (!isLikelyValidEmail(email)) {
      return t('请输入有效的邮箱地址');
    }

    return identityForm.errors.value;
  }

  return identityForm.errors.value;
});

const isIdentityValueInvalid = computed(() => {
  if (identityForm.type === 'phone') {
    const phone = identityPhoneLocalNumber.value.trim();

    if (phone === '') {
      return false;
    }

    return (
      !isLikelyValidDialCode(identityPhoneDialCode.value) ||
      !isLikelyValidLocalPhone(phone) ||
      !isLikelyValidPhone(buildPhoneNumber(identityPhoneDialCode.value, phone))
    );
  }

  if (identityForm.type === 'email') {
    const email = identityForm.value.trim();

    if (email === '') {
      return false;
    }

    return !isLikelyValidEmail(email);
  }

  return false;
});

const replaceIdentityValueErrorMessage = computed(() => {
  const errors = replaceIdentityForm.errors as Record<
    string,
    string | undefined
  >;
  const fallbackError = errors.value || errors.identity;

  if (replacingIdentity.value?.type.value === 'phone') {
    const phone = replacePhoneLocalNumber.value.trim();

    if (phone === '') {
      return fallbackError;
    }

    if (
      !isLikelyValidDialCode(replacePhoneDialCode.value) ||
      !isLikelyValidLocalPhone(phone) ||
      !isLikelyValidPhone(buildPhoneNumber(replacePhoneDialCode.value, phone))
    ) {
      return t('请输入有效的手机号');
    }

    return fallbackError;
  }

  if (replacingIdentity.value?.type.value === 'email') {
    const email = replaceIdentityForm.value.trim();

    if (email === '') {
      return fallbackError;
    }

    if (!isLikelyValidEmail(email)) {
      return t('请输入有效的邮箱地址');
    }

    return fallbackError;
  }

  return fallbackError;
});

const isReplaceIdentityValueInvalid = computed(() => {
  if (replacingIdentity.value?.type.value === 'phone') {
    const phone = replacePhoneLocalNumber.value.trim();

    if (phone === '') {
      return false;
    }

    return (
      !isLikelyValidDialCode(replacePhoneDialCode.value) ||
      !isLikelyValidLocalPhone(phone) ||
      !isLikelyValidPhone(buildPhoneNumber(replacePhoneDialCode.value, phone))
    );
  }

  if (replacingIdentity.value?.type.value === 'email') {
    const email = replaceIdentityForm.value.trim();

    if (email === '') {
      return false;
    }

    return !isLikelyValidEmail(email);
  }

  return false;
});

const identityNamespaceLabel = (namespace: string): string => {
  if (namespace === '') {
    return t('默认');
  }

  return namespace;
};

const canManageIdentity = (identity: ContactIdentityData): boolean => {
  return ['email', 'phone'].includes(String(identity.type.value));
};

const deleteIdentityErrorMessage = computed(() => {
  const errors = deleteIdentityForm.errors as Record<
    string,
    string | undefined
  >;

  return errors.identity;
});

watch(defaultPhonePrefix, (value) => {
  if (identityPhoneLocalNumber.value.trim() !== '') {
    if (replacePhoneLocalNumber.value.trim() !== '') {
      return;
    }
  }

  if (identityPhoneLocalNumber.value.trim() === '') {
    identityPhoneDialCode.value = value;
  }

  if (replacePhoneLocalNumber.value.trim() === '') {
    replacePhoneDialCode.value = value;
  }
});

watch(
  () => identityForm.type,
  (type) => {
    if (type === 'phone') {
      identityPhoneDialCode.value = defaultPhonePrefix.value;
      identityForm.value = '';

      return;
    }

    identityPhoneDialCode.value = defaultPhonePrefix.value;
    identityPhoneLocalNumber.value = '';
    identityForm.clearErrors('value');
  },
);

watch(addIdentityOpen, (open) => {
  if (open) {
    if (identityForm.type === 'phone') {
      identityPhoneDialCode.value = defaultPhonePrefix.value;
    }

    return;
  }

  if (identityForm.processing) {
    return;
  }

  identityForm.reset();
  identityForm.type = 'email';
  identityForm.namespace = null;
  identityForm.clearErrors();
  identityPhoneDialCode.value = defaultPhonePrefix.value;
  identityPhoneLocalNumber.value = '';
});

const openReplaceIdentity = (identity: ContactIdentityData) => {
  replacingIdentity.value = identity;
  replaceIdentityForm.clearErrors();

  if (String(identity.type.value) === 'phone') {
    const parsedPhone = splitPhoneNumber(identity.display_value ?? '');
    replacePhoneDialCode.value =
      parsedPhone.dialCode || defaultPhonePrefix.value;
    replacePhoneLocalNumber.value = parsedPhone.localNumber;
    replaceIdentityForm.value = '';

    return;
  }

  replacePhoneDialCode.value = defaultPhonePrefix.value;
  replacePhoneLocalNumber.value = '';
  replaceIdentityForm.value = identity.display_value ?? '';
};

const closeReplaceIdentity = (open: boolean) => {
  if (open || replaceIdentityForm.processing) {
    return;
  }

  replacingIdentity.value = null;
  replaceIdentityForm.reset();
  replaceIdentityForm.clearErrors();
  replacePhoneDialCode.value = defaultPhonePrefix.value;
  replacePhoneLocalNumber.value = '';
};

const openDeleteIdentity = (identity: ContactIdentityData) => {
  deletingIdentity.value = identity;
  deleteIdentityForm.clearErrors();
};

const closeDeleteIdentity = (open: boolean) => {
  if (open || deleteIdentityForm.processing) {
    return;
  }

  deletingIdentity.value = null;
  deleteIdentityForm.clearErrors();
};

const submitAddIdentity = () => {
  if (props.readOnly) {
    return;
  }

  if (identityForm.type === 'phone') {
    identityForm.value = buildPhoneNumber(
      identityPhoneDialCode.value,
      identityPhoneLocalNumber.value,
    );
  } else {
    identityForm.value = identityForm.value.trim();
  }

  if (isIdentityValueInvalid.value) {
    identityForm.setError(
      'value',
      identityForm.type === 'email'
        ? t('请输入有效的邮箱地址')
        : t('请输入有效的手机号'),
    );

    return;
  }

  identityForm.clearErrors('value');
  identityForm.post(
    app.contacts.identities.store.url({
      contactId: props.contactId,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        identityForm.reset();
        identityForm.type = 'email';
        identityForm.namespace = null;
        identityPhoneDialCode.value = defaultPhonePrefix.value;
        identityPhoneLocalNumber.value = '';
        addIdentityOpen.value = false;
        emit('requestRefresh');
      },
    },
  );
};

const submitReplaceIdentity = () => {
  if (props.readOnly || !replacingIdentity.value) {
    return;
  }

  if (String(replacingIdentity.value.type.value) === 'phone') {
    replaceIdentityForm.value = buildPhoneNumber(
      replacePhoneDialCode.value,
      replacePhoneLocalNumber.value,
    );
  } else {
    replaceIdentityForm.value = replaceIdentityForm.value.trim();
  }

  if (isReplaceIdentityValueInvalid.value) {
    replaceIdentityForm.setError(
      'value',
      String(replacingIdentity.value.type.value) === 'email'
        ? t('请输入有效的邮箱地址')
        : t('请输入有效的手机号'),
    );

    return;
  }

  replaceIdentityForm.clearErrors('value');
  replaceIdentityForm.put(
    app.contacts.identities.replace.url({
      contactId: props.contactId,
      identityId: replacingIdentity.value.id,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        replacingIdentity.value = null;
        replaceIdentityForm.reset();
        replacePhoneDialCode.value = defaultPhonePrefix.value;
        replacePhoneLocalNumber.value = '';
        emit('requestRefresh');
      },
    },
  );
};

const submitDeleteIdentity = () => {
  if (props.readOnly || !deletingIdentity.value) {
    return;
  }

  deleteIdentityForm.delete(
    app.contacts.identities.destroy.url({
      contactId: props.contactId,
      identityId: deletingIdentity.value.id,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        deletingIdentity.value = null;
        emit('requestRefresh');
      },
    },
  );
};
</script>

<template>
  <div>
    <div>
      <div class="mb-3 flex items-center justify-between">
        <h5 class="text-sm font-semibold">{{ t('联系方式与账号') }}</h5>
        <Dialog v-if="!readOnly" v-model:open="addIdentityOpen">
          <DialogTrigger as-child>
            <Button variant="outline" size="sm">
              {{ t('添加') }}
            </Button>
          </DialogTrigger>
          <DialogContent>
            <DialogHeader class="space-y-3">
              <DialogTitle>{{ t('添加联系方式') }}</DialogTitle>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submitAddIdentity">
              <div class="space-y-2">
                <Label for="identity-type">{{ t('联系方式类型') }}</Label>
                <Select
                  v-model="identityForm.type"
                  :disabled="identityForm.processing"
                >
                  <SelectTrigger id="identity-type" class="h-9">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="opt in identityTypeOptions"
                      :key="opt.value"
                      :value="opt.value"
                    >
                      {{ opt.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <InputError :message="identityForm.errors.type" />
              </div>
              <div class="space-y-2">
                <Label for="identity-value">{{ t('邮箱或手机号') }}</Label>
                <div v-if="identityForm.type === 'phone'" class="flex gap-2">
                  <PhoneDialCodeCombobox
                    v-model="identityPhoneDialCode"
                    class="w-36 shrink-0"
                    :disabled="identityForm.processing"
                  />
                  <Input
                    id="identity-value"
                    type="tel"
                    inputmode="tel"
                    :model-value="identityPhoneLocalNumber"
                    :disabled="identityForm.processing"
                    @update:model-value="
                      identityPhoneLocalNumber = $event as string
                    "
                  />
                </div>
                <Input
                  v-else
                  id="identity-value"
                  :type="identityForm.type === 'email' ? 'email' : 'text'"
                  :inputmode="identityForm.type === 'email' ? 'email' : 'text'"
                  :autocomplete="
                    identityForm.type === 'email' ? 'email' : 'off'
                  "
                  :maxlength="
                    identityForm.type === 'email' ? EMAIL_MAX_LENGTH : undefined
                  "
                  v-model="identityForm.value"
                  :disabled="identityForm.processing"
                />
                <InputError :message="identityValueErrorMessage" />
              </div>

              <DialogFooter class="gap-2">
                <DialogClose as-child>
                  <Button
                    type="button"
                    variant="secondary"
                    :disabled="identityForm.processing"
                  >
                    {{ t('取消') }}
                  </Button>
                </DialogClose>
                <Button
                  type="submit"
                  :disabled="identityForm.processing || isIdentityValueInvalid"
                >
                  {{ t('保存') }}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>
      </div>

      <div class="space-y-2">
        <div
          v-for="identity in contactDetail.identities"
          :key="identity.id"
          class="flex items-center justify-between rounded-md border px-3 py-2 text-sm"
        >
          <div class="flex items-center gap-2">
            <Badge
              class="border-transparent bg-muted text-muted-foreground hover:bg-muted/80"
            >
              {{ identity.type.label }}
            </Badge>
            <div class="flex flex-col gap-1">
              <span class="text-muted-foreground">
                {{ identity.display_value || '-' }}
              </span>
              <span
                v-if="identity.namespace"
                class="text-xs text-muted-foreground"
              >
                {{ t('所属渠道') }}:
                {{ identityNamespaceLabel(identity.namespace) }}
              </span>
            </div>
          </div>
          <div
            v-if="!readOnly && canManageIdentity(identity)"
            class="flex items-center gap-2"
          >
            <Button
              variant="ghost"
              size="sm"
              class="h-8 px-2 text-muted-foreground"
              @click="openReplaceIdentity(identity)"
            >
              {{ t('修改') }}
            </Button>
            <Button
              variant="ghost"
              size="sm"
              class="h-8 px-2 text-destructive hover:text-destructive"
              @click="openDeleteIdentity(identity)"
            >
              {{ t('删除') }}
            </Button>
          </div>
        </div>
        <div
          v-if="contactDetail.identities.length === 0"
          class="py-4 text-center text-sm text-muted-foreground"
        >
          {{ t('暂无联系方式或账号') }}
        </div>
      </div>
    </div>

    <Dialog
      :open="replacingIdentity !== null"
      @update:open="closeReplaceIdentity"
    >
      <DialogContent>
        <DialogHeader class="space-y-3">
          <DialogTitle>{{ t('修改联系方式') }}</DialogTitle>
        </DialogHeader>
        <form class="space-y-4" @submit.prevent="submitReplaceIdentity">
          <div class="space-y-2">
            <Label>{{ t('当前联系方式') }}</Label>
            <div
              class="rounded-md bg-muted/30 px-3 py-2 text-sm text-muted-foreground"
            >
              {{ replacingIdentity?.display_value || '-' }}
            </div>
          </div>
          <div class="space-y-2">
            <Label for="replace-identity-value">{{ t('新联系方式') }}</Label>
            <div
              v-if="replacingIdentity?.type.value === 'phone'"
              class="flex gap-2"
            >
              <PhoneDialCodeCombobox
                v-model="replacePhoneDialCode"
                class="w-36 shrink-0"
                :disabled="replaceIdentityForm.processing"
              />
              <Input
                id="replace-identity-value"
                type="tel"
                inputmode="tel"
                :model-value="replacePhoneLocalNumber"
                :disabled="replaceIdentityForm.processing"
                @update:model-value="replacePhoneLocalNumber = $event as string"
              />
            </div>
            <Input
              v-else
              id="replace-identity-value"
              :type="
                replacingIdentity?.type.value === 'email' ? 'email' : 'text'
              "
              :inputmode="
                replacingIdentity?.type.value === 'email' ? 'email' : 'text'
              "
              :autocomplete="
                replacingIdentity?.type.value === 'email' ? 'email' : 'off'
              "
              :maxlength="
                replacingIdentity?.type.value === 'email'
                  ? EMAIL_MAX_LENGTH
                  : undefined
              "
              v-model="replaceIdentityForm.value"
              :disabled="replaceIdentityForm.processing"
            />
            <InputError :message="replaceIdentityValueErrorMessage" />
          </div>

          <DialogFooter class="gap-2">
            <DialogClose as-child>
              <Button
                type="button"
                variant="secondary"
                :disabled="replaceIdentityForm.processing"
              >
                {{ t('取消') }}
              </Button>
            </DialogClose>
            <Button
              type="submit"
              :disabled="
                replaceIdentityForm.processing || isReplaceIdentityValueInvalid
              "
            >
              {{ t('保存') }}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <Dialog
      :open="deletingIdentity !== null"
      @update:open="closeDeleteIdentity"
    >
      <DialogContent>
        <DialogHeader class="space-y-3">
          <DialogTitle>{{ t('确认删除这条联系方式？') }}</DialogTitle>
        </DialogHeader>
        <div
          class="rounded-md bg-muted/30 px-3 py-3 text-sm text-muted-foreground"
        >
          {{ deletingIdentity?.display_value || '-' }}
        </div>
        <InputError :message="deleteIdentityErrorMessage" />
        <DialogFooter class="gap-2">
          <DialogClose as-child>
            <Button
              variant="secondary"
              :disabled="deleteIdentityForm.processing"
            >
              {{ t('取消') }}
            </Button>
          </DialogClose>
          <Button
            variant="destructive"
            :disabled="deleteIdentityForm.processing"
            @click="submitDeleteIdentity"
          >
            {{ deleteIdentityForm.processing ? t('删除中...') : t('确认删除') }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
