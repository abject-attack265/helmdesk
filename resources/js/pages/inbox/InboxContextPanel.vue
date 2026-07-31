<!--
  收件箱右侧面板展示渠道与联系人信息，编辑联系人资料，并提供 AI 助手。
-->
<script setup lang="ts">
import InputError from '@/components/common/InputError.vue';
import PhoneDialCodeCombobox from '@/components/common/PhoneDialCodeCombobox.vue';
import TagSelector from '@/components/common/TagSelector.vue';
import AttributeFieldRenderer from '@/components/custom-attribute/AttributeFieldRenderer.vue';
import { Input } from '@/components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { useDateTime } from '@/composables/useDateTime';
import { useI18n } from '@/composables/useI18n';
import { useToast } from '@/composables/useToast';
import { EMAIL_MAX_LENGTH, isLikelyValidEmail } from '@/lib/email';
import {
  buildPhoneNumber,
  getDefaultPhonePrefix,
  isLikelyValidDialCode,
  isLikelyValidLocalPhone,
  isLikelyValidPhone,
  splitPhoneNumber,
} from '@/lib/phone';
import AiAssistantWidget from '@/pages/inbox/AiAssistantWidget.vue';
import { runAllowedInboxContextReload } from '@/pages/inbox/inboxContextReloadGate';
import IntegrationPanel from '@/pages/inbox/IntegrationPanel.vue';
import app from '@/routes/app';
import type {
  ChannelType,
  ContactAttributeFieldData,
  ContactPanelData,
  ConversationSummaryData,
  FormCreateContactIdentityData,
  FormUpdateContactAttributeValuesData,
  FormUpdateContactData,
  InboxContactProfileData,
  TagOptionData,
  TelegramConversationChannelContextData,
  WebConversationChannelContextData,
  WechatOfficialAccountConversationChannelContextData,
} from '@/types/generated';
import WechatIcon from '@/components/icons/WechatIcon.vue';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { Globe, Send } from '@lucide/vue';
import {
  computed,
  nextTick,
  onUnmounted,
  reactive,
  ref,
  watch,
  type Component,
} from 'vue';

const props = defineProps<{
  contactProfile: InboxContactProfileData;
  conversation: ConversationSummaryData;
  availableContactTags: TagOptionData[];
  targetLocale: string;
  canTranslate: boolean;
  translationEnabled: boolean;
  writeBlocked: boolean;
}>();

const emit = defineEmits<{
  'write-pending-change': [pending: boolean];
  'write-failed': [];
}>();

const { locale, t } = useI18n();
const { formatDateTime } = useDateTime();
const { toast } = useToast();
const defaultPhonePrefix = computed(() => getDefaultPhonePrefix(locale.value));

type TabKey = 'profile' | 'copilot' | 'business';
type EditableProfileForm = Omit<
  FormUpdateContactData,
  'name' | 'country' | 'city'
> & {
  name: string;
  country: string;
  city: string;
};

const tabs = computed<Array<{ key: TabKey; label: string }>>(() => {
  const items: Array<{ key: TabKey; label: string }> = [
    { key: 'profile', label: t('资料') },
    { key: 'copilot', label: t('AI 助手') },
  ];
  if (integrationPanels.value.length > 0) {
    items.push({ key: 'business', label: t('业务') });
  }

  return items;
});

const activeTab = ref<TabKey>('profile');

const channelIconMap: Record<ChannelType, Component> = {
  web: Globe,
  telegram: Send,
  wechat_oa: WechatIcon,
};
const channelIcon = computed<Component>(() => {
  const type = props.conversation.channel_type;
  if (type === null) {
    throw new Error(`收件箱会话 ${props.conversation.id} 缺少渠道类型`);
  }

  return channelIconMap[type];
});
const channelDisplayName = computed(() => {
  const name = props.conversation.channel_name?.trim();
  if (!name) {
    throw new Error(`收件箱会话 ${props.conversation.id} 缺少渠道名称`);
  }

  return name;
});
const conversationAssigneeDisplay = computed(
  () =>
    props.conversation.assigned_user_name ??
    props.conversation.inbox_status_label,
);

type ChannelContextRow = {
  key: string;
  label: string;
  value: string;
  href?: string;
};

const webContext = computed<WebConversationChannelContextData | null>(() => {
  const ctx = props.conversation.channel_context;
  return ctx && ctx.channel_type === 'web'
    ? (ctx as WebConversationChannelContextData)
    : null;
});
const telegramContext = computed<TelegramConversationChannelContextData | null>(
  () => {
    const ctx = props.conversation.channel_context;
    return ctx && ctx.channel_type === 'telegram'
      ? (ctx as TelegramConversationChannelContextData)
      : null;
  },
);
const wechatOfficialAccountContext =
  computed<WechatOfficialAccountConversationChannelContextData | null>(() => {
    const ctx = props.conversation.channel_context;
    return ctx && ctx.channel_type === 'wechat_oa'
      ? (ctx as WechatOfficialAccountConversationChannelContextData)
      : null;
  });

const channelContextTitle = computed(() => {
  if (webContext.value) return t('来源与设备');
  if (telegramContext.value) return t('Telegram 信息');
  if (wechatOfficialAccountContext.value) return t('微信公众号信息');
  return '';
});

