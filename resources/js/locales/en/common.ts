/**
 * 文件说明：英文语言包，页面通过 useI18n 按中文 key 读取英文文案。
 */
// 通用（英文）
export default {
  // 通用
  列表: 'List',
  '加载中...': 'Loading...',
  发生错误: 'An error occurred',
  选择文件: 'Choose file',
  选择图片: 'Choose image',
  未选择任何文件: 'No file chosen',
  管理员: 'Admin',
  客服: 'Teammate',
  所有者: 'Owner',
  在线: 'Online',
  离线: 'Offline',
  个人设置: 'Personal settings',

  // 状态 / 安全
  已启用: 'Enabled',
  未启用: 'Not enabled',
  重置两步验证: 'Reset two-factor Auth',
  '确认重置两步验证？': 'Reset two-factor Auth?',
  '重置后，该用户需要重新绑定两步验证。':
    'After resetting, the user will need to set up two-factor Auth again.',
  '重置中...': 'Resetting...',

  // 提示通知
  成功: 'Success',
  错误: 'Error',
  警告: 'Warning',
  提示: 'Info',
  通知: 'Notice',
  '请求失败，请稍后重试': 'Request failed. Please try again later.',

  // 通用操作
  保存: 'Save',
  '保存中...': 'Saving...',
  继续: 'Continue',
  取消: 'Cancel',
  确认: 'Confirm',
  关闭: 'Close',
  返回: 'Back',
  创建: 'Create',
  '创建中...': 'Creating...',
  编辑: 'Edit',
  删除: 'Delete',
  '删除中...': 'Deleting...',
  '检测中...': 'Testing...',
  当前在用: 'In use',
  确认删除: 'Confirm Delete',
  恢复: 'Restore',
  '恢复中...': 'Restoring...',
  确认恢复: 'Confirm restore',
  删除时间: 'Deleted at',
  测试: 'Test',
  切换在线: 'Go online',
  导航菜单: 'Navigation menu',
  '浏览应用导航菜单。': 'Browse the application navigation menu.',
  打开导航: 'Open navigation',
  关闭导航: 'Close navigation',
  收起侧边栏: 'Collapse sidebar',
  展开侧边栏: 'Expand sidebar',
  前往仪表板: 'Go to dashboard',

  // 媒体类型
  文本: 'Text',
  图片: 'Image',
  音频: 'Audio',
  视频: 'Video',
  文件: 'File',
  大语言模型: 'LLM',
  嵌入模型: 'Embedding',
  重排序模型: 'ReRank',
  重排序: 'ReRank',

  // 字段/占位
  名称: 'Name',
  颜色: 'Color',
  描述: 'Description',
  来源: 'Source',
  更新时间: 'Updated at',
  '你当前处于离线状态，回复只会处理此会话，不会接收新的转人工会话。':
    'You are offline. Replies only affect this conversation and will not make you receive new human handoffs.',
  条: 'items',

  // 筛选
  清除筛选: 'Clear filter',
  已添加标签: 'Added tag',
  已移除标签: 'Removed tag',
  // 补充：缺失的英文文案
  图片预览: 'Image preview',
  '图片附件预览，按 Esc 关闭。':
    'Image attachment preview. Press Esc to close.',
  上一张: 'Previous',
  下一张: 'Next',
} as const;
