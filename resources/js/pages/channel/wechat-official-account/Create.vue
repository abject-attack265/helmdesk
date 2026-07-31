<!--
  微信公众号渠道创建页，使用 ShowCreateWechatOfficialAccountChannelPagePropsData 填写基本信息和接待方案。
-->
<script setup lang="ts">
import WechatOfficialAccount from '@/actions/App/Actions/Channel/WechatOfficialAccount';
import Plan from '@/actions/App/Actions/Reception/Plan';
import FormActions from '@/components/common/FormActions.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import InputError from '@/components/common/InputError.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ShowCreateWechatOfficialAccountChannelPagePropsData } from '@/types/generated';
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

defineOptions({ layout: AppLayout });

const props =
  defineProps<ShowCreateWechatOfficialAccountChannelPagePropsData>();
const { t } = useI18n();
const usableOptions = computed(() =>
  props.reception_plan_options.filter((option) => option.is_usable),
);
const hasUsableOptions = computed(() => usableOptions.value.length > 0);
const initialPlanId =
  usableOptions.value.length === 1 ? usableOptions.value[0].id : '';
const initialVisitorLocaleOption = props.reception_language_options[0];
if (!initialVisitorLocaleOption) {
  throw new Error('访客语言选项不能为空。');
}
const initialVisitorLocale = String(initialVisitorLocaleOption.value);
const channelName = ref('');
const channelDescription = ref('');
const selectedPlanId = ref(initialPlanId);
const defaultVisitorLocale = ref(initialVisitorLocale);
const creating = ref(false);
const canSubmit = computed(
  () => hasUsableOptions.value && selectedPlanId.value !== '',
);
const isDirty = computed(
  () =>
    channelName.value !== '' ||
    channelDescription.value !== '' ||
    selectedPlanId.value !== initialPlanId ||
    defaultVisitorLocale.value !== initialVisitorLocale,
);
const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('接入渠道') },
  {
    title: t('微信公众号'),
    href: WechatOfficialAccount.ListWechatOfficialAccountChannelsAction.url(),
  },
  { title: t('添加微信公众号渠道') },
]);

/** 离开前确认是否放弃尚未提交的内容。 */
function confirmLeaveIfDirty(): boolean {
  if (creating.value) {
    return false;
  }

  if (!isDirty.value) {
    return true;
  }

  return window.confirm(t('内容尚未保存，确定离开吗？未保存的修改会丢失。'));
}

/** 刷新或关闭页面时交由浏览器提示未保存内容。 */
function onBeforeUnload(event: BeforeUnloadEvent): void {
  if (!isDirty.value && !creating.value) {
    return;
  }

  event.preventDefault();
  event.returnValue = '';
}

let removeBeforeListener: (() => void) | null = null;

onMounted(() => {
  removeBeforeListener = router.on('before', (event) => {
    if (event.detail.visit.method === 'get' && !confirmLeaveIfDirty()) {
      event.preventDefault();
    }
  });
  window.addEventListener('beforeunload', onBeforeUnload);
});

onBeforeUnmount(() => {
  removeBeforeListener?.();
  window.removeEventListener('beforeunload', onBeforeUnload);
});
</script>

<template>
  <div class="contents">
    <Head :title="t('添加微信公众号渠道')" />
    <div class="px-4 py-6 sm:px-6">
      <div class="space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />
        <HeadingSmall
          :title="t('添加微信公众号渠道')"
          :description="t('先填写基本信息，添加后再填写微信公众号开发者配置。')"
        />
        <Form
          :action="
            WechatOfficialAccount.CreateWechatOfficialAccountChannelAction.url()
          "
          method="post"
          class="space-y-6"
          disable-while-processing
          @start="creating = true"
          @finish="creating = false"
          v-slot="{ errors, processing }"
        >
          <div class="grid gap-2">
            <Label for="wx_name" required>{{ t('渠道名称') }}</Label>
            <Input
              id="wx_name"
              v-model="channelName"
              name="name"
              required
              autofocus
              autocomplete="off"
              maxlength="100"
              :disabled="processing"
            />
            <InputError :message="errors.name" />
          </div>
          <div class="grid gap-2">
            <Label for="wx_description">{{ t('用途说明（选填）') }}</Label>
            <Textarea
              id="wx_description"
              v-model="channelDescription"
              name="description"
              rows="3"
              maxlength="2000"
              :disabled="processing"
            />
            <InputError :message="errors.description" />
          </div>
          <div class="grid gap-2">
            <Label for="wx_plan" required>{{ t('接待方案') }}</Label>
            <Select
              v-model="selectedPlanId"
              :disabled="processing || !hasUsableOptions"
            >
              <SelectTrigger id="wx_plan" class="w-full">
                <SelectValue :placeholder="t('请选择接待方案')" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="option in props.reception_plan_options"
                  :key="option.id"
                  :value="option.id"
                  :disabled="!option.is_usable"
                >
                  {{ option.name }}
                  <span
                    v-if="!option.is_usable && option.unusable_reason_label"
                    class="ml-2 text-xs text-muted-foreground"
                  >
                    ({{ option.unusable_reason_label }})
                  </span>
                </SelectItem>
              </SelectContent>
            </Select>
            <input
              type="hidden"
              name="reception_plan_id"
              :value="selectedPlanId"
            />
            <InputError :message="errors.reception_plan_id" />
          </div>
          <div class="grid gap-2">
            <Label for="wx_locale" required>{{ t('访客默认语言') }}</Label>
            <Select v-model="defaultVisitorLocale" :disabled="processing">
              <SelectTrigger id="wx_locale" class="w-full"
                ><SelectValue
              /></SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="option in props.reception_language_options"
                  :key="String(option.value)"
                  :value="String(option.value)"
                  >{{ option.label }}</SelectItem
                >
              </SelectContent>
            </Select>
            <input
              type="hidden"
              name="default_visitor_locale"
              :value="defaultVisitorLocale"
            />
            <InputError :message="errors.default_visitor_locale" />
          </div>
          <div v-if="!hasUsableOptions" class="flex items-center gap-3">
            <p class="text-sm text-muted-foreground">
              {{ t('当前没有可用的接待方案。') }}
            </p>
            <Button variant="outline" size="sm" as-child>
              <Link :href="Plan.ShowReceptionPlanIndexPageAction.url()">{{
                t('查看接待方案')
              }}</Link>
            </Button>
          </div>
          <FormActions
            :submit-label="t('添加')"
            :processing="processing"
            :submit-disabled="!canSubmit"
            :cancel-href="
              WechatOfficialAccount.ListWechatOfficialAccountChannelsAction.url()
            "
            :cancel-label="t('取消')"
          />
        </Form>
      </div>
    </div>
  </div>
</template>
