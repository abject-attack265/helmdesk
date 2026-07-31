/**
 * 文件说明：英文语言包，页面通过 useI18n 按中文 key 读取英文文案。
 */
// 认证相关（英文）
export default {
  // 认证 - 登录
  登录你的账户: 'Log in to your account',
  在下方输入你的邮箱和密码以登录:
    'Enter your email and password below to log in',
  登录: 'Log in',
  '忘记密码？': 'Forgot password?',
  记住我: 'Remember me',
  '没有账户？': "Don't have an account?",
  注册: 'Sign up',

  // 认证 - 注册
  创建账户: 'Create an account',
  在下方输入你的详细信息以创建账户:
    'Enter your details below to create your account',
  '已有账户？': 'Already have an account?',

  // 认证 - 重置密码
  重置密码: 'Reset password',
  请在下方输入你的新密码: 'Please enter your new password below',
  电子邮件: 'Email',

  // 认证 - 两步验证挑战
  身份验证码: 'Authentication Code',
  '输入你的身份验证器应用程序提供的验证码。':
    'Enter the authentication code provided by your authenticator application.',
  使用恢复码登录: 'login using a recovery code',
  恢复码: 'Recovery Code',
  '请输入你的紧急恢复码之一来确认访问你的账户。':
    'Please confirm access to your account by entering one of your emergency recovery codes.',
  使用身份验证码登录: 'login using an authentication code',
  或者你可以: 'or you can',
  '验证失败后可以重新输入并重试。': 'You can enter a new code and try again.',
  '正在验证…': 'Verifying…',
  返回登录并切换账号: 'Return to login and switch account',
  '正在返回登录页…': 'Returning to login…',
  '为保障应用管理员账号安全，建议启用两步验证；你也可以暂时跳过':
    'Enable two-factor authentication to protect the application owner account, or skip it for this session.',
  '启用两步验证后，每次登录都需要输入身份验证器应用提供的动态验证码。请先生成密钥。':
    'After enabling two-factor authentication, each login requires a code from your authenticator app. Generate a secret to begin.',
  '使用 Google Authenticator 等身份验证器应用扫描二维码，或手动输入密钥。':
    'Scan the QR code with Google Authenticator or another authenticator app, or enter the secret manually.',
  复制密钥: 'Copy secret',
  输入验证码以确认: 'Enter a code to confirm',
  确认并进入应用: 'Confirm and enter the application',
  暂时跳过: 'Skip for now',
  '两步验证已启用，可以进入应用。':
    'Two-factor authentication is enabled. You can enter the application.',
  进入应用: 'Enter the application',

  // 认证 - 确认密码
  确认你的密码: 'Confirm your password',
  '这是应用程序的安全区域。请在继续之前确认你的密码。':
    'This is a secure area of the application. Please confirm your password before continuing.',
  确认密码页面: 'Confirm password',

  // 认证 - 忘记密码
  忘记密码: 'Forgot password',
  输入你的电子邮件以接收密码重置链接:
    'Enter your email to receive a password reset link',
  发送密码重置链接: 'Email password reset link',
  '正在发送重置链接…': 'Sending reset link…',
  '或者，返回': 'Or, return to',

  // 认证 - 验证邮箱
  验证电子邮件: 'Verify email',
  '请点击我们刚刚发送给你的电子邮件中的链接来验证你的电子邮件地址。':
    'Please verify your email address by clicking on the link we just emailed to you.',
  邮箱验证: 'Email verification',
  '新的验证链接已发送到你注册时提供的电子邮件地址。':
    'A new verification link has been sent to the email address you provided during registration.',
  重新发送验证邮件: 'Resend verification email',
  退出登录: 'Log out',
} as const;