const channelContextRows = computed<ChannelContextRow[]>(() => {
  const rows: ChannelContextRow[] = [];
  const web = webContext.value;
  if (web) {
    const browser = [web.browser, web.browser_version]
      .filter(Boolean)
      .join(' ');
    if (web.current_url) {
      rows.push({
        key: 'current_url',
        label: t('当前页'),
        value: web.current_url,
        href: web.current_url,
      });
    }
    if (web.referrer) {
      rows.push({
        key: 'referrer',
        label: t('访问来源'),
        value: web.referrer,
        href: web.referrer,
      });
    }
    if (web.landing_url) {
      rows.push({
        key: 'landing_url',
        label: t('首次访问页'),
        value: web.landing_url,
        href: web.landing_url,
      });
    }
    if (web.device_type) {
      rows.push({ key: 'device', label: t('设备'), value: web.device_type });
    }
    if (browser) {
      rows.push({ key: 'browser', label: t('浏览器'), value: browser });
    }
    if (web.platform) {
      rows.push({ key: 'platform', label: t('操作系统'), value: web.platform });
    }
    return rows;
  }

  const tg = telegramContext.value;
  if (tg) {
    if (tg.username) {
      rows.push({
        key: 'username',
        label: t('用户名'),
        value: tg.username.startsWith('@') ? tg.username : `@${tg.username}`,
      });
    }
    if (tg.language_code) {
      rows.push({ key: 'language', label: t('语言'), value: tg.language_code });
    }
    if (tg.chat_type) {
      rows.push({
        key: 'chat_type',
        label: t('会话类型'),
        value: tg.chat_type,
      });
    }
    if (tg.is_premium !== null) {
      rows.push({
        key: 'premium',
        // Telegram 专有功能名，中英文一致，不走 i18n。
        label: 'Premium',
        value: tg.is_premium ? t('是') : t('否'),
      });
    }
  }

  const wechat = wechatOfficialAccountContext.value;
  if (wechat?.openid) {
    rows.push({ key: 'openid', label: 'OpenID', value: wechat.openid });
  }
  if (wechat?.nickname) {
    rows.push({ key: 'nickname', label: t('昵称'), value: wechat.nickname });
  }
  if (wechat?.language) {
    rows.push({ key: 'language', label: t('语言'), value: wechat.language });
  }

  return rows;
});
const profileForm = useForm<EditableProfileForm>({
  name: '',
  type: null,
  note: null,
  country: '',
  city: '',
});
const noteForm = useForm({
  note: props.contactProfile.note ?? '',
});
const emailForm = useForm<FormCreateContactIdentityData>({
  type: 'email',
  value: '',
  namespace: null,
});
const phoneForm = useForm<FormCreateContactIdentityData>({
  type: 'phone',
  value: '',
  namespace: null,
});
const phoneDialCode = ref(defaultPhonePrefix.value);
const phoneLocalNumber = ref('');
const typeForm = useForm<FormUpdateContactData>({
  name: null,
  type: props.contactProfile.type,
  note: null,
  country: null,
  city: null,
});
const attrForm = useForm<FormUpdateContactAttributeValuesData>({
  attributes: {},
});
const attrValues = reactive<Record<string, unknown>>({});
const attrSaving = ref(false);
const tagProcessing = ref(false);
const syncingFromProps = ref(false);
const lastSavedProfile = ref('');
const lastSavedEmail = ref(props.contactProfile.primary_email ?? '');
const lastSavedPhone = ref(props.contactProfile.primary_phone ?? '');
const lastSavedNote = ref(props.contactProfile.note ?? '');
const lastSavedAttributes = ref('');

const profileSaveTimer = ref<number | null>(null);
const emailSaveTimer = ref<number | null>(null);
const phoneSaveTimer = ref<number | null>(null);
const noteSaveTimer = ref<number | null>(null);
const attributeSaveTimer = ref<number | null>(null);

const contextMutationProcessing = computed(
  () =>
    profileForm.processing ||
    noteForm.processing ||
    emailForm.processing ||
    phoneForm.processing ||
    typeForm.processing ||
    attrForm.processing ||
    attrSaving.value ||
    tagProcessing.value,
);
const writePending = computed(
  () =>
    profileSaveTimer.value !== null ||
    emailSaveTimer.value !== null ||
    phoneSaveTimer.value !== null ||
    noteSaveTimer.value !== null ||
    attributeSaveTimer.value !== null ||
    contextMutationProcessing.value,
);

watch(writePending, (pending) => emit('write-pending-change', pending), {
  immediate: true,
});

watch(
  () =>
    [
      props.contactProfile.id,
      props.contactProfile.name,
      props.contactProfile.country,
      props.contactProfile.city,
    ] as const,
  () => {
    clearProfileSaveTimer();
    syncingFromProps.value = true;
    profileForm.name = props.contactProfile.name ?? '';
    profileForm.type = null;
    profileForm.country = props.contactProfile.country ?? '';
    profileForm.city = props.contactProfile.city ?? '';
    lastSavedProfile.value = serializeProfileForm();
    profileForm.clearErrors();
    void nextTick(() => {
      syncingFromProps.value = false;
    });
  },
  { immediate: true },
);

watch(
  () => [props.contactProfile.id, props.contactProfile.type] as const,
  () => {
    typeForm.type = props.contactProfile.type;
    typeForm.clearErrors();
  },
  { immediate: true },
);

