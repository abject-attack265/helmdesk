/**
 * 文件说明：经验提炼页面的英文国际化文案。
 */
export default {
  经验提炼: 'Experience extraction',
  '从人工接待会话中批量提炼可复用经验，润色后沉淀为「{name}」的问答对':
    'Extract reusable experience from human-handled conversations in batch, then polish and save it as Q&A entries in "{name}"',
  创建任务: 'New task',
  '暂无提炼任务，点击「创建任务」从人工会话中提取经验':
    'No extraction tasks yet. Click "New task" to extract experience from human-handled conversations',
  '确认删除提炼任务？': 'Delete this extraction task?',
  '删除后该任务及其候选经验会一并移除且不可恢复；已采纳进知识库的问答不受影响。':
    'The task and its candidates will be removed permanently; Q&A entries already adopted into knowledge bases are not affected.',
  进行中的任务不能删除: 'A running task cannot be deleted',
  '会话 {count} 个': '{count} conversations',
  '{count} 条': '{count}',
  '待处理 {count} 条': '{count} pending',
  会话数: 'Conversations',
  候选经验: 'Candidates',
  触发人: 'Triggered by',
  结果: 'Results',
  '经验提炼-会话列表': 'Experience extraction - Conversations',
  '经验提炼-结果': 'Experience extraction - Results',
  会话列表: 'Conversations',
  提炼结果: 'Results',
  '正在分析 {count} 个会话…': 'Analyzing {count} conversations…',
  提炼失败: 'Extraction failed',
  提炼完成后候选经验将出现在这里:
    'Candidates will appear here once the extraction completes',
  '已有一次提炼正在进行中，请等待其完成':
    'An extraction is already running. Please wait for it to finish',
  '勾选要分析的联系人，每人在所选时间段内的会话会连起来分析，单次最多 {max} 个会话':
    'Select the contacts to analyze. Each contact’s conversations in the chosen period are analyzed together, up to {max} conversations per run',
  开始日期: 'Start date',
  结束日期: 'End date',
  '时间跨度最多 {days} 天，超出会自动收敛':
    'The period spans at most {days} days; anything longer is narrowed automatically',
  当前筛选条件下没有人工参与过的联系人:
    'No contacts with human-handled conversations match the current filters',
  全部客服: 'All teammates',
  '只筛出该客服服务过的联系人；提炼时仍会带上这些联系人的全部会话':
    'Only narrows down to contacts this teammate has served; extraction still includes all of those contacts’ conversations',
  搜索主题或摘要: 'Search subject or summary',
  选择联系人: 'Select contact',
  展开会话明细: 'Expand conversations',
  '（无主题）': '(No subject)',
  关闭时间: 'Closed at',
  最近关闭: 'Last closed',
  人工消息: 'Agent messages',
  '人工 {count} 条': '{count} agent messages',
  已提炼过: 'Extracted before',
  '已选 {contacts} 个联系人 · 共 {conversations} 个会话':
    '{contacts} contacts selected · {conversations} conversations',
  '单次最多提炼 {max} 个会话，请减少勾选':
    'Up to {max} conversations per run, please deselect some',
  全选本页未提炼: 'Select all unextracted on this page',
  清空选择: 'Clear selection',
  开始提炼: 'Start extraction',
  已采纳: 'Adopted',
  已丢弃: 'Discarded',
  在左侧选择一条候选经验: 'Select a candidate on the left',
  '来源会话 {count} 个': '{count} source conversations',
  来源会话: 'Source conversations',
  '（点击核对原始对话）': '(click to review the original conversation)',
  丢弃: 'Discard',
  '采纳后写入「{name}」': 'Adopted entries are saved to "{name}"',
  主问题: 'Primary question',
  每行一条: 'One per line',
  采纳并写入知识库: 'Adopt and save to knowledge base',
  暂无内容: 'No content yet',
} as const;
