/**
 * 管理收件箱回复附件的上传、待发送预览、独立表单发送和会话归属。
 */
import {
  resolveAttachmentUploadError,
  useAttachmentUploader,
  type AttachmentPurpose,
} from '@/composables/useAttachmentUploader';
import { useI18n } from '@/composables/useI18n';
import inboxActions from '@/routes/app/inbox';
import type {
  FormReplyInboxConversationData,
  InboxSelectionData,
} from '@/types/generated';
import { useForm } from '@inertiajs/vue3';
import {
  computed,
  onUnmounted,
  ref,
  toValue,
  watch,
  type ComputedRef,
  type MaybeRefOrGetter,
  type Ref,
} from 'vue';

const MAX_REPLY_ATTACHMENT_COUNT = 10;
const IMAGE_PRELOAD_TIMEOUT_MS = 5_000;

type InboxPendingReplyAttachmentStatus = 'uploading' | 'uploaded' | 'failed';
type InboxReplyAttachmentKind = 'file' | 'image';

interface InboxPendingReplyAttachment {
  id: string;
  name: string;
  byteSize: number;
  previewUrl: string | null;
  progress: number;
  status: InboxPendingReplyAttachmentStatus;
  statusLabel: string | null;
}

interface InboxPendingReplyUpload {
  id: string;
  clientMessageId: string;
  conversationId: string;
  quotedMessageId: string | null;
  kind: InboxReplyAttachmentKind;
  purpose: AttachmentPurpose;
  attachments: InboxPendingReplyAttachment[];
}

interface UseInboxReplyAttachmentsOptions {
  selection: MaybeRefOrGetter<InboxSelectionData | null>;
  quotedMessageId: MaybeRefOrGetter<string | null>;
  blocked: MaybeRefOrGetter<boolean>;
  onQuoteConsumed?: () => void;
  onScrollToBottom: () => void | Promise<void>;
  onSwitchAfterSent: (conversationId: string) => void;
  onFocusAfterFinished: (conversationId: string) => void | Promise<void>;
  onSendFailed?: () => void;
}

interface UseInboxReplyAttachmentsReturn {
  visibleUploads: ComputedRef<InboxPendingReplyUpload[]>;
  visibleUploadCount: ComputedRef<number>;
  uploading: ComputedRef<boolean>;
  sending: ComputedRef<boolean>;
  error: Ref<string | null>;
  handleFileChange: (event: Event) => Promise<void>;
  handleImageChange: (event: Event) => Promise<void>;
  handlePaste: (event: ClipboardEvent) => void;
  removeUpload: (uploadId: string) => void;
  cancelCurrentFlows: () => void;
}