watch(
  () => [props.contactProfile.id, props.contactProfile.primary_email] as const,
  () => {
    clearEmailSaveTimer();
    syncingFromProps.value = true;
    emailForm.value = props.contactProfile.primary_email ?? '';
    lastSavedEmail.value = emailForm.value;
    emailForm.clearErrors();
    void nextTick(() => {
      syncingFromProps.value = false;
    });
  },
  { immediate: true },
);

watch(
  () => [props.contactProfile.id, props.contactProfile.primary_phone] as const,
  () => {
    clearPhoneSaveTimer();
    syncingFromProps.value = true;
    const value = props.contactProfile.primary_phone ?? '';
    const parsedPhone = splitPhoneNumber(value);
    phoneDialCode.value = parsedPhone.dialCode || defaultPhonePrefix.value;
    phoneLocalNumber.value = parsedPhone.localNumber;
    phoneForm.value = value;
    lastSavedPhone.value = value;
    phoneForm.clearErrors();
    void nextTick(() => {
      syncingFromProps.value = false;
    });
  },
  { immediate: true },
);

watch(
  () => [props.contactProfile.id, props.contactProfile.note] as const,
  () => {
    clearNoteSaveTimer();
    syncingFromProps.value = true;
    noteForm.note = props.contactProfile.note ?? '';
    lastSavedNote.value = noteForm.note;
    noteForm.clearErrors();
    void nextTick(() => {
      syncingFromProps.value = false;
    });
  },
  { immediate: true },
);

watch(
  () =>
    [
      props.contactProfile.id,
      JSON.stringify(props.contactProfile.custom_attributes),
    ] as const,
  () => {
    clearAttributeSaveTimer();
    initAttrValues(props.contactProfile.custom_attributes);
  },
  { immediate: true },
);

function placeholderOr(value: string | null): string {
  const trimmed = value?.trim();

  return trimmed ? trimmed : '—';
}

// 业务数据面板按当前联系人异步加载各集成的展示描述符。
const integrationPanels = ref<ContactPanelData[]>([]);
const integrationPanelsLoading = ref(false);
let integrationPanelsRequestContactId: string | null = null;

async function fetchIntegrationPanels(contactId: string): Promise<void> {
  integrationPanelsRequestContactId = contactId;
  integrationPanelsLoading.value = true;

  try {
    const response = await axios.get<{ panels: ContactPanelData[] }>(
      app.inbox.contacts.integrationPanels.url({
        contactId,
      }),
      { headers: { 'X-Requested-With': 'XMLHttpRequest' } },
    );

    if (integrationPanelsRequestContactId !== contactId) return;

    integrationPanels.value = response.data.panels;
  } catch (error) {
    if (integrationPanelsRequestContactId !== contactId) return;
    console.warn('[inbox-integration-panels] 联系人业务数据加载失败', {
      contactId,
      error,
    });
    integrationPanels.value = [];
  } finally {
    if (integrationPanelsRequestContactId === contactId) {
      integrationPanelsLoading.value = false;
    }
  }
}

watch(
  () => props.contactProfile.id,
  (contactId) => {
    integrationPanels.value = [];
    void fetchIntegrationPanels(contactId);
  },
  { immediate: true },
);

watch(integrationPanels, (panels) => {
  if (panels.length === 0 && activeTab.value === 'business') {
    activeTab.value = 'profile';
  }
});

type ProfileRow = { key: string; label: string; value: string };

const profileRows = computed<ProfileRow[]>(() => {
  const profile = props.contactProfile;

  const rows: ProfileRow[] = [
    { key: 'source', label: t('来源'), value: profile.source_label },
    {
      key: 'important',
      label: t('重点客户'),
      value: profile.is_important ? t('是') : t('否'),
    },
    { key: 'type', label: t('类型'), value: profile.type_label },
    { key: 'name', label: t('姓名'), value: placeholderOr(profile.name) },
    {
      key: 'email',
      label: t('邮箱'),
      value: placeholderOr(profile.primary_email),
    },
    {
      key: 'phone',
      label: t('手机号'),
      value: placeholderOr(profile.primary_phone),
    },
    {
      key: 'external_ids',
      label: t('外部 ID'),
      value: placeholderOr(profile.external_ids.join(', ')),
    },
    { key: 'locale', label: t('语言'), value: placeholderOr(profile.locale) },
    {
      key: 'visitor_locale',
      label: t('访客语言'),
      value: placeholderOr(props.conversation.visitor_locale),
    },
    {
      key: 'timezone',
      label: t('时区'),
      value: placeholderOr(profile.timezone),
    },
    {
      key: 'region',
      label: t('地区'),
      value: placeholderOr(
        [profile.country, profile.city].filter(Boolean).join(' / '),
      ),
    },
    { key: 'note', label: t('备注'), value: '' },
    {
      key: 'last_seen_at',
      label: t('最近活跃'),
      value: profile.last_seen_at
        ? formatDateTime(profile.last_seen_at, 'YYYY-MM-DD HH:mm')
        : '—',
    },
    {
      key: 'created_at',
      label: t('创建时间'),
      value: profile.created_at
        ? formatDateTime(profile.created_at, 'YYYY-MM-DD HH:mm')
        : '—',
    },
  ];

  return rows;
});

const selectedTagIds = computed(() =>
  props.contactProfile.tags.map((tag) => tag.id),
);

const editableAttributes = computed(() => {
  return props.contactProfile.custom_attributes.filter(
    (field) => field.is_editable,
  );
});

const deletedAttributes = computed(() => {
  return props.contactProfile.custom_attributes.filter(
    (field) =>
      !field.is_editable && field.value !== null && field.value !== undefined,
  );
});

