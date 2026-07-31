/**
 * 文件说明：封装后台桌面通知展示能力。
 */
interface BrowserNotificationOptions {
  title: string;
  body: string;
  url?: string;
  onClick?: () => void;
}

export function browserNotificationsAllowed(): boolean {
  return (
    typeof window !== 'undefined' &&
    'Notification' in window &&
    window.Notification.permission === 'granted'
  );
}

export function showBrowserNotification(
  options: BrowserNotificationOptions,
): void {
  if (!browserNotificationsAllowed()) {
    return;
  }

  const notification = new window.Notification(options.title, {
    body: options.body,
    icon: '/images/logo-mark.png',
  });

  notification.onclick = () => {
    notification.close();
    if (options.onClick) {
      options.onClick();
      return;
    }

    if (options.url) {
      window.focus();
      window.location.href = options.url;
    }
  };
}
