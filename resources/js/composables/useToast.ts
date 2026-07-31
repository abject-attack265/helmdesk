/**
 * 文件说明：toast 状态管理，并统一拦截 Inertia flash 与 axios 错误转成 toast。
 */
import { router } from '@inertiajs/vue3';
import { onUnmounted, ref } from 'vue';
import { useI18n } from './useI18n';

export type ToastType = 'default' | 'success' | 'error' | 'warning' | 'info';

export interface Toast {
  id: string;
  title?: string;
  description?: string;
  type?: ToastType;
  duration?: number;
  action?: {
    label: string;
    onClick: () => void;
  };
}

const DEFAULT_TOAST_DURATION = 5000;
const IMPORTANT_TOAST_DURATION = 8000;

const toasts = ref<Toast[]>([]);
let toastIdCounter = 0;

const removeToast = (id: string): void => {
  const index = toasts.value.findIndex((t) => t.id === id);
  if (index !== -1) {
    toasts.value.splice(index, 1);
  }
};

export function useToast() {
  const { t } = useI18n();

  const addToast = (toast: Omit<Toast, 'id'>): string => {
    const id = `toast-${++toastIdCounter}`;
    const duration = toast.duration ?? DEFAULT_TOAST_DURATION;

    toasts.value = [{ id, ...toast, duration }];

    return id;
  };

  const toast = {
    success: (message: string) => {
      return addToast({
        title: t('成功'),
        description: message,
        type: 'success',
      });
    },
    error: (message: string) => {
      return addToast({
        title: t('错误'),
        description: message,
        type: 'error',
        duration: IMPORTANT_TOAST_DURATION,
      });
    },
    warning: (message: string) => {
      return addToast({
        title: t('警告'),
        description: message,
        type: 'warning',
        duration: IMPORTANT_TOAST_DURATION,
      });
    },
    info: (message: string) => {
      return addToast({ title: t('提示'), description: message, type: 'info' });
    },
    default: (message: string) => {
      return addToast({
        title: t('通知'),
        description: message,
        type: 'default',
      });
    },
  };

  return {
    toasts,
    toast,
    addToast,
    removeToast,
  };
}

let apiInterceptorSetup = false;

/**
 * 统一拦截 Inertia 表单/flash 错误与 axios 响应错误，转成 error toast。
 */
export function useErrorHandling() {
  const { t } = useI18n();
  const { toast } = useToast();

  const removeErrorListener = router.on('error', (event) => {
    const errors = event.detail.errors as { toast?: string };

    if (errors.toast) {
      toast.error(errors.toast);
    }
  });

  const removeFlashListener = router.on('flash', (event) => {
    const flash = event.detail.flash as {
      toast?: {
        type?: ToastType;
        message: string;
      };
    };

    if (flash.toast) {
      toast[flash.toast.type ?? 'success'](flash.toast.message);
    }
  });

  onUnmounted(() => {
    removeErrorListener();
    removeFlashListener();
  });

  if (!apiInterceptorSetup) {
    import('axios').then(({ default: axios }) => {
      axios.interceptors.response.use(
        (response) => response,
        (error: unknown) => {
          if (axios.isCancel(error)) {
            return Promise.reject(error);
          }

          const message = axios.isAxiosError<{ message?: string }>(error)
            ? (error.response?.data?.message ?? error.message)
            : error instanceof Error
              ? error.message
              : t('请求失败，请稍后重试');
          toast.error(message);

          return Promise.reject(error);
        },
      );

      apiInterceptorSetup = true;
    });
  }
}
