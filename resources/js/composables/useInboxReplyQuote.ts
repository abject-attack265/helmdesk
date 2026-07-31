/**
 * 管理收件箱回复引用的目标快照、附件预览、正文弹窗和撤回内容再编辑。
 */
import { useI18n } from '@/composables/useI18n';
import type {
  ContactTimelineEntryData,
  InboxSelectionData,
  ReceptionAttachmentData,
} from '@/types/generated';
import {
  computed,
  ref,
  toValue,
  watch,
  type ComputedRef,
  type MaybeRefOrGetter,
  type Ref,
} from 'vue';

type ReplyQuoteAttachment = ReceptionAttachmentData;

interface ReplyQuoteTarget {
  id: string;
  senderName: string;
  preview: string;
  content: string | null;
  attachments: ReplyQuoteAttachment[];
}

interface UseInboxReplyQuoteOptions {
  selection: MaybeRefOrGetter<InboxSelectionData | null>;
  replyContent: Ref<string>;
  clearReplyContentError: () => void;
  focusComposer: (conversationId: string) => void | Promise<void>;
  formatVisitorName: (
    name: string | null | undefined,
    contactId: string,
  ) => string;
}

interface UseInboxReplyQuoteReturn {
  replyQuote: Ref<ReplyQuoteTarget | null>;
  quotedMessageId: ComputedRef<string | null>;
  replyQuotePreviewOpen: Ref<boolean>;
  replyQuotePreviewImages: Ref<ReplyQuoteAttachment[]>;
  replyQuotePreviewInitialId: Ref<string | null>;
  replyQuoteTextDialogOpen: Ref<boolean>;
  replyQuoteDialogTitle: Ref<string>;
  replyQuoteDialogContent: Ref<string>;
  quoteMessage: (entry: ContactTimelineEntryData) => void;
  clearReplyQuote: () => void;
  openReplyQuoteTarget: (quote: ReplyQuoteTarget) => void;
  reeditRecalledMessage: (content: string) => void;
}

