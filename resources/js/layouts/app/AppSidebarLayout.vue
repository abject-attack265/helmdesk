<!--
  文件说明：应用后台侧边栏，组合导航权限、账号状态和实时提醒。
-->
<script setup lang="ts">
import Integration from '@/actions/App/Actions/Integration';
import KnowledgeBase from '@/actions/App/Actions/KnowledgeBase';
import Plan from '@/actions/App/Actions/Reception/Plan';
import WechatOfficialAccount from '@/actions/App/Actions/Channel/WechatOfficialAccount';
import { useI18n } from '@/composables/useI18n';
import { useRequiredSystem } from '@/composables/useSystem';
import { useSystemNotificationAlerts } from '@/composables/useSystemNotificationAlerts';
import SidebarShell, {
  type SidebarShellNavItem,
} from '@/layouts/app/SidebarShell.vue';
import SidebarUserMenuWithOnlineStatus from '@/layouts/app/SidebarUserMenuWithOnlineStatus.vue';
import logout from '@/routes/logout';
import { edit } from '@/routes/settings/profile';
import app from '@/routes/app';
import type { AppPageProps } from '@/types';
import { usePage } from '@inertiajs/vue3';
import {
  AppWindow,
  BookOpen,
  ClipboardList,
  Cpu,
  Inbox,
  LayoutGrid,
  Languages,
  ScrollText,
  Settings,
  SlidersHorizontal,
  Tag,
  Users,
  Zap,
} from '@lucide/vue';
import { computed } from 'vue';

interface Props {
  hideHeader?: boolean;
}

type SidebarPageProps = AppPageProps & {
  current_thread_id?: string | null;
};

withDefaults(defineProps<Props>(), {
  hideHeader: false,
});

const { t } = useI18n();
const page = usePage<SidebarPageProps>();
const currentApp = useRequiredSystem();
const user = computed(() => page.props.auth.user);
const notificationPreferences = computed(
  () => page.props.auth.user.notification_preferences,
);

const requireSystemFlag = (
  value: boolean | undefined,
  name: string,
): boolean => {
  if (typeof value !== 'boolean') {
    throw new Error(`${name} is required for AppSidebarLayout.`);
  }

  return value;
};

const currentUserContext = computed(() => {
  if (!page.props.currentUserContext) {
    throw new Error('currentUserContext is required for AppSidebarLayout.');
  }

  return page.props.currentUserContext;
});

const isOwner = computed(() => currentUserContext.value.is_owner);
const canAccessContacts = computed(() =>
  requireSystemFlag(page.props.canAccessContacts, 'canAccessContacts'),
);
const canAccessCannedReplies = computed(() =>
  requireSystemFlag(
    page.props.canAccessCannedReplies,
    'canAccessCannedReplies',
  ),
);
const canAccessTags = computed(() =>
  requireSystemFlag(page.props.canAccessTags, 'canAccessTags'),
);
const canAccessAttributes = computed(() =>
  requireSystemFlag(page.props.canAccessAttributes, 'canAccessAttributes'),
);
const canAccessKnowledgeBases = computed(() =>
  requireSystemFlag(
    page.props.canAccessKnowledgeBases,
    'canAccessKnowledgeBases',
  ),
);
const canAccessReceptionPlans = computed(() =>
  requireSystemFlag(
    page.props.canAccessReceptionPlans,
    'canAccessReceptionPlans',
  ),
);
const canAccessChannels = computed(() =>
  requireSystemFlag(page.props.canAccessChannels, 'canAccessChannels'),
);
const canAccessUsers = computed(() =>
  requireSystemFlag(page.props.canAccessUsers, 'canAccessUsers'),
);
const canManageSystemSettings = computed(() =>
  requireSystemFlag(
    page.props.canManageSystemSettings,
    'canManageSystemSettings',
  ),
);

type MainNavItem = SidebarShellNavItem;

const contactsBaseUrl = app.contacts.index.url('all');
const manageBaseUrl = app.manage.system.settings.show
  .url()
  .replace(/\/system\/settings$/, '');