const phoneErrorMessage = computed(() => {
  const phone = phoneLocalNumber.value.trim();

  if (phone === '') {
    return phoneForm.errors.value;
  }

  if (
    !isLikelyValidDialCode(phoneDialCode.value) ||
    !isLikelyValidLocalPhone(phone) ||
    !isLikelyValidPhone(buildPhoneNumber(phoneDialCode.value, phone))
  ) {
    return t('请输入有效的手机号');
  }

  return phoneForm.errors.value;
});

const isPhoneInvalid = computed(() => {
  const phone = phoneLocalNumber.value.trim();

  if (phone === '') {
    return false;
  }

  return (
    !isLikelyValidDialCode(phoneDialCode.value) ||
    !isLikelyValidLocalPhone(phone) ||
    !isLikelyValidPhone(buildPhoneNumber(phoneDialCode.value, phone))
  );
});

const emailErrorMessage = computed(() => {
  const email = emailForm.value.trim();

  if (email === '') {
    return emailForm.errors.value;
  }

  if (!isLikelyValidEmail(email)) {
    return t('请输入有效的邮箱地址');
  }

  return emailForm.errors.value;
});

const isEmailInvalid = computed(() => {
  const email = emailForm.value.trim();

  if (email === '') {
    return false;
  }

  return !isLikelyValidEmail(email);
});

const hasCustomAttributes = computed(() => {
  return props.contactProfile.custom_attributes.length > 0;
});

function clearNoteSaveTimer(): void {
  if (noteSaveTimer.value !== null) {
    window.clearTimeout(noteSaveTimer.value);
    noteSaveTimer.value = null;
  }
}

function clearProfileSaveTimer(): void {
  if (profileSaveTimer.value !== null) {
    window.clearTimeout(profileSaveTimer.value);
    profileSaveTimer.value = null;
  }
}

function clearEmailSaveTimer(): void {
  if (emailSaveTimer.value !== null) {
    window.clearTimeout(emailSaveTimer.value);
    emailSaveTimer.value = null;
  }
}

function clearPhoneSaveTimer(): void {
  if (phoneSaveTimer.value !== null) {
    window.clearTimeout(phoneSaveTimer.value);
    phoneSaveTimer.value = null;
  }
}

function clearAttributeSaveTimer(): void {
  if (attributeSaveTimer.value !== null) {
    window.clearTimeout(attributeSaveTimer.value);
    attributeSaveTimer.value = null;
  }
}

function serializeProfileForm(): string {
  return JSON.stringify({
    name: profileForm.name,
    country: profileForm.country,
    city: profileForm.city,
  });
}

/** 记录联系人资料传输失败，并通知上层取消等待中的导航。 */
function handleContextTransportFailure(failureType: 'network' | 'http'): void {
  console.warn('[inbox-context] 联系人资料保存请求失败', {
    conversationId: props.conversation.id,
    contactId: props.contactProfile.id,
    failureType,
  });
  toast.error(t('请求失败，请稍后重试'));
  emit('write-failed');
}

/** 记录标签写入后的局部刷新失败，标签写入结果以服务端为准。 */
function handleContextReloadFailure(failureType: 'network' | 'http'): void {
  console.warn('[inbox-context] 联系人资料写入后的刷新请求失败', {
    conversationId: props.conversation.id,
    contactId: props.contactProfile.id,
    failureType,
  });
  toast.warning(t('联系人资料已保存，但页面刷新失败'));
}

/** 放行标签写入后的受控局部刷新，并在访问结束后释放写入状态。 */
function reloadSelection(onFinish: () => void): void {
  runAllowedInboxContextReload(() => {
    router.reload({
      only: ['selection', 'conversation_list'],
      onNetworkError: () => handleContextReloadFailure('network'),
      onHttpException: () => handleContextReloadFailure('http'),
      onCancel: () => {
        console.info('[inbox-context] 联系人资料写入后的刷新请求已取消', {
          conversationId: props.conversation.id,
          contactId: props.contactProfile.id,
        });
      },
      onFinish,
    });
  });
}

function initAttrValues(fields: ContactAttributeFieldData[]): void {
  syncingFromProps.value = true;

  for (const key of Object.keys(attrValues)) {
    delete attrValues[key];
  }

  for (const field of fields.filter((item) => item.is_editable)) {
    attrValues[field.key] =
      field.value ?? (field.type === 'multi_select' ? [] : null);
  }

  attrForm.attributes = { ...attrValues };
  lastSavedAttributes.value = JSON.stringify(attrForm.attributes);
  attrForm.clearErrors();
  void nextTick(() => {
    syncingFromProps.value = false;
  });
}

function saveProfile(showProgress = true): void {
  const profile = props.contactProfile;
  if (props.writeBlocked || contextMutationProcessing.value) {
    scheduleProfileSave();
    return;
  }

  const serializedProfile = serializeProfileForm();
  if (serializedProfile === lastSavedProfile.value) return;

  profileForm.put(
    app.contacts.update.url({
      id: profile.id,
    }),
    {
      preserveScroll: true,
      only: ['selection', 'conversation_list'],
      showProgress,
      onSuccess: () => {
        lastSavedProfile.value = serializedProfile;
      },
      onError: () => emit('write-failed'),
      onNetworkError: () => handleContextTransportFailure('network'),
      onHttpException: () => handleContextTransportFailure('http'),
      onCancel: () => emit('write-failed'),
    },
  );
}

