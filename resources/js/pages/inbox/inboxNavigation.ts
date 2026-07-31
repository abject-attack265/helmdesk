/**
 * 收件箱导航查询构建器，统一把筛选、选中线程和面板状态转换为 Wayfinder URL。
 */
import appRoutes from '@/routes/app';
import type {
  InboxPane,
  InboxView,
  ShowInboxPagePropsData,
} from '@/types/generated';

interface InboxFilterState {
  view: InboxView;
  channelId: string | null;
  assignee: string | null;
  search: string | null;
  importantOnly: boolean;
}

interface InboxNavigationState {
  filters: InboxFilterState;
  threadId: string | null;
  pane: InboxPane | null;
}

type InboxFilterOverrides = Partial<InboxFilterState>;

export interface InboxNavigationOverrides {
  filters?: InboxFilterOverrides;
  threadId?: string | null;
  pane?: InboxPane | null;
}

type InboxPageNavigationProps = Pick<
  ShowInboxPagePropsData,
  | 'current_view'
  | 'current_channel_id'
  | 'current_assignee'
  | 'current_search'
  | 'current_important_only'
  | 'current_thread_id'
  | 'current_pane'
>;

/** 从收件箱 PageProps 提取稳定的导航状态。 */
export function inboxNavigationStateFromPageProps(
  props: InboxPageNavigationProps,
): InboxNavigationState {
  return {
    filters: {
      view: props.current_view,
      channelId: props.current_channel_id,
      assignee: props.current_assignee,
      search: props.current_search,
      importantOnly: props.current_important_only,
    },
    threadId: props.current_thread_id,
    pane: props.current_pane,
  };
}

/** 将局部覆盖项合并到当前收件箱导航状态。 */
export function mergeInboxNavigationState(
  state: InboxNavigationState,
  overrides: InboxNavigationOverrides = {},
): InboxNavigationState {
  return {
    filters: {
      ...state.filters,
      ...overrides.filters,
    },
    threadId:
      overrides.threadId === undefined ? state.threadId : overrides.threadId,
    pane: overrides.pane === undefined ? state.pane : overrides.pane,
  };
}

/** 合并局部覆盖项并生成收件箱 Wayfinder URL。 */
export function buildInboxUrl(
  state: InboxNavigationState,
  overrides: InboxNavigationOverrides = {},
): string {
  const navigation = mergeInboxNavigationState(state, overrides);
  const query: Record<string, string> = {
    view: navigation.filters.view,
  };

  if (navigation.filters.channelId) {
    query.channel = navigation.filters.channelId;
  }
  if (navigation.filters.assignee) {
    query.assignee = navigation.filters.assignee;
  }
  if (navigation.filters.search) {
    query.search = navigation.filters.search;
  }
  if (navigation.filters.importantOnly) {
    query.important = '1';
  }
  if (navigation.threadId) {
    query.thread_id = navigation.threadId;
  }
  if (navigation.pane === 'list') {
    query.pane = navigation.pane;
  }

  return appRoutes.inbox.show.url({ query });
}
