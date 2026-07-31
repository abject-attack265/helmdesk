/**
 * 管理接待方活动快照的版本顺序与本地租约过期。
 */
import type { ReceptionActivityStateData } from '@/types/generated';
import { onScopeDispose, readonly, ref } from 'vue';

export function useReceptionActivityState() {
  const active = ref(false);
  const revision = ref(0);
  let expirationTimer: ReturnType<typeof setTimeout> | null = null;

  function clearExpirationTimer(): void {
    if (expirationTimer === null) {
      return;
    }

    clearTimeout(expirationTimer);
    expirationTimer = null;
  }

  /** 应用晚于当前版本的活动快照，并按剩余租约自动停止。 */
  function apply(activity: ReceptionActivityStateData): boolean {
    if (activity.revision <= revision.value) {
      return false;
    }

    clearExpirationTimer();
    revision.value = activity.revision;
    active.value = activity.active;

    if (activity.active) {
      expirationTimer = setTimeout(() => {
        // 保留版本，避免重复快照在租约到期后重新显示活动状态。
        active.value = false;
        expirationTimer = null;
      }, activity.hold_ms);
    }

    return true;
  }

  function reset(): void {
    clearExpirationTimer();
    active.value = false;
    revision.value = 0;
  }

  onScopeDispose(reset);

  return {
    active: readonly(active),
    apply,
    reset,
  };
}
