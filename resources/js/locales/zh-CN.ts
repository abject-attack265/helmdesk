/**
 * 文件说明：中文语言包，维护前端页面默认展示文案。
 */
import aiSettings from './zh-CN/ai-settings';
import app from './zh-CN/app';
import appSettings from './zh-CN/app-settings';
import auth from './zh-CN/auth';
import common from './zh-CN/common';
import contact from './zh-CN/contact';
import conversation from './zh-CN/conversation';
import settings from './zh-CN/settings';

export default {
  ...common,
  ...settings,
  ...auth,
  ...app,
  ...aiSettings,
  ...appSettings,
  ...contact,
  ...conversation,
} as const;
