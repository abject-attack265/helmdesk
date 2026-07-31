/**
 * 文件说明：独立访客端页面代码，承接嵌入式或公开访问场景。
 */
import { onMounted, ref } from 'vue';

/**
 * 独立页使用自己的最小化文案字典，与后台语言偏好隔离。
 */

export const STANDALONE_LOCALE_STORAGE_KEY = 'standalone_locale';

const dictionaries = {
  'zh-CN': {} as Record<string, string>,
  en: {
    发送: 'Send',
    添加附件: 'Attach file',
    添加图片: 'Attach image',
    选择表情: 'Pick emoji',
    移除: 'Remove',
    移除附件: 'Remove attachment',
    '附件上传中...': 'Uploading attachment...',
    '附件上传中... {progress}%': 'Uploading attachment... {progress}%',
    '一次最多发送 {count} 个附件':
      'You can send up to {count} attachments at a time',
    上传失败: 'Upload failed',
    '上传失败，请重试': 'Upload failed. Please try again.',
    图片上传失败: 'Failed to upload image',
    附件上传失败: 'Failed to upload attachment',
    发送失败: 'Failed to send',
    '缺少直传表单参数。': 'Missing direct upload form fields.',
    '缺少直传地址。': 'Missing direct upload URL.',
    '缺少分片大小。': 'Missing multipart chunk size.',
    '正在加载会话……': 'Loading conversation…',
    '发送失败，请稍后重试': 'Failed to send, please try again.',
    接口返回格式异常: 'Unexpected response format.',
    '会话尚未准备好，请稍后重试':
      'Session is not ready yet, please try again later.',
    客服: 'Agent',
    我: 'Me',
    你: 'You',
    复制: 'Copy',
    引用: 'Quote',
    撤回: 'Recall',
    重新编辑: 'Re-edit',
    取消引用: 'Cancel quote',
    引用消息内容: 'Quoted message',
    你撤回了一条消息: 'You recalled a message',
    对方撤回了一条消息: 'A message was recalled',
    图片: 'Image',
    文件: 'File',
    无内容: 'No content',
    'AI 应答中': 'AI is replying',
    正在等待客服接入: 'Waiting for an agent',
    客服正在为您服务: 'Agent is helping you',
    客服正在输入: 'The agent is typing',
    等待您的回复: 'Waiting for your reply',
    关闭聊天: 'Close chat',
    进入聊天: 'Enter chat',
    返回首页: 'Back to home',
    '由 HelmDesk 提供技术支持': 'Powered by HelmDesk',
    '这是一条预览示例回复，仅用于查看气泡样式。':
      'This is a sample preview reply, just to show the bubble style.',
    服务暂时不可用: 'Service temporarily unavailable',
    '我们正在调整这个入口，请稍后再来。':
      "We're updating this entry. Please check back later.",
    '本次服务您还满意吗？': 'How was your experience?',
    满意: 'Satisfied',
    不满意: 'Unsatisfied',
    '补充说明（选填）': 'Add a note (optional)',
    提交评价: 'Submit',
    感谢您的评价: 'Thanks for your feedback!',
  } satisfies Record<string, string>,
} as const;

export type StandaloneLocale = keyof typeof dictionaries;

const defaultLocale: StandaloneLocale = 'en';

const isSupportedLocale = (value: unknown): value is StandaloneLocale =>
  typeof value === 'string' && value in dictionaries;

const normalizeStandaloneLocale = (
  value: string | null | undefined,
): StandaloneLocale | null => {
  if (!value) {
    return null;
  }

  const normalized = value.replace('_', '-').toLowerCase();
  if (normalized === 'en' || normalized.startsWith('en-')) {
    return 'en';
  }
  if (normalized === 'zh' || normalized.startsWith('zh-')) {
    return 'zh-CN';
  }

  return null;
};

const getBrowserLocale = (): StandaloneLocale | null => {
  if (typeof navigator === 'undefined') {
    return null;
  }

  for (const value of [...(navigator.languages ?? []), navigator.language]) {
    const locale = normalizeStandaloneLocale(value);
    if (locale) {
      return locale;
    }
  }

  return null;
};

const currentLocale = ref<StandaloneLocale>(
  getBrowserLocale() ?? defaultLocale,
);

const syncFromStorage = (): void => {
  if (typeof window === 'undefined') {
    return;
  }

  const stored = window.localStorage.getItem(STANDALONE_LOCALE_STORAGE_KEY);
  if (stored === null) {
    return;
  }
  if (isSupportedLocale(stored)) {
    currentLocale.value = stored;
    return;
  }

  throw new Error(`Unsupported standalone locale: ${stored}`);
};

export function initializeStandaloneLocale(): void {
  syncFromStorage();
}

export function useStandaloneI18n() {
  onMounted(syncFromStorage);

  function t(key: string, params?: Record<string, string | number>): string {
    const dict = dictionaries[currentLocale.value] as Record<string, string>;
    let text = dict[key] ?? key;

    if (params) {
      for (const [name, value] of Object.entries(params)) {
        text = text.replaceAll(`{${name}}`, String(value));
      }
    }

    return text;
  }

  function updateLocale(
    next: StandaloneLocale,
    options: { persist?: boolean } = {},
  ): void {
    if (!isSupportedLocale(next)) {
      throw new Error(`Unsupported standalone locale: ${next}`);
    }

    currentLocale.value = next;

    if (options.persist === false || typeof window === 'undefined') {
      return;
    }

    window.localStorage.setItem(STANDALONE_LOCALE_STORAGE_KEY, next);
    document.cookie = `${STANDALONE_LOCALE_STORAGE_KEY}=${next};path=/;max-age=${365 * 24 * 60 * 60};SameSite=Lax`;
  }

  return {
    locale: currentLocale,
    t,
    updateLocale,
    isSupportedLocale,
  };
}