const mainNavItems = computed<MainNavItem[]>(() => {
  const items: MainNavItem[] = [
    {
      title: t('仪表板'),
      href: app.dashboard.url(),
      icon: LayoutGrid,
      activeUrls: [app.dashboard.url()],
    },
    {
      title: t('收件箱'),
      href: app.inbox.show.url(),
      icon: Inbox,
      activeUrls: [app.inbox.show.url()],
    },
    ...(canAccessContacts.value
      ? [
          {
            title: t('联系人'),
            href: contactsBaseUrl,
            icon: Users,
            activeUrls: [contactsBaseUrl],
          },
        ]
      : []),
    ...(canAccessCannedReplies.value
      ? [
          {
            title: t('快捷回复'),
            href: app.cannedReplies.index.url(),
            icon: Zap,
            activeUrls: [app.cannedReplies.index.url()],
          },
        ]
      : []),
    ...(canAccessTags.value
      ? [
          {
            title: t('标签'),
            href: app.manage.tags.index.url(),
            icon: Tag,
            activeUrls: [`${manageBaseUrl}/tags`],
          },
        ]
      : []),
    ...(canAccessAttributes.value
      ? [
          {
            title: t('自定义字段'),
            href: app.manage.attributes.index.url(),
            icon: SlidersHorizontal,
            activeUrls: [`${manageBaseUrl}/attributes`],
          },
        ]
      : []),
    ...(canAccessKnowledgeBases.value
      ? [
          {
            title: t('知识库'),
            href: KnowledgeBase.ListKnowledgeBasesAction.url(),
            icon: BookOpen,
            activeUrls: [
              `${manageBaseUrl}/knowledge-bases`,
              `${manageBaseUrl}/experience-extraction`,
            ],
          },
        ]
      : []),
    ...(canAccessReceptionPlans.value
      ? [
          {
            title: t('接待方案'),
            href: Plan.ShowReceptionPlanIndexPageAction.url(),
            icon: ClipboardList,
            activeUrls: [`${manageBaseUrl}/reception/plans`],
          },
        ]
      : []),
    ...(canAccessChannels.value
      ? [
          {
            title: t('接入渠道'),
            href: app.manage.channels.web.index.url(),
            icon: AppWindow,
            activeUrls: [`${manageBaseUrl}/channels`],
            children: [
              {
                title: t('网站'),
                href: app.manage.channels.web.index.url(),
                activeUrls: [`${manageBaseUrl}/channels/web`],
              },
              {
                title: t('Telegram'),
                href: app.manage.channels.telegram.index.url(),
                activeUrls: [`${manageBaseUrl}/channels/telegram`],
              },
              {
                title: t('微信公众号'),
                href: WechatOfficialAccount.ListWechatOfficialAccountChannelsAction.url(),
                activeUrls: [
                  `${manageBaseUrl}/channels/wechat-official-account`,
                ],
              },
            ],
          },
        ]
      : []),
  ];

  const settingsChildren: MainNavItem[] = [];

  if (canManageSystemSettings.value) {
    settingsChildren.push(
      {
        title: t('常规设置'),
        href: app.manage.system.settings.show.url(),
        activeUrls: [`${manageBaseUrl}/system`],
      },
      {
        title: t('存储设置'),
        href: app.manage.storage.index.url(),
        activeUrls: [`${manageBaseUrl}/storage`],
      },
      {
        title: t('翻译供应商'),
        href: app.manage.translationProviders.index.url(),
        icon: Languages,
        activeUrls: [`${manageBaseUrl}/translation-providers`],
      },
      {
        title: t('AI 供应商'),
        href: app.manage.aiProviders.index.url(),
        icon: Cpu,
        activeUrls: [`${manageBaseUrl}/ai-providers`],
      },
      {
        title: t('AI 模型'),
        href: app.manage.aiModels.index.url(),
        icon: Settings,
        activeUrls: [`${manageBaseUrl}/ai-models`],
      },
      {
        title: t('AI 调用日志'),
        href: app.manage.aiCallLogs.index.url(),
        icon: ScrollText,
        activeUrls: [`${manageBaseUrl}/ai-call-logs`],
      },
    );
  }

  if (canAccessUsers.value) {
    settingsChildren.splice(canManageSystemSettings.value ? 1 : 0, 0, {
      title: t('客服'),
      href: app.manage.teammates.index.url(),
      activeUrls: [`${manageBaseUrl}/teammates`],
    });
  }

  if (isOwner.value) {
    settingsChildren.splice(2, 0, {
      title: t('集成'),
      href: Integration.ShowInstanceIntegrationsAction.url(),
      activeUrls: [`${manageBaseUrl}/integrations`],
    });
  }

  if (settingsChildren.length > 0) {
    items.push({
      title: t('设置'),
      href: settingsChildren[0].href,
      icon: Settings,
      activeUrls: settingsChildren.flatMap((child) => child.activeUrls ?? []),
      children: settingsChildren,
    });
  }

  return items;
});

const logoutHref = computed(() => logout.web.url());

useSystemNotificationAlerts({
  userId: computed(() => user.value.id),
  preferences: notificationPreferences,
  currentThreadId: computed(() => page.props.current_thread_id ?? null),
});
</script>

<template>
  <SidebarShell
    :hide-header="hideHeader"
    :header-href="app.dashboard.url()"
    :header-title="currentApp.name"
    :header-logo-url="currentApp.logo_url"
    :header-logo-inverts-in-dark="currentApp.logo_id === null"
    :main-nav-items="mainNavItems"
    :footer-nav-items="[]"
    :profile-href="edit().url"
    :profile-label="t('个人资料')"
    :logout-href="logoutHref"
  >
    <template #userMenu="{ isMobile, sidebarState }">
      <SidebarUserMenuWithOnlineStatus
        :profile-href="edit().url"
        :profile-label="t('个人资料')"
        :logout-href="logoutHref"
        :is-mobile="isMobile"
        :sidebar-state="sidebarState"
      />
    </template>

    <slot />
  </SidebarShell>
</template>