function scheduleProfileSave(): void {
  if (syncingFromProps.value) return;

  clearProfileSaveTimer();
  if (serializeProfileForm() === lastSavedProfile.value) {
    return;
  }

  profileSaveTimer.value = window.setTimeout(() => {
    profileSaveTimer.value = null;
    saveProfile(false);
  }, 700);
}

function saveNote(showProgress = true): void {
  const profile = props.contactProfile;
  if (props.writeBlocked || contextMutationProcessing.value) {
    scheduleNoteSave();
    return;
  }
  if (noteForm.note === lastSavedNote.value) return;

  noteForm.put(
    app.contacts.update.url({
      id: profile.id,
    }),
    {
      preserveScroll: true,
      only: ['selection', 'conversation_list'],
      onSuccess: () => {
        lastSavedNote.value = noteForm.note;
      },
      onError: () => emit('write-failed'),
      onNetworkError: () => handleContextTransportFailure('network'),
      onHttpException: () => handleContextTransportFailure('http'),
      onCancel: () => emit('write-failed'),
      showProgress,
    },
  );
}

function saveIdentity(kind: 'email' | 'phone', showProgress = true): void {
  const profile = props.contactProfile;

  const form = kind === 'email' ? emailForm : phoneForm;
  const lastSaved = kind === 'email' ? lastSavedEmail : lastSavedPhone;
  const identityId =
    kind === 'email'
      ? profile.primary_email_identity_id
      : profile.primary_phone_identity_id;
  const value =
    kind === 'phone'
      ? buildPhoneNumber(phoneDialCode.value, phoneLocalNumber.value)
      : form.value.trim();

  if (value === lastSaved.value) return;
  if (props.writeBlocked || contextMutationProcessing.value) {
    scheduleIdentitySave(kind);
    return;
  }

  if (value === '') {
    if (!identityId) {
      return;
    }

    form.clearErrors('value');
    form.delete(
      app.contacts.identities.destroy.url({
        contactId: profile.id,
        identityId,
      }),
      {
        preserveScroll: true,
        only: ['selection', 'conversation_list'],
        showProgress,
        onSuccess: () => {
          lastSaved.value = '';
        },
        onError: () => emit('write-failed'),
        onNetworkError: () => handleContextTransportFailure('network'),
        onHttpException: () => handleContextTransportFailure('http'),
        onCancel: () => emit('write-failed'),
      },
    );
    return;
  }

  if (kind === 'phone' && isPhoneInvalid.value) {
    form.setError('value', t('请输入有效的手机号'));
    emit('write-failed');
    return;
  }
  if (kind === 'email' && isEmailInvalid.value) {
    form.setError('value', t('请输入有效的邮箱地址'));
    emit('write-failed');
    return;
  }

  form.clearErrors('value');
  syncingFromProps.value = true;
  form.value = value;
  form.type = kind;
  form.namespace = null;
  void nextTick(() => {
    syncingFromProps.value = false;
  });

  const options = {
    preserveScroll: true,
    only: ['selection', 'conversation_list'],
    showProgress,
    onSuccess: () => {
      lastSaved.value = value;
    },
    onError: () => emit('write-failed'),
    onNetworkError: () => handleContextTransportFailure('network'),
    onHttpException: () => handleContextTransportFailure('http'),
    onCancel: () => emit('write-failed'),
  };

  if (identityId) {
    form.put(
      app.contacts.identities.replace.url({
        contactId: profile.id,
        identityId,
      }),
      options,
    );
    return;
  }

  form.post(
    app.contacts.identities.store.url({
      contactId: profile.id,
    }),
    options,
  );
}

function scheduleIdentitySave(kind: 'email' | 'phone'): void {
  if (syncingFromProps.value) return;

  const clearTimer =
    kind === 'email' ? clearEmailSaveTimer : clearPhoneSaveTimer;
  clearTimer();
  const value =
    kind === 'phone'
      ? buildPhoneNumber(phoneDialCode.value, phoneLocalNumber.value)
      : emailForm.value.trim();
  const lastSaved = kind === 'email' ? lastSavedEmail : lastSavedPhone;
  if (value === lastSaved.value) {
    return;
  }

  const timer = window.setTimeout(() => {
    if (kind === 'email') {
      emailSaveTimer.value = null;
    } else {
      phoneSaveTimer.value = null;
    }
    saveIdentity(kind, false);
  }, 700);

  if (kind === 'email') {
    emailSaveTimer.value = timer;
  } else {
    phoneSaveTimer.value = timer;
  }
}

function saveContactType(nextType: string): void {
  const profile = props.contactProfile;
  if (nextType !== 'visitor' && nextType !== 'contact') {
    throw new Error('联系人类型值无效');
  }
  if (nextType === profile.type) return;
  if (props.writeBlocked || contextMutationProcessing.value) return;

  typeForm.type = nextType;
  typeForm.put(
    app.contacts.update.url({
      id: profile.id,
    }),
    {
      preserveScroll: true,
      only: ['selection', 'conversation_list'],
      onError: () => emit('write-failed'),
      onNetworkError: () => handleContextTransportFailure('network'),
      onHttpException: () => handleContextTransportFailure('http'),
      onCancel: () => emit('write-failed'),
    },
  );
}