/** 创建按会话隔离的收件箱回复附件流程。 */
export function useInboxReplyAttachments(
  options: UseInboxReplyAttachmentsOptions,
): UseInboxReplyAttachmentsReturn {
  const { t } = useI18n();
  const { upload } = useAttachmentUploader();
  const pendingUploads = ref<InboxPendingReplyUpload[]>([]);
  const activeUploadIds = ref<Set<string>>(new Set());
  const error = ref<string | null>(null);
  const uploadControllers = new Map<string, AbortController>();
  const attachmentReplyForm = useForm<FormReplyInboxConversationData>({
    content: null,
    attachment_ids: [],
    client_msg_id: null,
    quoted_message_id: null,
    visitor_content: null,
    visitor_locale: null,
    source_locale: null,
  });
  let pendingUploadSequence = 0;
  let activeSendUploadId: string | null = null;
  let disposed = false;

  const selectedConversationId = computed(
    () => toValue(options.selection)?.conversation.id ?? null,
  );
  const visibleUploads = computed(() => {
    const conversationId = selectedConversationId.value;

    return conversationId === null
      ? []
      : pendingUploads.value.filter(
          (pendingUpload) => pendingUpload.conversationId === conversationId,
        );
  });
  const visibleUploadCount = computed(() => visibleUploads.value.length);
  const uploading = computed(() => activeUploadIds.value.size > 0);
  const sending = computed(() => attachmentReplyForm.processing);

  function isConversationSelected(conversationId: string): boolean {
    return selectedConversationId.value === conversationId;
  }

  /** 后续状态只能写入创建批次时所属的会话。 */
  function isUploadContextCurrent(
    pendingUpload: InboxPendingReplyUpload,
  ): boolean {
    return isConversationSelected(pendingUpload.conversationId);
  }

  function startUploading(uploadId: string): void {
    activeUploadIds.value = new Set([...activeUploadIds.value, uploadId]);
  }

  function finishUploading(uploadId: string): void {
    const next = new Set(activeUploadIds.value);
    next.delete(uploadId);
    activeUploadIds.value = next;
  }

  /** 浏览器预加载上传后的图片地址，并返回不阻塞发送的加载结果。 */
  function preloadImage(
    url: string,
  ): Promise<'loaded' | 'failed' | 'timed-out'> {
    return new Promise((resolve) => {
      const image = new Image();
      let timeoutId = 0;
      const finish = (result: 'loaded' | 'failed' | 'timed-out') => {
        window.clearTimeout(timeoutId);
        image.onload = null;
        image.onerror = null;
        resolve(result);
      };

      timeoutId = window.setTimeout(
        () => finish('timed-out'),
        IMAGE_PRELOAD_TIMEOUT_MS,
      );
      image.onload = () => finish('loaded');
      image.onerror = () => finish('failed');
      image.src = url;
    });
  }

  function validateFiles(files: File[]): boolean {
    if (files.length === 0) {
      return false;
    }

    if (files.length <= MAX_REPLY_ATTACHMENT_COUNT) {
      return true;
    }

    error.value = t('一次最多发送 {count} 个附件', {
      count: MAX_REPLY_ATTACHMENT_COUNT,
    });

    return false;
  }

  /** 创建包含会话和引用快照的待发送批次。 */
  function createPendingUpload(
    files: File[],
    kind: InboxReplyAttachmentKind,
    purpose: AttachmentPurpose,
  ): InboxPendingReplyUpload | null {
    const selection = toValue(options.selection);
    if (toValue(options.blocked) || !selection?.can_reply) {
      return null;
    }

    const conversationId = selection.conversation.id;
    if (!validateFiles(files)) {
      return null;
    }

    const uploadId = `reply-upload-${Date.now()}-${pendingUploadSequence++}`;

    return {
      id: uploadId,
      clientMessageId: uploadId,
      conversationId,
      quotedMessageId: toValue(options.quotedMessageId),
      kind,
      purpose,
      attachments: files.map((file, index) => ({
        id: `${uploadId}-${index}`,
        name: file.name || `${kind}-${index + 1}`,
        byteSize: file.size,
        previewUrl: kind === 'image' ? URL.createObjectURL(file) : null,
        progress: 0,
        status: 'uploading',
        statusLabel: null,
      })),
    };
  }

  /** 取回列表中的响应式批次实例，直传过程中的进度和状态写入必须经过它才能刷新视图。 */
  function trackedUpload(uploadId: string): InboxPendingReplyUpload {
    const pendingUpload = pendingUploads.value.find(
      (item) => item.id === uploadId,
    );
    if (!pendingUpload) {
      throw new Error('待发送附件批次不在收件箱本地列表中');
    }

    return pendingUpload;
  }

  function markUploadFailed(
    uploadId: string,
    label: string,
    includeUploaded = false,
  ): void {
    const pendingUpload = pendingUploads.value.find(
      (item) => item.id === uploadId,
    );
    if (!pendingUpload) {
      return;
    }

    pendingUpload.attachments.forEach((attachment) => {
      if (includeUploaded || attachment.status !== 'uploaded') {
        attachment.status = 'failed';
        attachment.statusLabel = label;
      }
    });
  }

  function revokeUploadPreviews(pendingUpload: InboxPendingReplyUpload): void {
    pendingUpload.attachments.forEach((attachment) => {
      if (attachment.previewUrl?.startsWith('blob:')) {
        URL.revokeObjectURL(attachment.previewUrl);
      }
    });
  }

  function removeUpload(uploadId: string): void {
    const pendingUpload = pendingUploads.value.find(
      (item) => item.id === uploadId,
    );
    if (!pendingUpload) {
      return;
    }

    uploadControllers.get(uploadId)?.abort();
    uploadControllers.delete(uploadId);
    finishUploading(uploadId);
    revokeUploadPreviews(pendingUpload);
    pendingUploads.value = pendingUploads.value.filter(
      (item) => item.id !== uploadId,
    );

    const hasFailedUpload = pendingUploads.value.some(
      (item) =>
        item.conversationId === pendingUpload.conversationId &&
        item.attachments.some((attachment) => attachment.status === 'failed'),
    );
    if (!hasFailedUpload) {
      error.value = null;
    }
  }

  /** 发送已完成直传的附件批次。 */
  function sendAttachments(
    pendingUpload: InboxPendingReplyUpload,
    attachmentIds: string[],
  ): Promise<void> {
    activeSendUploadId = pendingUpload.id;
    let sentSuccessfully = false;

    function failSend(message: string, details: Record<string, unknown>): void {
      if (disposed || !isUploadContextCurrent(pendingUpload)) {
        return;
      }

      options.onSendFailed?.();
      console.warn('[inbox-reply-attachments] 附件消息发送失败', {
        instance: 'system',
        conversationId: pendingUpload.conversationId,
        kind: pendingUpload.kind,
        purpose: pendingUpload.purpose,
        stage: 'send',
        ...details,
      });
      markUploadFailed(pendingUpload.id, t('发送失败'), true);
      error.value = message;
    }

    return new Promise((resolve) => {
      attachmentReplyForm
        .transform(() => ({
          content: null,
          attachment_ids: attachmentIds,
          client_msg_id: pendingUpload.clientMessageId,
          quoted_message_id: pendingUpload.quotedMessageId,
          visitor_content: null,
          visitor_locale: null,
          source_locale: null,
        }))
        .post(
          inboxActions.conversations.reply.url({
            conversation: pendingUpload.conversationId,
          }),
          {
            preserveScroll: true,
            preserveState: true,
            only: ['selection', 'tab_counts'],
            onSuccess: () => {
              sentSuccessfully = true;
              removeUpload(pendingUpload.id);

              if (disposed || !isUploadContextCurrent(pendingUpload)) {
                return;
              }

              if (
                toValue(options.quotedMessageId) ===
                pendingUpload.quotedMessageId
              ) {
                options.onQuoteConsumed?.();
              }
              void options.onScrollToBottom();
            },
            onError: (errors) => {
              failSend(errors.attachment_ids ?? t('发送失败'), {
                failureType: 'validation',
                errorFields: Object.keys(errors),
              });
            },
            onNetworkError: (requestError) => {
              failSend(t('发送失败'), {
                failureType: 'network',
                errorType: requestError.name,
              });
            },
            onHttpException: (response) => {
              failSend(t('发送失败'), {
                failureType: 'http',
                status: response.status,
              });
            },
            onCancel: () => {
              failSend(t('发送失败'), {
                failureType: 'cancelled',
              });
            },
            onFinish: () => {
              if (activeSendUploadId === pendingUpload.id) {
                activeSendUploadId = null;
              }
              attachmentReplyForm.reset();
              attachmentReplyForm.clearErrors();
              if (!disposed && isUploadContextCurrent(pendingUpload)) {
                // 成功后的导航必须在表单 onFinish 后执行，避免嵌套 Inertia visit 将发送判为取消。
                if (sentSuccessfully) {
                  options.onSwitchAfterSent(pendingUpload.conversationId);
                }
                void options.onFocusAfterFinished(pendingUpload.conversationId);
              }
              resolve();
            },
          },
        );
    });
  }

  async function uploadAndSend(
    files: File[],
    purpose: AttachmentPurpose,
    kind: InboxReplyAttachmentKind,
  ): Promise<void> {
    if (
      disposed ||
      toValue(options.blocked) ||
      uploading.value ||
      sending.value
    ) {
      return;
    }

    const pendingUpload = createPendingUpload(files, kind, purpose);
    if (pendingUpload === null) {
      return;
    }

    const controller = new AbortController();
    pendingUploads.value.push(pendingUpload);
    const trackedPendingUpload = trackedUpload(pendingUpload.id);
    uploadControllers.set(pendingUpload.id, controller);
    startUploading(pendingUpload.id);
    error.value = null;
    void options.onScrollToBottom();

    const uploadedAttachmentIds: string[] = [];
    const preloadTasks: Array<Promise<'loaded' | 'failed' | 'timed-out'>> = [];

    try {
      for (const [index, file] of files.entries()) {
        const pendingAttachment = trackedPendingUpload.attachments[index];
        const attachment = await upload(file, {
          purpose,
          context: {},
          signal: controller.signal,
          onProgress: (value) => {
            pendingAttachment.progress = Math.min(100, Math.max(0, value));
          },
        });

        pendingAttachment.name = attachment.name;
        pendingAttachment.byteSize = attachment.byte_size;
        pendingAttachment.progress = 100;
        pendingAttachment.status = 'uploaded';
        uploadedAttachmentIds.push(attachment.id);

        if (attachment.full_url && kind === 'image') {
          preloadTasks.push(preloadImage(attachment.full_url));
        }
      }

      const preloadResults = await Promise.all(preloadTasks);
      const failedPreloadCount = preloadResults.filter(
        (result) => result === 'failed',
      ).length;
      const timedOutPreloadCount = preloadResults.filter(
        (result) => result === 'timed-out',
      ).length;
      if (failedPreloadCount > 0 || timedOutPreloadCount > 0) {
        console.info(
          '[inbox-reply-attachments] 图片预加载未完成，继续发送附件回复',
          {
            instance: 'system',
            conversationId: pendingUpload.conversationId,
            imageCount: preloadResults.length,
            failedPreloadCount,
            timedOutPreloadCount,
          },
        );
      }
    } catch (uploadError) {
      if (!disposed && !controller.signal.aborted) {
        options.onSendFailed?.();
        const message = resolveAttachmentUploadError(
          uploadError,
          t,
          kind === 'image' ? '图片上传失败' : '附件上传失败',
        );
        console.warn('[inbox-reply-attachments] 附件上传失败', {
          instance: 'system',
          conversationId: pendingUpload.conversationId,
          kind,
          purpose,
          stage: 'upload',
          error:
            uploadError instanceof Error ? uploadError.name : 'UnknownError',
        });
        error.value = message;
        markUploadFailed(pendingUpload.id, t('上传失败'));
      }

      return;
    } finally {
      uploadControllers.delete(pendingUpload.id);
      finishUploading(pendingUpload.id);
    }

    if (
      disposed ||
      controller.signal.aborted ||
      !isUploadContextCurrent(pendingUpload)
    ) {
      return;
    }

    await sendAttachments(pendingUpload, uploadedAttachmentIds);
  }

  async function handleFileChange(event: Event): Promise<void> {
    const target = event.target as HTMLInputElement;
    const files = Array.from(target.files ?? []);
    target.value = '';

    await uploadAndSend(files, 'conversation_file', 'file');
  }

  async function handleImageChange(event: Event): Promise<void> {
    const target = event.target as HTMLInputElement;
    const files = Array.from(target.files ?? []);
    target.value = '';

    await uploadAndSend(files, 'conversation_image', 'image');
  }

  function pastedImageFiles(event: ClipboardEvent): File[] {
    return Array.from(event.clipboardData?.items ?? [])
      .filter((item) => item.kind === 'file' && item.type.startsWith('image/'))
      .map((item, index) => {
        const file = item.getAsFile();
        if (file === null) {
          return null;
        }
        if (file.name) {
          return file;
        }

        return new File([file], `pasted-image-${Date.now()}-${index + 1}.png`, {
          type: file.type || 'image/png',
        });
      })
      .filter((file): file is File => file !== null);
  }

  function handlePaste(event: ClipboardEvent): void {
    const selection = toValue(options.selection);
    if (
      !selection?.can_reply ||
      disposed ||
      toValue(options.blocked) ||
      uploading.value ||
      sending.value
    ) {
      return;
    }

    const imageFiles = pastedImageFiles(event);
    if (imageFiles.length === 0) {
      return;
    }

    event.preventDefault();
    void uploadAndSend(imageFiles, 'conversation_image', 'image');
  }

  /** 取消未完成的附件回复并清理本地状态，供会话切换和显式导航调用。 */
  function cancelCurrentFlows(): void {
    const activeSend =
      activeSendUploadId !== null || attachmentReplyForm.processing;
    const activeTaskCount = uploadControllers.size + (activeSend ? 1 : 0);
    if (activeTaskCount > 0) {
      const activeContext = pendingUploads.value.find(
        (pendingUpload) =>
          uploadControllers.has(pendingUpload.id) ||
          pendingUpload.id === activeSendUploadId,
      );
      console.info('[inbox-reply-attachments] 取消未完成的附件回复', {
        instance: 'system',
        conversationId:
          activeContext?.conversationId ?? selectedConversationId.value,
        count: activeTaskCount,
      });
    }

    uploadControllers.forEach((controller) => controller.abort());
    uploadControllers.clear();

    if (activeSendUploadId !== null || attachmentReplyForm.processing) {
      attachmentReplyForm.cancel();
    }
    activeSendUploadId = null;
    attachmentReplyForm.reset();
    attachmentReplyForm.clearErrors();

    pendingUploads.value.forEach(revokeUploadPreviews);
    pendingUploads.value = [];
    activeUploadIds.value = new Set();
    error.value = null;
  }

  const stopContextWatch = watch(
    selectedConversationId,
    () => cancelCurrentFlows(),
    { flush: 'sync' },
  );

  function cleanup(): void {
    if (disposed) {
      return;
    }

    disposed = true;
    stopContextWatch();
    uploadControllers.forEach((controller) => controller.abort());
    uploadControllers.clear();
    attachmentReplyForm.cancel();
    activeSendUploadId = null;
    pendingUploads.value.forEach(revokeUploadPreviews);
    pendingUploads.value = [];
    activeUploadIds.value = new Set();
    error.value = null;
  }

  onUnmounted(cleanup);

  return {
    visibleUploads,
    visibleUploadCount,
    uploading,
    sending,
    error,
    handleFileChange,
    handleImageChange,
    handlePaste,
    removeUpload,
    cancelCurrentFlows,
  };
}
