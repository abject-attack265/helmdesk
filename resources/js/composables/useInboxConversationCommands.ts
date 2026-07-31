/**
 * 管理收件箱会话流转、联系人重点标记和当前用户上线命令。
 */
import appRoutes from '@/routes/app';
import inboxActions from '@/routes/app/inbox';
import type {
  FormTransferInboxConversationData,
  FormUpdateContactImportanceData,
  FormUpdateTeammateOnlineStatusData,
  InboxSelectionData,
  UserOptionData,
} from '@/types/generated';
import { useForm, type InertiaForm } from '@inertiajs/vue3';
import axios from 'axios';
import {
  computed,
  ref,
  toValue,
  type ComputedRef,
  type MaybeRefOrGetter,
  type Ref,
} from 'vue';

/** 会话流转命令只需要表单的提交能力，各命令的字段结构互不相同。 */
type ConversationCommandForm = Pick<InertiaForm<object>, 'post'>;

interface UseInboxConversationCommandsOptions {
  selection: MaybeRefOrGetter<InboxSelectionData | null>;
  teammates: MaybeRefOrGetter<UserOptionData[]>;
  currentUserId: MaybeRefOrGetter<string>;
  commandsBlocked: MaybeRefOrGetter<boolean>;
  onChanged: () => void | Promise<void>;
}

interface UseInboxConversationCommandsReturn {
  transferTeammates: ComputedRef<UserOptionData[]>;
  isAiOwnedSelection: ComputedRef<boolean>;
  conversationCommandProcessing: ComputedRef<boolean>;
  importanceProcessing: Ref<boolean>;
  updatingOnlineStatus: ComputedRef<boolean>;
  claimConversation: () => void;
  releaseConversationToAi: () => void;
  transferConversationToTeammate: (targetUserId: string) => void;
  reopenConversation: () => void;
  closeConversation: () => void;
  toggleSelectionImportance: () => Promise<void>;
  switchCurrentUserOnline: () => void;
}

/** 创建收件箱会话命令，并用共享处理状态阻止并发流转操作。 */
export function useInboxConversationCommands(
  options: UseInboxConversationCommandsOptions,
): UseInboxConversationCommandsReturn {
  const claimForm = useForm({});
  const releaseToAiForm = useForm({});
  const transferForm = useForm<FormTransferInboxConversationData>({
    target_user_id: '',
  });
  const reopenForm = useForm({});
  const closeForm = useForm({});
  const onlineStatusForm = useForm<FormUpdateTeammateOnlineStatusData>({
    online_status: 1,
  });
  const importanceProcessing = ref(false);

  const transferTeammates = computed(() =>
    toValue(options.teammates).filter(
      (teammate) => teammate.id !== toValue(options.currentUserId),
    ),
  );
  const isAiOwnedSelection = computed(() => {
    const conversation = toValue(options.selection)?.conversation;

    return (
      conversation?.status === 'open' &&
      conversation.assigned_user_id === null &&
      conversation.inbox_status === 'ai_handling'
    );
  });
  const conversationFormsProcessing = computed(
    () =>
      claimForm.processing ||
      releaseToAiForm.processing ||
      transferForm.processing ||
      reopenForm.processing ||
      closeForm.processing,
  );
  const conversationCommandProcessing = computed(
    () => conversationFormsProcessing.value || toValue(options.commandsBlocked),
  );
  const updatingOnlineStatus = computed(() => onlineStatusForm.processing);

  /**
   * 提交一条会话流转命令，权限由 InboxSelectionData 的 can_* 字段决定。
   *
   * @param form 承接该命令的 Inertia 表单
   * @param permitted 当前选择是否允许执行该命令
   * @param buildUrl 由会话 ID 生成的提交地址
   * @param prepare 通过权限校验后填充表单字段
   */
  function submitConversationCommand(
    form: ConversationCommandForm,
    permitted: (selection: InboxSelectionData) => boolean,
    buildUrl: (params: { conversation: string }) => string,
    prepare?: () => void,
  ): void {
    const selection = toValue(options.selection);
    if (
      !selection ||
      !permitted(selection) ||
      conversationCommandProcessing.value
    ) {
      return;
    }

    prepare?.();
    form.post(
      buildUrl({
        conversation: selection.conversation.id,
      }),
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => options.onChanged(),
      },
    );
  }

  function claimConversation(): void {
    submitConversationCommand(
      claimForm,
      (selection) => selection.can_claim,
      inboxActions.conversations.claim.url,
    );
  }

  function releaseConversationToAi(): void {
    submitConversationCommand(
      releaseToAiForm,
      (selection) => selection.can_release_to_ai,
      inboxActions.conversations.releaseToAi.url,
    );
  }

  function transferConversationToTeammate(targetUserId: string): void {
    submitConversationCommand(
      transferForm,
      (selection) =>
        selection.can_transfer_to_teammate &&
        targetUserId !== toValue(options.currentUserId),
      inboxActions.conversations.transfer.url,
      () => {
        transferForm.target_user_id = targetUserId;
      },
    );
  }

  function reopenConversation(): void {
    submitConversationCommand(
      reopenForm,
      (selection) => selection.can_reopen,
      inboxActions.conversations.reopen.url,
    );
  }

  function closeConversation(): void {
    submitConversationCommand(
      closeForm,
      (selection) => selection.can_close,
      inboxActions.conversations.close.url,
    );
  }

  async function toggleSelectionImportance(): Promise<void> {
    const contact = toValue(options.selection)?.contact;
    if (
      !contact ||
      importanceProcessing.value ||
      toValue(options.commandsBlocked)
    ) {
      return;
    }

    const payload: FormUpdateContactImportanceData = {
      is_important: !contact.is_important,
    };
    importanceProcessing.value = true;

    try {
      await axios.put(
        appRoutes.contacts.importance.update.url({
          id: contact.id,
        }),
        payload,
      );
      await options.onChanged();
    } catch (error: unknown) {
      console.warn('[inbox-importance] 联系人重点状态更新失败', {
        instance: 'system',
        conversationId: toValue(options.selection)?.conversation.id,
        contactId: contact.id,
        status: axios.isAxiosError(error) ? error.response?.status : undefined,
        code: axios.isAxiosError(error) ? error.code : undefined,
        errorType: axios.isAxiosError(error)
          ? 'AxiosError'
          : error instanceof Error
            ? error.name
            : typeof error,
      });
      throw error;
    } finally {
      importanceProcessing.value = false;
    }
  }

  function switchCurrentUserOnline(): void {
    if (onlineStatusForm.processing || toValue(options.commandsBlocked)) {
      return;
    }

    onlineStatusForm.online_status = 1;
    onlineStatusForm.put(appRoutes.onlineStatus.update.url(), {
      preserveScroll: true,
      preserveState: true,
    });
  }

  return {
    transferTeammates,
    isAiOwnedSelection,
    conversationCommandProcessing,
    importanceProcessing,
    updatingOnlineStatus,
    claimConversation,
    releaseConversationToAi,
    transferConversationToTeammate,
    reopenConversation,
    closeConversation,
    toggleSelectionImportance,
    switchCurrentUserOnline,
  };
}