function scheduleNoteSave(): void {
  if (syncingFromProps.value) return;

  clearNoteSaveTimer();
  if (noteForm.note === lastSavedNote.value) {
    return;
  }

  noteSaveTimer.value = window.setTimeout(() => {
    noteSaveTimer.value = null;
    saveNote(false);
  }, 700);
}

async function handleAttachTag(tagId: string): Promise<void> {
  const profile = props.contactProfile;
  if (props.writeBlocked || contextMutationProcessing.value) return;

  let reloadStarted = false;
  tagProcessing.value = true;
  try {
    await axios.post(
      app.contacts.tags.attach.url({
        id: profile.id,
      }),
      { tag_id: tagId },
    );
    reloadSelection(() => {
      tagProcessing.value = false;
    });
    reloadStarted = true;
  } catch (error) {
    console.warn('[inbox-contact-tags] 联系人标签添加失败', {
      contactId: profile.id,
      tagId,
      status: axios.isAxiosError(error) ? error.response?.status : undefined,
      code: axios.isAxiosError(error) ? error.code : undefined,
    });
    emit('write-failed');
  } finally {
    if (!reloadStarted) {
      tagProcessing.value = false;
    }
  }
}

async function handleDetachTag(tagId: string): Promise<void> {
  const profile = props.contactProfile;
  if (props.writeBlocked || contextMutationProcessing.value) return;

  let reloadStarted = false;
  tagProcessing.value = true;
  try {
    await axios.delete(
      app.contacts.tags.detach.url({
        id: profile.id,
        tagId,
      }),
    );
    reloadSelection(() => {
      tagProcessing.value = false;
    });
    reloadStarted = true;
  } catch (error) {
    console.warn('[inbox-contact-tags] 联系人标签移除失败', {
      contactId: profile.id,
      tagId,
      status: axios.isAxiosError(error) ? error.response?.status : undefined,
      code: axios.isAxiosError(error) ? error.code : undefined,
    });
    emit('write-failed');
  } finally {
    if (!reloadStarted) {
      tagProcessing.value = false;
    }
  }
}

function saveCustomAttributes(showProgress = true): void {
  const profile = props.contactProfile;
  if (props.writeBlocked || contextMutationProcessing.value) {
    scheduleAttributeSave();
    return;
  }

  attrSaving.value = true;
  attrForm.attributes = { ...attrValues };
  const serializedAttributes = JSON.stringify(attrForm.attributes);
  if (serializedAttributes === lastSavedAttributes.value) {
    attrSaving.value = false;
    return;
  }

  attrForm.put(
    app.contacts.attributes.update.url({
      id: profile.id,
    }),
    {
      preserveScroll: true,
      only: ['selection', 'conversation_list'],
      showProgress,
      onSuccess: () => {
        lastSavedAttributes.value = serializedAttributes;
      },
      onError: () => emit('write-failed'),
      onNetworkError: () => handleContextTransportFailure('network'),
      onHttpException: () => handleContextTransportFailure('http'),
      onCancel: () => emit('write-failed'),
      onFinish: () => {
        attrSaving.value = false;
      },
    },
  );
}

function attrFieldError(key: string): string | undefined {
  const errors = attrForm.errors as Record<string, string | undefined>;

  return errors[`attributes.${key}`];
}

function scheduleAttributeSave(): void {
  if (syncingFromProps.value) return;

  clearAttributeSaveTimer();
  if (JSON.stringify(attrValues) === lastSavedAttributes.value) {
    return;
  }

  attributeSaveTimer.value = window.setTimeout(() => {
    attributeSaveTimer.value = null;
    saveCustomAttributes(false);
  }, 700);
}

function customAttributeOptionLabel(
  field: ContactAttributeFieldData,
  code: string,
): string {
  const options = field.config?.options as
    Array<{ code: string; label: string }> | undefined;

  return options?.find((option) => option.code === code)?.label ?? code;
}

function formatCustomAttributeValue(field: ContactAttributeFieldData): string {
  if (field.value === null || field.value === undefined || field.value === '') {
    return '-';
  }

  if (field.type === 'boolean') {
    return field.value === true ? t('是') : t('否');
  }

  if (field.type === 'single_select' && typeof field.value === 'string') {
    return customAttributeOptionLabel(field, field.value);
  }

  if (field.type === 'multi_select' && Array.isArray(field.value)) {
    return field.value
      .map((code) => customAttributeOptionLabel(field, String(code)))
      .join(', ');
  }

  return String(field.value);
}

watch(
  () => [profileForm.name, profileForm.country, profileForm.city],
  () => scheduleProfileSave(),
);

watch(
  () => emailForm.value,
  () => scheduleIdentitySave('email'),
);

watch(
  () => [phoneDialCode.value, phoneLocalNumber.value],
  () => scheduleIdentitySave('phone'),
);

watch(defaultPhonePrefix, (value) => {
  if (phoneLocalNumber.value.trim() !== '') return;

  phoneDialCode.value = value;
});

watch(
  () => noteForm.note,
  () => scheduleNoteSave(),
);

watch(attrValues, () => scheduleAttributeSave(), { deep: true });

onUnmounted(() => {
  clearProfileSaveTimer();
  clearEmailSaveTimer();
  clearPhoneSaveTimer();
  clearNoteSaveTimer();
  clearAttributeSaveTimer();
  emit('write-pending-change', false);
});
</script>

