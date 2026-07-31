/**
 * 标记收件箱联系人面板发起的受控局部刷新，供页面级写入协调器精确放行。
 */
let allowedContextReloadDepth = 0;

/** 在同步创建 Inertia visit 的期间放行联系人面板局部刷新。 */
export function runAllowedInboxContextReload(start: () => void): void {
  allowedContextReloadDepth += 1;
  try {
    start();
  } finally {
    allowedContextReloadDepth -= 1;
  }
}

/** 判断当前 GET visit 是否由联系人面板的受控刷新同步创建。 */
export function isInboxContextReloadAllowed(): boolean {
  return allowedContextReloadDepth > 0;
}