/** 创建回复引用状态并监听选中会话的生命周期。 */
export function useInboxReplyQuote(
  options: UseInboxReplyQuoteOptions,
): UseInboxReplyQuoteReturn {
  const { t } = useI18n();
  const replyQuote = ref<ReplyQuoteTarget | null>(null);
  const replyQuotePreviewOpen = ref(false);
  const replyQuotePreviewImages = ref<ReplyQuoteAttachment[]>([]);
  const replyQuotePreviewInitialId = ref<string | null>(null);
  const replyQuoteTextDialogOpen = ref(false);
  const replyQuoteDialogTitle = ref('');
  const replyQuoteDialogContent = ref('');
  const quotedMessageId = computed(() => replyQuote.value?.id ?? null);

  function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
  }

  function parseQuoteAttachment(
    value: unknown,
    messageId: string,
  ): ReplyQuoteAttachment {
    if (
      !isRecord(value) ||
      typeof value.id !== 'string' ||
      typeof value.name !== 'string' ||
      typeof value.mime_type !== 'string' ||
      typeof value.byte_size !== 'number' ||
      typeof value.url !== 'string' ||
      value.url.trim() === ''
    ) {
      throw new Error(`消息 ${messageId} 的引用附件数据格式无效`);
    }

    return {
      id: value.id,
      name: value.name,
      mime_type: value.mime_type,
      byte_size: value.byte_size,
      url: value.url,
    };
  }

  function quoteAttachments(
    entry: ContactTimelineEntryData,
  ): ReplyQuoteAttachment[] {
    const raw = entry.payload?.attachments as unknown;
    if (raw === null || raw === undefined) {
      return [];
    }
    if (!Array.isArray(raw)) {
      throw new Error(`消息 ${entry.id} 的引用附件列表格式无效`);
    }

    return raw.map((attachment) => parseQuoteAttachment(attachment, entry.id));
  }

  function quoteSenderName(entry: ContactTimelineEntryData): string {
    if (entry.role === 'visitor') {
      const contact = toValue(options.selection)?.contact;
      if (!contact) {
        throw new Error('回复引用缺少当前联系人');
      }

      return options.formatVisitorName(contact.name, contact.id);
    }
    if (entry.role === 'ai') {
      return entry.sender_name || t('AI 助手');
    }

    return entry.sender_name || t('客服');
  }

  function quotePreview(entry: ContactTimelineEntryData): string {
    if (typeof entry.content === 'string' && entry.content.trim().length > 0) {
      return entry.content.replace(/\s+/g, ' ').slice(0, 120);
    }
    if (entry.kind === 'image') {
      return t('图片');
    }
    if (entry.kind === 'file') {
      return t('文件');
    }

    return t('无内容');
  }

  function createReplyQuoteTarget(
    entry: ContactTimelineEntryData,
  ): ReplyQuoteTarget {
    return {
      id: entry.id,
      senderName: quoteSenderName(entry),
      preview: quotePreview(entry),
      content: entry.content,
      attachments: quoteAttachments(entry),
    };
  }

  function quoteMessage(entry: ContactTimelineEntryData): void {
    if (entry.type !== 'message') {
      throw new Error(`时间线条目 ${entry.id} 不是可引用消息`);
    }
    if (entry.recalled_at) {
      throw new Error(`消息 ${entry.id} 已撤回，不能引用`);
    }

    replyQuote.value = createReplyQuoteTarget(entry);
    void options.focusComposer(entry.conversation_id);
  }

  function clearReplyQuote(): void {
    replyQuote.value = null;
  }

  function replyQuoteImage(
    quote: ReplyQuoteTarget,
  ): ReplyQuoteAttachment | null {
    return (
      quote.attachments.find((attachment) =>
        attachment.mime_type.startsWith('image/'),
      ) ?? null
    );
  }

  function replyQuoteFile(
    quote: ReplyQuoteTarget,
  ): ReplyQuoteAttachment | null {
    return (
      quote.attachments.find(
        (attachment) => !attachment.mime_type.startsWith('image/'),
      ) ?? null
    );
  }

  function replyQuoteFullContent(quote: ReplyQuoteTarget): string {
    const content = quote.content?.trim();
    return content || quote.preview;
  }

  function openReplyQuoteTarget(quote: ReplyQuoteTarget): void {
    const image = replyQuoteImage(quote);
    if (image) {
      replyQuotePreviewImages.value = [image];
      replyQuotePreviewInitialId.value = image.id;
      replyQuotePreviewOpen.value = true;

      return;
    }

    const file = replyQuoteFile(quote);
    if (file) {
      window.open(file.url, '_blank', 'noopener,noreferrer');

      return;
    }

    replyQuoteDialogTitle.value = quote.senderName;
    replyQuoteDialogContent.value = replyQuoteFullContent(quote);
    replyQuoteTextDialogOpen.value = true;
  }

  function reeditRecalledMessage(content: string): void {
    if (content.length === 0) {
      return;
    }

    const existing = options.replyContent.value;
    if (existing.length > 0) {
      options.replyContent.value = existing.endsWith('\n')
        ? existing + content
        : `${existing}\n${content}`;
    } else {
      options.replyContent.value = content;
    }
    options.clearReplyContentError();
  }

  function resetConversationQuoteState(): void {
    clearReplyQuote();
    replyQuotePreviewOpen.value = false;
    replyQuotePreviewImages.value = [];
    replyQuotePreviewInitialId.value = null;
    replyQuoteTextDialogOpen.value = false;
    replyQuoteDialogTitle.value = '';
    replyQuoteDialogContent.value = '';
  }

  watch(
    () => toValue(options.selection)?.conversation.id,
    resetConversationQuoteState,
  );

  return {
    replyQuote,
    quotedMessageId,
    replyQuotePreviewOpen,
    replyQuotePreviewImages,
    replyQuotePreviewInitialId,
    replyQuoteTextDialogOpen,
    replyQuoteDialogTitle,
    replyQuoteDialogContent,
    quoteMessage,
    clearReplyQuote,
    openReplyQuoteTarget,
    reeditRecalledMessage,
  };
}