<template>
  <aside
    class="flex h-full min-h-0 w-full min-w-0 flex-col overflow-x-visible overflow-y-hidden bg-background"
    :inert="props.writeBlocked || contextMutationProcessing"
    :aria-busy="props.writeBlocked || contextMutationProcessing"
  >
    <div class="shrink-0 space-y-1.5 border-b px-3 py-2.5">
      <div class="flex items-center gap-2">
        <component
          :is="channelIcon"
          class="h-4 w-4 shrink-0 text-muted-foreground"
        />
        <span class="min-w-0 truncate text-sm font-medium text-foreground">
          {{ channelDisplayName }}
        </span>
      </div>
      <div
        class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
      >
        <span>
          {{ t('状态') }}：<span class="text-foreground">{{
            conversation.status_label
          }}</span>
        </span>
        <span>
          {{ t('负责人') }}：<span class="text-foreground">{{
            conversationAssigneeDisplay
          }}</span>
        </span>
      </div>
    </div>

    <div class="flex shrink-0 items-center gap-1 border-b px-3 py-2">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        class="rounded px-2.5 py-1 text-xs transition-colors"
        :class="
          activeTab === tab.key
            ? 'bg-primary text-primary-foreground'
            : 'text-muted-foreground hover:bg-muted'
        "
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
      </button>
    </div>

    <div
      class="min-h-0 min-w-0 flex-1 text-sm"
      :class="
        activeTab === 'copilot'
          ? 'overflow-hidden'
          : 'overflow-x-hidden overflow-y-auto overscroll-contain p-3'
      "
    >
      <template v-if="activeTab === 'profile'">
        <div v-if="channelContextRows.length > 0" class="mb-4 space-y-2">
          <div class="text-xs font-medium text-muted-foreground">
            {{ channelContextTitle }}
          </div>
          <dl class="space-y-2">
            <div
              v-for="row in channelContextRows"
              :key="row.key"
              class="grid grid-cols-[4.75rem_minmax(0,1fr)] items-start gap-2"
            >
              <dt
                class="flex min-h-7 min-w-0 items-center text-xs text-muted-foreground"
              >
                {{ row.label }}
              </dt>
              <dd class="flex min-h-7 min-w-0 flex-1 items-center">
                <a
                  v-if="row.href"
                  :href="row.href"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="truncate text-sm text-foreground underline-offset-2 hover:underline"
                  :title="row.value"
                >
                  {{ row.value }}
                </a>
                <span
                  v-else
                  class="truncate text-sm text-foreground"
                  :title="row.value"
                >
                  {{ row.value }}
                </span>
              </dd>
            </div>
          </dl>
          <Separator />
        </div>

        <div class="space-y-4">
          <dl class="space-y-2">
            <div
              v-for="row in profileRows"
              :key="row.key"
              class="grid grid-cols-[4.75rem_minmax(0,1fr)] items-start gap-2"
            >
              <dt
                class="flex min-h-8 min-w-0 items-center text-xs text-muted-foreground"
              >
                {{ row.label }}
              </dt>
              <dd v-if="row.key === 'name'" class="min-w-0 flex-1 space-y-1">
                <Input
                  v-model="profileForm.name"
                  type="text"
                  maxlength="255"
                  :disabled="profileForm.processing"
                  class="h-8 px-2.5 text-sm"
                />
                <InputError :message="profileForm.errors.name" />
              </dd>
              <dd v-else-if="row.key === 'type'" class="min-w-0 flex-1">
                <Select
                  :model-value="contactProfile.type"
                  :disabled="typeForm.processing"
                  @update:model-value="
                    (value) => saveContactType(String(value))
                  "
                >
                  <SelectTrigger class="h-8 w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="visitor">{{ t('访客') }}</SelectItem>
                    <SelectItem value="contact">{{ t('联系人') }}</SelectItem>
                  </SelectContent>
                </Select>
              </dd>
              <dd
                v-else-if="row.key === 'email'"
                class="relative min-w-0 flex-1 space-y-1"
              >
                <Input
                  v-model="emailForm.value"
                  type="email"
                  inputmode="email"
                  autocomplete="email"
                  :maxlength="EMAIL_MAX_LENGTH"
                  :disabled="emailForm.processing"
                  class="h-8 px-2.5 text-sm"
                />
                <InputError :message="emailErrorMessage" />
              </dd>
              <dd
                v-else-if="row.key === 'phone'"
                class="relative min-w-0 flex-1 space-y-1"
              >
                <div class="flex min-w-0 flex-wrap gap-2">
                  <PhoneDialCodeCombobox
                    v-model="phoneDialCode"
                    align="end"
                    portal
                    class="h-8 w-28 shrink-0 text-xs"
                    :disabled="phoneForm.processing"
                  />
                  <Input
                    v-model="phoneLocalNumber"
                    type="tel"
                    inputmode="tel"
                    :disabled="phoneForm.processing"
                    class="h-8 min-w-0 flex-1 px-2.5 text-sm"
                  />
                </div>
                <InputError :message="phoneErrorMessage" />
              </dd>
              <dd
                v-else-if="row.key === 'region'"
                class="grid min-w-0 flex-1 grid-cols-[repeat(auto-fit,minmax(7rem,1fr))] gap-2"
              >
                <div class="min-w-0 space-y-1">
                  <Input
                    v-model="profileForm.country"
                    type="text"
                    maxlength="120"
                    :disabled="profileForm.processing"
                    class="h-8 px-2.5 text-sm"
                  />
                  <InputError :message="profileForm.errors.country" />
                </div>
                <div class="min-w-0 space-y-1">
                  <Input
                    v-model="profileForm.city"
                    type="text"
                    maxlength="120"
                    :disabled="profileForm.processing"
                    class="h-8 px-2.5 text-sm"
                  />
                  <InputError :message="profileForm.errors.city" />
                </div>
              </dd>
              <dd
                v-else-if="row.key !== 'note'"
                class="flex min-h-8 min-w-0 flex-1 items-center text-sm break-words text-foreground"
              >
                {{ row.value }}
              </dd>
              <dd v-else class="relative min-w-0 flex-1 space-y-1.5">
                <Textarea
                  v-model="noteForm.note"
                  rows="2"
                  maxlength="10000"
                  :disabled="noteForm.processing"
                  class="resize-y leading-6"
                />
                <InputError :message="noteForm.errors.note" />
              </dd>
            </div>
          </dl>

          <Separator />

          <div class="grid grid-cols-[4.75rem_minmax(0,1fr)] items-start gap-2">
            <div
              class="flex min-h-8 min-w-0 items-center text-xs text-muted-foreground"
            >
              {{ t('标签') }}
            </div>
            <div class="min-w-0 flex-1">
              <TagSelector
                :options="availableContactTags"
                :selected-tag-ids="selectedTagIds"
                :disabled="tagProcessing"
                @attach="handleAttachTag"
                @detach="handleDetachTag"
              />
            </div>
          </div>

          <div
            v-if="props.contactProfile.conversation_tag_aggregates.length > 0"
            class="grid grid-cols-[4.75rem_minmax(0,1fr)] items-start gap-2"
          >
            <div
              class="flex min-h-8 min-w-0 items-center text-xs text-muted-foreground"
            >
              {{ t('咨询概况') }}
            </div>
            <div class="flex min-w-0 flex-1 flex-wrap gap-1.5">
              <span
                v-for="aggregate in props.contactProfile
                  .conversation_tag_aggregates"
                :key="aggregate.tag_id"
                class="flex items-center gap-1 rounded-full border bg-background py-0.5 pr-2 pl-2 text-xs text-foreground"
              >
                <span
                  class="h-1.5 w-1.5 shrink-0 rounded-full"
                  :style="{ backgroundColor: aggregate.color ?? '#94a3b8' }"
                />
                {{ aggregate.name }}
                <span class="text-muted-foreground"
                  >×{{ aggregate.count }}</span
                >
              </span>
            </div>
          </div>

          <template v-if="hasCustomAttributes">
            <Separator />
            <div class="space-y-3">
              <div
                v-for="field in editableAttributes"
                :key="field.definition_id"
                class="grid grid-cols-[4.75rem_minmax(0,1fr)] items-start gap-2"
              >
                <div
                  class="flex min-h-8 min-w-0 items-center text-xs text-muted-foreground"
                >
                  {{ field.name }}
                </div>
                <div class="min-w-0 flex-1">
                  <AttributeFieldRenderer
                    :field="field"
                    :model-value="attrValues[field.key]"
                    :errors="attrFieldError(field.key)"
                    :disabled="attrSaving"
                    hide-label
                    hide-meta
                    compact
                    @update:model-value="attrValues[field.key] = $event"
                  />
                </div>
              </div>
              <div
                v-if="deletedAttributes.length > 0"
                :class="{ 'pt-2': editableAttributes.length > 0 }"
                class="space-y-2"
              >
                <div
                  v-for="field in deletedAttributes"
                  :key="field.definition_id"
                  class="grid grid-cols-[4.75rem_minmax(0,1fr)] items-start gap-2"
                >
                  <div
                    class="flex min-h-8 min-w-0 items-center text-xs text-muted-foreground"
                  >
                    {{ field.name }}
                  </div>
                  <div class="flex min-h-8 min-w-0 flex-1 items-center">
                    <div class="truncate text-sm">
                      {{ formatCustomAttributeValue(field) }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </template>
          <div
            v-else
            class="grid grid-cols-[4.75rem_minmax(0,1fr)] items-start gap-2"
          >
            <div
              class="flex min-h-8 min-w-0 items-center text-xs text-muted-foreground"
            >
              {{ t('自定义字段') }}
            </div>
            <div
              class="flex min-h-8 min-w-0 flex-1 items-center text-xs text-muted-foreground"
            >
              {{ t('暂无自定义字段') }}
            </div>
          </div>
        </div>
      </template>

      <template v-else-if="activeTab === 'business'">
        <div v-if="integrationPanelsLoading" class="space-y-2">
          <div class="h-4 w-24 animate-pulse rounded bg-muted" />
          <div class="h-3 w-16 animate-pulse rounded bg-muted" />
          <div class="h-3 w-full animate-pulse rounded bg-muted" />
          <div class="h-3 w-2/3 animate-pulse rounded bg-muted" />
        </div>

        <div v-else class="space-y-4">
          <template
            v-for="(panel, panelIndex) in integrationPanels"
            :key="panel.integration_id"
          >
            <Separator v-if="panelIndex > 0" />
            <IntegrationPanel :panel="panel" />
          </template>
        </div>
      </template>

      <KeepAlive>
        <AiAssistantWidget
          v-if="activeTab === 'copilot'"
          :conversation-id="conversation.id"
          :contact-profile="contactProfile"
          :target-locale="targetLocale"
          :can-translate="canTranslate"
          :translation-enabled="translationEnabled"
        />
      </KeepAlive>
    </div>
  </aside>
</template>
