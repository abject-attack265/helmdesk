/**
 * 文件说明：英文语言包，页面通过 useI18n 按中文 key 读取英文文案。
 */
// 设置相关（英文）
export default {
  // 设置
  设置: 'Settings',
  管理你的个人资料和账户设置: 'Manage your profile and account settings',
  个人资料: 'Profile',
  密码: 'Password',
  两步验证: 'Two-Factor Auth',
  外观: 'Appearance',
  语言和时区: 'Language & Timezone',
  通知: 'Notifications',

  // 个人资料设置
  个人资料设置: 'Profile settings',
  个人信息: 'Profile information',
  更新你的姓名和电子邮件地址: 'Update your name and email address',
  姓名: 'Name',
  电子邮件地址: 'Email address',
  '你的电子邮件地址未验证。': 'Your email address is unverified.',
  '点击这里重新发送验证邮件。': 'Click here to resend the verification email.',
  '新的验证链接已发送到你的电子邮件地址。':
    'A new verification link has been sent to your email address.',

  // 密码设置
  密码设置: 'Password settings',
  修改密码: 'Update password',
  确保你的账户使用长且随机的密码以保证安全:
    'Ensure your account is using a long, random password to stay secure',
  当前密码: 'Current password',
  新密码: 'New password',
  确认密码: 'Confirm password',

  // 外观设置
  外观设置: 'Appearance settings',
  更新你账户的外观设置: "Update your account's appearance settings",
  浅色: 'Light',
  深色: 'Dark',
  跟随系统: 'System',

  // 两步验证
  两步验证设置: 'Two-factor authentication',
  管理你的两步验证设置: 'Manage your two-factor authentication settings',
  已启用: 'Enabled',
  已禁用: 'Disabled',
  '启用两步验证后，登录时将需要输入安全验证码。该验证码可以通过手机上支持 TOTP 的应用程序获取。':
    'After enabling two-factor authentication, you will be prompted for a secure verification code when logging in. You can get this code from a TOTP app on your phone.',
  继续设置: 'Continue setup',
  启用两步验证: 'Enable two-factor authentication',
  '启用两步验证后，登录时将需要输入安全的随机验证码，你可以通过手机上支持 TOTP 的应用程序获取该验证码。':
    'After enabling two-factor authentication, you will be prompted for a secure random verification code when logging in. You can get this code from a TOTP app on your phone.',
  禁用两步验证: 'Disable two-factor authentication',
  '要完成两步验证的启用，请扫描二维码或在身份验证器应用中输入设置密钥':
    'To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app.',
  两步验证现已启用: 'Two-factor authentication is now enabled',
  '两步验证现已启用。扫描二维码或在身份验证器应用中输入设置密钥。':
    'Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.',
  验证身份验证码: 'Verify authentication code',
  '输入来自身份验证器应用的 6 位数字验证码':
    'Enter the 6-digit code from your authenticator app.',
  '或者，手动输入密钥': 'Or, enter the key manually',
  两步验证恢复码: 'Two-factor recovery codes',
  '如果丢失两步验证设备，恢复码可以让你重新访问账户。请将它们存储在安全的密码管理器中。':
    'If you lose your two-factor device, recovery codes can help you regain access to your account. Store them in a secure password manager.',
  查看恢复码: 'View recovery codes',
  隐藏恢复码: 'Hide recovery codes',
  重新生成恢复码: 'Regenerate recovery codes',
  '每个恢复码只能使用一次来访问你的账户，使用后将被删除。如需更多恢复码，请点击上方的':
    'Each recovery code can only be used once to access your account and will be deleted after use. If you need more recovery codes, click',
  '"重新生成恢复码"': '"Regenerate recovery codes"',

  // 语言和时区
  语言和时区设置: 'Language and Timezone Settings',
  语言偏好: 'Language preference',
  选择你的首选语言: 'Select your preferred language',
  当前语言: 'Current language',
  选择语言: 'Select language',
  简体中文: '简体中文',
  English: 'English',
  时区设置: 'Timezone Settings',
  '选择你的时区，用于正确显示时间':
    'Select your timezone for accurate time display',
  当前时区: 'Current timezone',
  选择时区: 'Select timezone',
  自动检测: 'Auto Detect',
  浏览器时区: 'Browser timezone',

  // 时区搜索
  未找到匹配的时区: 'No matching timezones found',

  // 通知设置
  通知设置: 'Notification settings',
  控制客服后台的新消息桌面通知和声音提醒:
    'Control desktop notifications and sounds for new helpdesk messages',
  桌面通知: 'Desktop notifications',
  浏览器已允许桌面通知: 'Desktop notifications are allowed',
  '浏览器已拒绝桌面通知，请在浏览器设置中重新允许':
    'Desktop notifications are blocked. Re-enable them in your browser settings.',
  尚未请求浏览器通知权限:
    'Browser notification permission has not been requested',
  当前浏览器不支持桌面通知:
    'This browser does not support desktop notifications',
  请求浏览器权限: 'Request browser permission',
  试听桌面通知: 'Test desktop notification',
  'HelmDesk 通知测试': 'HelmDesk notification test',
  桌面通知可以正常显示: 'Desktop notifications can be displayed',
  声音提醒: 'Sound alerts',
  新消息到达时播放一段提示音: 'Play a short sound when new messages arrive',
  新消息到达时播放选中的提示音:
    'Play the selected sound when new messages arrive',
  试听提示音: 'Test sound',
  提醒范围: 'Alert scope',
  选择哪些会话事件会触发桌面通知或声音:
    'Choose which conversation events trigger desktop notifications or sounds',
  分配给我的会话: 'Conversations assigned to me',
  访客新消息和会话转接给我时提醒:
    'Alert me for visitor messages and transfers to me',
  待接入会话: 'Pending handoff conversations',
  'AI 转人工或访客请求人工时提醒':
    'Alert me when AI or visitors request human handoff',
  新的访客消息: 'New visitor message',
  会话已转接给你: 'Conversation transferred to you',
  新的待接入会话: 'New pending handoff',
  点击查看会话: 'Click to view the conversation',
} as const;
