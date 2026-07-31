/**
 * 应用设置相关的英文前端国际化文案。
 */
export default {
  // 设置菜单
  应用: 'Instance',
  常规设置: 'General',

  // 自定义字段
  '给联系人增加需要记录的信息。':
    'Add any extra information you need to keep about contacts.',
  添加字段: 'Add field',
  编辑字段: 'Edit field',
  字段名称: 'Field name',
  内部标识: 'Internal key',
  '用于系统识别这个字段。请以小写字母开头，只使用小写字母、数字和下划线，创建后不能修改。':
    'The system uses this key to identify the field. Start with a lowercase letter and use only lowercase letters, numbers, and underscores. It cannot be changed after creation.',
  '内部标识创建后不能修改。':
    'The internal key cannot be changed after creation.',
  填写方式: 'Input type',
  '填写方式创建后不能修改。':
    'The input type cannot be changed after creation.',
  字段说明: 'Description',
  可用于联系人筛选: 'Use in contact filters',
  'AI 使用': 'AI access',
  'AI 可查看': 'AI can view',
  'AI 可填写': 'AI can fill in',
  '开启后，接待 AI 会参考已填写的内容，避免重复询问。':
    'When enabled, the reception AI can use existing information and avoid asking for it again.',
  '开启后，AI 会从会话中提取信息并填写。只会填写空白内容或更新之前由 AI 填写的内容，不会覆盖人工填写或其他方式写入的内容。':
    'When enabled, AI extracts information from conversations and fills in this field. It only fills blank values or updates values previously filled in by AI. It never overwrites values entered manually or by other sources.',
  已填写联系人: 'Contacts with a value',
  选项管理: 'Options',
  选项标识: 'Option key',
  '选项标识用于区分每个选项，显示名称是页面上看到的文字。已有联系人使用后，选项标识不能修改或删除。':
    'The option key distinguishes each option. The display name is the text shown on the page. An option key cannot be changed or deleted after a contact uses it.',
  添加一项: 'Add option',
  暂无自定义字段: 'No custom fields',
  还没有自定义字段: 'No custom fields yet',
  自定义字段回收站: 'Custom field recycle bin',
  '查看和恢复已删除的自定义字段。': 'View and restore deleted custom fields.',
  '删除这个字段？': 'Delete this field?',
  '删除后会移到回收站。已有联系人数据会保留，恢复字段后可继续使用。':
    'The field will move to the recycle bin. Existing contact data will be kept and can be used again after the field is restored.',
  '恢复这个字段？': 'Restore this field?',
  '恢复后会重新出现在自定义字段列表中，已有联系人数据可以继续使用。':
    'The field will return to the custom field list, and its existing contact data can be used again.',
  回收站中没有字段: 'No deleted fields',

  // 标签
  '分别管理用于会话和联系人的标签。':
    'Manage conversation and contact tags separately.',
  添加标签组: 'Add tag group',
  标签组名称: 'Tag group name',
  标签组: 'Tag group',
  重命名: 'Rename',
  重命名标签组: 'Rename tag group',
  标签用于: 'Used for',
  什么时候使用: 'When to use it',
  '写清楚哪些会话应该使用这个标签，例如：客户明确提出退款、退货或退费。AI 会根据这段说明自动添加。':
    'Explain which conversations should use this tag, for example, when a customer clearly asks for a refund, return, or reimbursement. AI will use this description to add the tag automatically.',
  保留的标签: 'Tag to keep',
  要合并的标签: 'Tag to merge',
  '合并后，“要合并的标签”会移到回收站，之前使用它的联系人或会话会改用“保留的标签”。即使之后恢复，原来的关联也不会自动移回。':
    'The tag being merged will move to the recycle bin. Contacts or conversations using it will switch to the tag you keep. Restoring the original tag later will not move those associations back.',
  '用于 {count} 个会话': 'Used in {count} conversations',
  '用于 {count} 个联系人': 'Used by {count} contacts',
  至少需要两个标签才能合并: 'At least two tags are needed to merge',
  当前没有可以合并的标签: 'No tags can be merged right now',
  '标签组中还有标签，请先移动或删除组内标签。':
    'This group still has tags. Move or delete them first.',
  '选择这些标签用于会话还是联系人，创建后不能修改。':
    'Choose whether these tags are for conversations or contacts. This cannot be changed after creation.',
  已标记: 'Tagged items',
  创建方式: 'Created by',
  '删除这个标签？': 'Delete this tag?',
  '删除后会移到回收站。恢复后，之前使用这个标签的联系人或会话会重新显示该标签。':
    'The tag will move to the recycle bin. After it is restored, it will reappear on the contacts or conversations that previously used it.',
  '查看并恢复已删除的标签。': 'View and restore deleted tags.',
  '恢复这个标签？': 'Restore this tag?',
  '恢复后会重新出现在原来的标签组中。':
    'The tag will return to its original group.',
  '删除这个标签组？': 'Delete this tag group?',
  '删除后，这个空标签组不会出现在回收站中。':
    'This empty tag group will be deleted permanently and will not appear in the recycle bin.',
  删除标签组: 'Delete tag group',
  '这个标签已锁定，不能删除。': 'This tag is locked and cannot be deleted.',
  还没有标签组: 'No tag groups yet',
  这个标签组还没有标签: 'No tags in this group yet',
  回收站中没有标签: 'No deleted tags',
  编辑标签: 'Edit tag',
  暂无标签: 'No tags yet',
  合并标签: 'Merge tags',
  标签回收站: 'Tag recycle bin',
  添加标签: 'Add tag',
  暂无可用标签: 'No available tags',
  标签筛选: 'Tag filter',
  匹配任一: 'Match any',
  匹配全部: 'Match all',
  只看无标签的联系人: 'Only untagged contacts',
  无标签: 'Untagged',
  包含: 'Include',
  排除: 'Exclude',
  包含此标签: 'Include this tag',
  排除此标签: 'Exclude this tag',

  // 应用设置 - 常规设置
  配置系统的基本信息和设置: 'Configure basic system information and settings',
  系统名称: 'System Name',
  系统Logo: 'System Logo',
  允许自主注册: 'Allow self-registration',
  '开启后，访客可以在登录页创建账号。':
    'Visitors can create an account from the login page when enabled.',

  // 客服
  管理客服账号: 'Manage teammates',
  新增客服: 'Add teammate',
  直接创建客服: 'Create teammate',
  邀请客服: 'Invite teammate',
  '通过邮箱邀请新成员，对方将收到邮件自助设置密码后加入系统':
    'Invite a teammate by email; they can set a password and join the system',
  发送邀请: 'Send invitation',
  待接受邀请: 'Pending invitations',
  已发出但对方尚未接受的邀请: 'Invitations that have not been accepted',
  邀请人: 'Invited by',
  过期时间: 'Expires at',
  重发: 'Resend',
  撤销: 'Revoke',
  '确认撤销邀请？': 'Revoke this invitation?',
  '撤销后该邀请链接将立即失效。':
    'The invitation link will stop working immediately.',
  '撤销中...': 'Revoking...',
  确认撤销: 'Revoke',
  接受邀请: 'Accept invitation',
  接受邀请并加入: 'Accept and join',
  邀请链接无效或已过期: 'The invitation link is invalid or expired',
  '设置你的密码即可加入应用「{app}」': 'Set your password to join “{app}”',
  '该邀请链接无效或已过期，请联系邀请你的成员重新发送。':
    'The invitation link is invalid or expired. Ask the inviter to send a new one.',
  前往登录: 'Go to sign in',
  权限: 'Permissions',
  配置权限: 'Configure permissions',
  '按业务模块勾选该客服可执行的操作。':
    'Select the operations this teammate can perform by module.',
  '已选 {count} 项': '{count} selected',
  未分配: 'Not assigned',
  全选: 'Select all',
  清空所选: 'Clear selected',
  权限数: 'Permissions',
  编辑客服信息与权限: 'Edit teammate information and permissions',
  默认语言: 'Default language',
  客服名称: 'Teammate name',
  对外昵称: 'Public display name',
  头像: 'Avatar',
  搜索名称或邮箱: 'Search name or email',
  邮箱: 'Email',
  登录密码: 'Password',
  确认密码: 'Confirm password',
  创建客服账号并分配权限: 'Create a teammate account and assign permissions',
  创建客服账号: 'Create a teammate account',
  编辑客服: 'Edit teammate',
  编辑客服信息: 'Edit teammate information',
  移除: 'Remove',
  '移除中...': 'Removing...',
  确认移除: 'Confirm remove',
  '确认移除客服？': 'Remove this teammate?',
  '移除后该客服将不能进入系统后台。':
    'This teammate will no longer be able to access the admin system.',
  暂无客服: 'No teammates',
  在线状态: 'Online status',
  最后活跃时间: 'Last active',

  // AI 设置
  AI: 'AI',
  'AI 设置': 'AI Settings',
  大模型供应商: 'LLM Providers',
  '管理当前应用下的大模型供应商凭据与可用模型。':
    'Manage LLM provider credentials and available models for this app.',
  默认模型: 'Default model',
  大语言模型: 'LLM Model',
  默认大语言模型: 'Default LLM Model',
  '为当前应用配置默认模型。': 'Configure default models for this app.',
  '为当前应用配置默认模型、AI 最大并发配置和过载提示文案。':
    'Configure the default model, AI max concurrency, and overload message for this app.',
  '用于生成对话回复。': 'Used to generate conversation replies.',
  'AI 最大并发配置': 'AI Max Concurrency',
  'AI 过载提示文案': 'AI Overload Message',
  '控制当前应用同一时间最多处理多少 AI 请求。':
    'Control how many AI requests this app can process at the same time.',
  '用于当前应用 AI 运行时过载保护时向用户展示的提示文案。':
    'Message shown to users when this app hits AI runtime overload protection.',
  '例如：当前 AI 请求较多，请稍后再试。':
    'For example: AI is busy right now, please try again later.',
  请选择默认大语言模型: 'Select default LLM model',
  当前默认大语言模型已失效请重新选择:
    'The current default LLM model is no longer available. Please pick another one.',
  '当前应用没有可用的启用模型，请先在大模型供应商中启用至少一个大语言模型':
    'No active models in this app. Enable at least one LLM model in LLM Providers first.',
  '清空后已保存的凭据将被移除，供应商也会被自动停用。':
    'Saved credentials will be removed and the provider will be automatically disabled.',
  '该模型已被知识库使用，不能停用或删除':
    'This model is used by a knowledge base and cannot be disabled or deleted.',
  '该供应商已有模型被知识库使用，不能停用或删除':
    'This provider has models used by knowledge bases and cannot be disabled or deleted.',
  确认清空: 'Clear credentials',
  '清空中...': 'Clearing...',
  更新: 'Update',
  '已配置，输入新值后点击更新':
    'Configured. Enter a new value and click Update.',
  '这是应用内部的本地保护阈值，不能超过 AI 最大并发配置。':
    'This is a local safety limit inside the app and cannot exceed the AI max concurrency setting.',
  '超过上限后，系统会结束本轮任务。':
    'Once the limit is reached, the system will end the current task.',
  简介: 'Description',
  暂无简介: 'No description',
  暂未更新: 'Not updated yet',
  行为说明: 'Behavior',
  工作方式: 'How it works',
  '设置它的回复规则，以及哪些情况需要转人工。':
    'Set its response rules and define when it should hand off to a human.',
  系统指令: 'System instructions',
  回复规则: 'Response rules',
  人工介入指引: 'Handoff instructions',
  转人工规则: 'Handoff rules',
  'ReAct 循环': 'ReAct loop',
  处理轮次: 'Processing rounds',
  最大循环次数: 'Max iterations',
  最大处理轮次: 'Max processing rounds',
  模型: 'Model',
  供应商: 'Provider',
  模型名称: 'Model name',
  '模型 ID': 'Model ID',
  媒体输入能力: 'Media input capabilities',
  是否支持图片输入: 'Supports image input',
  是否支持视频输入: 'Supports video input',
  继承应用默认模型: 'Use the app default model',
  当前应用默认模型: 'Current app default model',
  当前模型: 'Current model',
  关键配置: 'Key settings',
  最近更新: 'Updated',
  继承应用默认: 'Uses app default',
  独立模型: 'Custom model',
  '应用默认模型当前不可用，请先在默认模型设置中修正或改为单独指定模型。':
    'The app default model is unavailable. Fix it in default model settings or choose a model explicitly.',
  '模型当前不可用，后续运行前需要修正。':
    'This model is currently unavailable and must be fixed before AI reception can run.',
  已配置人工介入: 'Handoff configured',
  未配置人工介入: 'No handoff guidance',
  已配置转人工规则: 'Handoff rules configured',
  未配置转人工规则: 'No handoff rules',
  已设置转人工: 'Handoff set',
  未设置转人工: 'No handoff set',
  未设置或已失效: 'Not set or invalid',
  单独指定模型: 'Choose a model for this app',
  指定模型: 'Select model',

  // Reception plans
  接待方案: 'Reception plans',
  接待方案表单: 'Reception plan form',
  '调整接待方案的流程策略、营业时间与自动化配置':
    "Adjust the plan's flow strategy, business hours, and automation",
  接待方案管理: 'Reception plan management',
  新建接待方案: 'New reception plan',
  '先添加方案，再继续完善人设、服务场景等详细配置。':
    'Add the plan first, then continue refining persona and service scenarios.',
  '例如：售前接待方案': 'For example: Pre-sales plan',
  '一句话说明该方案的服务范围与边界。':
    'Describe the plan’s scope and boundary in one line.',
  '从这里挑选方案，右侧查看与编辑详情。':
    'Pick a plan here; view and edit details on the right.',
  '从左侧选择方案后，可以在这里编辑接待配置、服务场景和查看版本历史。':
    'After selecting a plan on the left, you can edit reception settings, service scenarios and view version history here.',
  '从左侧选择已删除方案后可以查看详情并恢复。':
    'Pick a deleted plan on the left to view its details and restore it.',
  未发布: 'Unpublished',
  暂无服务场景: 'No service scenarios',
  暂无可用知识库: 'No knowledge bases available',
  暂无可用集成工具: 'No integration tools available',
  '确认移除该服务场景？': 'Remove this service scenario?',
  '确认后会从当前表单移除，点击保存后生效；进行中会话沿用其锁定的配置不受影响。':
    'Confirming removes it from the current form. Click Save to apply it; active conversations keep using their locked configuration.',
  场景名称不能为空: 'Scenario name is required',
  场景指令不能为空: 'Scenario instructions are required',
  '修改后会更新当前表单，点击保存后生效。':
    'Changes update the current form. Click Save to apply them.',
  '取消关联后该知识库将不再被此方案检索，点击保存后生效。':
    'After unlinking, this knowledge base will no longer be searched by the plan. Click Save to apply it.',
  暂无版本: 'No versions',
  从模板新建: 'Create from template',
  查看详情: 'View details',
  详情: 'Details',
  版本号: 'Version',
  当前查看: 'Currently viewing',
  返回管理页: 'Back to manager',
  '版本只读，发布时已固化配置。':
    'Versions are read-only; configuration is frozen at publish time.',
  创建接待方案: 'Create reception plan',
  添加接待方案: 'Add reception plan',
  '管理应用的接待方案配置与已发布版本。':
    'Manage reception plan configuration and published versions for this app.',
  创建方案: 'Create plan',
  方案名称: 'Plan name',
  方案简介: 'Plan description',
  基础信息: 'Basics',
  流程策略: 'Flow strategy',
  服务场景: 'Service scenarios',
  关联知识库: 'Linked knowledge bases',
  '选择此方案中 AI 接待可以检索的知识库。':
    'Select the knowledge bases available to the reception AI for this plan.',
  '选择要关联到此方案的知识库，AI 接待时可以检索这些知识库。':
    'Select the knowledge bases to link to this plan so the reception AI can search them.',
  '授权此方案中 AI 接待可调用的集成，可在每个集成下进一步收窄工具白名单（不勾选则放行该集成全部已启用工具）。':
    'Authorize integrations that the reception AI may use in this plan. You can narrow the tool allowlist for each integration; selecting none allows all enabled tools in that integration.',
  '取消授权后该集成将不再被此方案的 AI 接待调用，点击保存后生效。':
    'After authorization is removed, the reception AI for this plan can no longer use the integration. The change takes effect when you save.',
  '{count} 个': '{count}',
  '配置方案名称、简介与 AI 人设，决定访客沟通时看到的接待形象。':
    'Configure the plan name, description, and AI persona that shape how visitors see your reception.',
  '配置 AI 与人工客服之间的接待方式、流转规则与各类提示文案。':
    'Configure how conversations flow between AI and human agents, plus the related notice messages.',
  '配置人工服务的时区与每周服务时间，非服务时间自动告知访客。':
    'Configure the timezone and weekly hours for human service; visitors are notified automatically outside these hours.',
  接待设置: 'Reception settings',
  自动回复: 'Auto replies',
  '配置会话进入 AI 或人工接待时自动回复给访客的真实消息。':
    'Configure real replies sent automatically when a conversation enters AI or human reception.',
  'AI 接待欢迎语': 'AI reception greeting',
  '新会话进入 AI 接待或后续由 AI 接管时发送一次，支持 {variable}。':
    'Sent once when a new conversation enters AI reception or is later taken over by AI. Supports {variable}.',
  '您好，我是{{display_name}}，请问有什么可以帮您？':
    'Hi, I am {{display_name}}. How can I help?',
  客服接入欢迎语: 'Teammate joined greeting',
  '会话首次分配给客服时发送一次，支持 {variable}。':
    'Sent once when the conversation is first assigned to a teammate. Supports {variable}.',
  '您好，我是{{teammate_name}}，接下来由我为您服务。':
    'Hi, I am {{teammate_name}}. I will help you from here.',
  客服转接欢迎语: 'Teammate transfer greeting',
  '会话转接给另一位客服时发送一次，支持 {variable}。':
    'Sent once when the conversation is transferred to another teammate. Supports {variable}.',
  '您好，我是{{teammate_name}}，已接手本次会话。':
    'Hi, I am {{teammate_name}}. I have taken over this conversation.',
  语气风格: 'Tone',
  备用模型: 'Backup models',
  添加备用模型: 'Add backup model',
  '优先级 {priority}': 'Priority {priority}',
  '未配置备用模型。': 'No backup models configured.',
  候选模型: 'Candidate models',
  上移: 'Move up',
  下移: 'Move down',
  接待指引: 'Reception instructions',
  '请保持友好、简洁、准确；先理解访客问题，再给出可执行答复。不确定时说明限制并询问关键信息。':
    'Be friendly, concise, and accurate. Understand the visitor first, then give actionable help. If uncertain, state the limit and ask for the key detail.',
  'AI 优先': 'AI first',
  人工优先: 'Teammate first',
  '当前默认模型不可用，请重新选择。':
    'The current default model is unavailable. Choose another one.',
  版本历史: 'Version history',
  历史版本: 'Version history',
  查看历史版本: 'View version history',
  版本: 'Version',
  发布: 'Publish',
  发布状态: 'Publish status',
  发布信息: 'Publish info',
  发布备注: 'Release note',
  发布人: 'Published by',
  发布时间: 'Published at',
  发布新版本: 'Publish new version',
  '发布备注（可选）': 'Release note (optional)',
  '发布 {name} 的当前配置，版本号会自动递增。':
    'Publish the current configuration of {name}. The version number will increment automatically.',
  '发布当前配置，版本号会自动递增。':
    'Publish the current configuration. The version number will increment automatically.',
  '例如：调整接待指引，限 500 字以内':
    'For example: Updated reception instructions. 500 characters max.',
  '发布中...': 'Publishing...',
  确认发布: 'Confirm publish',
  'v{number} 已发布': 'v{number} published',
  暂无接待方案: 'No reception plans yet',
  '确认删除接待方案？': 'Delete this reception plan?',
  接待方案回收站: 'Reception plan recycle bin',
  查看已删除的接待方案并可恢复: 'View deleted reception plans and restore them',
  暂无已删除的接待方案: 'No deleted reception plans',
  删除信息: 'Deletion info',
  '确认恢复接待方案？': 'Restore this reception plan?',
  '恢复后将重新出现在接待方案列表中。':
    'After restore, it will appear in the reception plan list again.',
  '删除后该接待方案会被移到回收站，可随时恢复；如果已有渠道或会话正在使用，系统会先阻止删除。':
    'After deletion, this reception plan moves to the recycle bin and can be restored later. If channels or conversations are using it, the system will prevent deletion first.',
  '展示已发布的全部版本，可查看每个版本的配置。':
    'Show all published versions. You can view each version configuration.',
  '接待方案 {name} 的版本历史': 'Version history for reception plan {name}',
  '查看接待方案 {name} 的版本 {version} 详情':
    'View {version} details for reception plan {name}',
  返回编辑: 'Back to edit',
  返回版本历史: 'Back to version history',
  查看版本: 'View version',
  '版本内容为发布时保存的只读配置。':
    'Version content is the read-only configuration saved at publish time.',
  '例如：保持友好简洁，遇到不确定先承认，再询问关键信息……':
    'For example: Keep replies friendly and concise. When unsure, acknowledge it first, then ask for the key details...',

  '已基于模板预填字段，可根据业务需要调整后保存。':
    'Fields are pre-filled from the template; adjust as needed before saving.',
  关联集成: 'Linked integrations',

  // 知识库
  知识库: 'Knowledge bases',
  知识库列表: 'Knowledge base list',
  '管理应用知识库、文档和检索能力':
    'Manage knowledge bases, documents, and retrieval for this app',
  '管理当前应用可供 AI 接待检索的知识库。':
    'Manage knowledge bases that AI reception can search in this app.',
  '创建后可在知识库中上传文档或录入问答，为 AI 接待提供检索能力。':
    'After creation, upload documents or add Q&A entries to make them searchable by AI reception.',
  创建知识库: 'Create knowledge base',
  编辑知识库: 'Edit knowledge base',
  知识库名称: 'Knowledge base name',
  知识库头像: 'Knowledge base avatar',
  嵌入模型: 'Embedding model',
  重排序模型可选: 'ReRank model (optional)',
  不使用重排序: 'Do not use ReRank',
  确认修改嵌入模型: 'Change Embedding model?',
  修改嵌入模型后知识库里所有文档的标准索引需要重新构建:
    'After changing the Embedding model, the standard index for all documents in this knowledge base must be rebuilt.',
  继续修改: 'Continue',
  '未上传时将使用默认知识库头像。':
    'A default knowledge base avatar will be used when none is uploaded.',
  暂无知识库: 'No knowledge bases yet',
  '创建知识库后，就可以继续导入文档并配置检索能力。':
    'Create a knowledge base first, then import documents and configure retrieval.',
  知识库回收站: 'Knowledge base recycle bin',
  查看已删除的知识库并可恢复: 'View deleted knowledge bases and restore them',
  暂无已删除的知识库: 'No deleted knowledge bases',
  '确认删除知识库？': 'Delete this knowledge base?',
  '删除后该知识库将从当前应用移除。':
    'After deletion, this knowledge base will be removed from the current app.',
  '删除后该知识库会被移到回收站，可随时恢复。':
    'After deletion, this knowledge base moves to the recycle bin and can be restored later.',
  '确认恢复知识库？': 'Restore this knowledge base?',
  '恢复后将重新出现在知识库列表中。':
    'After restore, it will appear in the knowledge base list again.',
  '为当前应用添加一个知识库。': 'Add a knowledge base for the current app.',
  '调整知识库头像、名称和描述。':
    'Adjust the knowledge base avatar, name, and description.',
  '支持上传文档和手动添加自定义内容，解析后用于知识库检索。':
    'Upload documents or manually add custom content, then use it for knowledge base retrieval.',
  '管理当前知识库下的文档；左侧切换分组以查看不同分组的文档。':
    'Manage documents in this knowledge base. Use the groups on the left to switch scopes.',
  '手动录入问答对，适合 FAQ 等精匹配场景。':
    'Manually enter Q&A pairs, ideal for FAQ-style precise matching scenarios.',
  '管理当前知识库下的问答；左侧切换分组以查看不同分组的问答。':
    'Manage Q&A entries in this knowledge base. Use the groups on the left to switch scopes.',
  添加问答: 'Add Q&A',
  编辑问答: 'Edit Q&A',
  问答: 'Q&A',
  问题: 'Question',
  答案: 'Answer',
  暂无问答: 'No Q&A entries yet',
  '确认删除该问答？': 'Delete this Q&A entry?',
  '删除后将移除该问答。': 'This Q&A entry will be removed.',
  '加载答案失败，请关闭弹窗后重试。':
    'Failed to load the answer. Close the dialog and try again.',
  '已编辑的内容尚未保存，确定要关闭吗？关闭后修改将丢失。':
    'You have unsaved changes. Are you sure you want to close? Changes will be lost.',
  未知分组: 'Unknown Group',
  新建知识库: 'New knowledge base',
  普通知识库: 'Standard knowledge base',
  问答知识库: 'Q&A knowledge base',
  公众号知识库: 'Official account knowledge base',
  类别: 'Type',
  描述: 'Description',
  '创建后可在知识库中上传文档或录入问答，为智能体提供检索能力。':
    'After creation, upload documents or add Q&A entries to provide retrieval context for agents.',
  '调整知识库基础信息。': 'Edit knowledge base basics.',
  '删除后将永久移除此知识库及其下所有文档和索引数据，不可恢复。':
    'This will permanently delete this knowledge base, its documents, and its index data. This action cannot be undone.',
  检索配置: 'Retrieval settings',
  知识库检索配置: 'Knowledge base retrieval settings',
  '当前应用内所有知识库共用这套检索配置。':
    'All knowledge bases in this app share these retrieval settings.',
  标准索引: 'Standard index',
  '为文档建立基础索引，用于日常知识库问答。':
    'Builds the baseline index used for everyday knowledge base answers.',
  请选择嵌入模型: 'Select an embedding model',
  向量维度: 'Vector dimension',
  分段方式: 'Chunking method',
  普通分段: 'Standard chunking',
  语义分段: 'Semantic chunking',
  '单段最大 token': 'Max tokens per chunk',
  '相邻段重叠 token': 'Overlap tokens',
  深度索引: 'Deep index',
  '为长文档建立更深入的层级索引，提升复杂问题的命中效果。':
    'Builds a deeper layered index for long documents and complex questions.',
  摘要模型: 'Summary model',
  请选择摘要模型: 'Select a summary model',
  '重排序模型（可选）': 'Rerank model (optional)',
  确认更新检索配置: 'Update retrieval settings?',
  '保存后会清理当前应用已有知识库索引，并按新的配置重新构建。':
    'Saving will clear existing knowledge base indexes in this app and rebuild them with the new settings.',
  继续保存: 'Continue saving',
  请从左侧选择一个知识库: 'Select a knowledge base on the left',
  全部文档: 'All documents',
  全部问答: 'All Q&A',
  文件名: 'File name',
  文件类型: 'File type',
  手动内容: 'Manual content',
  纯文本: 'Plain text',
  大小: 'Size',
  暂无文档: 'No documents yet',
  '搜索文件名...': 'Search file names...',
  上传文档: 'Upload documents',
  手动添加文档: 'Add manual document',
  编辑文档: 'Edit document',
  标题: 'Title',
  正文: 'Body',
  '加载文档内容失败，请关闭弹窗后重试。':
    'Failed to load document content. Close the dialog and try again.',
  检索测试: 'Retrieval test',
  即将上线: 'Coming soon',
  重新索引: 'Reindex',
  '确认删除该文档？': 'Delete this document?',
  '删除后将移除该文档以及它后续生成的索引数据。':
    'This will remove the document and the index data generated from it.',
  分组: 'Group',
  新建分组: 'New group',
  编辑分组: 'Edit group',
  分组名称: 'Group name',
  上级分组: 'Parent group',
  '无（顶级分组）': 'None (top-level group)',
  '分组最多支持两级，即分组下可再创建子分组。':
    'Groups support up to two levels, so each group can contain one level of child groups.',
  移动分组: 'Move group',
  目标分组: 'Target group',
  '确认删除分组？': 'Delete this group?',
  '删除前请先清空子分组。分组下的文档不会被删除。':
    'Remove child groups before deleting. Documents in this group will not be deleted.',
  '该分组下还有子分组，需先清空子分组才能移动到其它分组下。':
    'This group still has child groups. Remove them before moving this group under another group.',
  标准问题: 'Primary question',
  相似问法: 'Similar questions',
  暂无相似问法: 'No similar questions yet',
  '删除后将移除该问答、相似问法和全部答案。':
    'This will remove this Q&A entry, its similar questions, and all answers.',
  完成: 'Done',
  开始上传: 'Start upload',
  '上传失败，请稍后重试。': 'Upload failed. Please try again later.',
  '网络异常，请检查网络后重试。':
    'Network error. Check your connection and try again.',
  '支持 {exts}': 'Supports {exts}',
  '支持 {exts}，单个文件不超过 {size}，单次最多上传 {count} 个。':
    'Supports {exts}. Each file must be under {size}. Upload up to {count} files at a time.',
  '支持 .md / .markdown / .txt / .pdf / .docx / .html / .htm，单个文件不超过 20MB，单次最多上传 20 个。':
    'Supports .md / .markdown / .txt / .pdf / .docx / .html / .htm. Each file must be under 20MB. Upload up to 20 files at a time.',
  '点击选择文件，或将文件拖拽到此处':
    'Click to choose files, or drag files here',
  '已选择 {count} 个文件': '{count} file(s) selected',
  重试失败的文件: 'Retry failed files',
  '已忽略不支持的文件：{names}（仅支持 {exts}）。':
    'Unsupported files ignored: {names}. Supported formats: {exts}.',
  '已忽略超过 {size} 的文件：{names}。': 'Files over {size} ignored: {names}.',
  '单次最多上传 {count} 个文件，多余的已忽略。':
    'Upload up to {count} files at a time. Extra files were ignored.',
  管理应用下的知识库和文档分组:
    'Manage knowledge bases and document groups in this app',

  网站渠道: 'Web channels',
  '管理当前应用的网站 AI 入口。':
    'Manage the web AI entry points for the current app.',
  网站: 'Website',
  Telegram: 'Telegram',
  创建渠道: 'Create channel',
  编辑渠道: 'Edit channel',
  返回渠道列表: 'Back to channels',
  返回渠道详情: 'Back to channel details',
  '创建一个新的网站渠道。': 'Create a new web channel.',
  '管理渠道基础信息、机器人接入与接待配置':
    'Manage channel basics, bot access, and reception settings',
  配置网站渠道的接入方式与访客界面:
    "Configure the web channel's access and visitor interface",
  '调整渠道入口、品牌配置和人工兜底规则。':
    'Adjust the channel entry, branding, and human handoff rules.',
  渠道名称: 'Channel name',
  接待方式: 'Reception mode',
  客服身份展示: 'Service identity display',
  客服头像: 'Service avatar',
  客服昵称: 'Service nickname',
  '无人接待超时后 AI 接待': 'AI handles unassigned conversations after timeout',
  '无人接待超时时间（秒）': 'Unassigned timeout (seconds)',
  '客服无响应后 AI 接管': 'AI takes over when agent is unresponsive',
  '客服无响应时间（秒）': 'Agent no-response timeout (seconds)',
  空闲自动结束会话: 'Auto-close idle conversations',
  '空闲多久后自动结束会话（分钟）': 'Idle minutes before auto-closing',
  '重点客户 AI 谨慎回复': 'Careful AI replies for important customers',
  '重点客户 AI 风险转人工提示': 'AI handoff hint for important customer risks',
  重点客户人工在线优先接待: 'Prefer online human for important customers',
  启用人工服务时间: 'Enable human service hours',
  '当前为非人工服务时间，我会先为您处理；如需客服，将在人工服务时间内继续跟进。':
    'It is currently outside human service hours. I will help first; if an agent is needed, they will follow up during service hours.',
  启用营业时间: 'Enable business hours',
  '开启后，仅在设定时段内支持客服接待会话；时段外由 AI 全程处理。':
    'When enabled, agent service is only available during set hours. AI handles everything outside those hours.',
  时区: 'Timezone',
  选择时区: 'Select timezone',
  每周可用时段: 'Weekly schedule',
  '暂不支持跨夜时段，结束时间必须晚于开始时间。':
    'Overnight schedules are not supported yet. The end time must be later than the start time.',
  休息: 'Off',
  时段外提示: 'Off-hours notice',
  '在营业时间外展示给访客，说明 AI 正在服务并告知客服何时可跟进。':
    'Shown to visitors outside business hours to explain AI is serving and when an agent can follow up.',
  '示例：人工服务时间为工作日 09:00–18:00，当前由 AI 助手为您服务。如需客服，将在工作时间内回复。':
    'Example: Human service hours are Mon-Fri 09:00-18:00. AI is helping you now. If an agent is needed, we will reply during business hours.',
  营业时间: 'Business hours',
  周一: 'Mon',
  周二: 'Tue',
  周三: 'Wed',
  周四: 'Thu',
  周五: 'Fri',
  周六: 'Sat',
  周日: 'Sun',
  '中国标准时间 (UTC+8)': 'China Standard Time (UTC+8)',
  '日本标准时间 (UTC+9)': 'Japan Standard Time (UTC+9)',
  '新加坡时间 (UTC+8)': 'Singapore Time (UTC+8)',
  '韩国标准时间 (UTC+9)': 'Korea Standard Time (UTC+9)',
  '香港时间 (UTC+8)': 'Hong Kong Time (UTC+8)',
  '印度标准时间 (UTC+5:30)': 'India Standard Time (UTC+5:30)',
  '英国时间 (UTC+0/+1)': 'United Kingdom Time (UTC+0/+1)',
  '中欧时间 (UTC+1/+2)': 'Central European Time (UTC+1/+2)',
  '德国时间 (UTC+1/+2)': 'Germany Time (UTC+1/+2)',
  '美国东部时间 (UTC-5/-4)': 'US Eastern Time (UTC-5/-4)',
  '美国中部时间 (UTC-6/-5)': 'US Central Time (UTC-6/-5)',
  '美国太平洋时间 (UTC-8/-7)': 'US Pacific Time (UTC-8/-7)',
  '协调世界时 (UTC+0)': 'Coordinated Universal Time (UTC+0)',
  自定义传参: 'Custom parameters',
  登录用户身份签名: 'Signed user identity',
  '配置签名密钥后，你的业务后端可签发 JWT，让已登录用户以可信身份接入客服，防止他人伪造身份。下方的明文参数映射只适合来源、活动等非敏感信息。':
    'After configuring a signing secret, your backend can issue JWTs so logged-in users enter support with a trusted identity. The plain parameter mappings below are only for non-sensitive context such as source or campaign.',
  未生成密钥: 'No secret generated',
  生成密钥: 'Generate secret',
  重置密钥: 'Regenerate secret',
  '确认重置签名密钥？': 'Regenerate signing secret?',
  '重置后现有 token 将立即失效，使用当前密钥签发的访客身份无法再通过校验。系统会立即生成新密钥。':
    'Current tokens become invalid immediately. Visitor identities signed with the current secret will no longer verify. The system will generate a new secret immediately.',
  确认重置: 'Regenerate',
  '把独立页 URL 参数或小部件配置参数按映射写入联系人字段、自定义属性或标签。属性必须开启 API 写入，适合记录来源、活动和入口等公开上下文。':
    'Map standalone URL parameters or widget configuration parameters into contact fields, custom attributes, or tags. Attributes must allow API writes. Use this for source, campaign, entry, and other public context.',
  '把聊天链接 URL 参数或网站嵌入配置参数按映射写入联系人字段、自定义属性或标签。属性必须开启 API 写入，适合记录来源、活动和入口等公开上下文。':
    'Map chat link URL parameters or website embed configuration parameters into contact fields, custom attributes, or tags. Attributes must allow API writes. Use this for source, campaign, entry, and other public context.',
  新增映射: 'Add mapping',
  '配置一个外部参数如何写入联系人资料，保存页面后生效。':
    'Configure how an external parameter is written to contact data. It takes effect after saving the page.',
  参数名: 'Parameter name',
  写入目标: 'Write target',
  写入模式: 'Write mode',
  目标键: 'Target key',
  删除映射: 'Delete mapping',
  暂无自定义传参: 'No custom parameters',
  '属性 Key': 'Attribute key',
  标签模板: 'Tag template',
  '选择已开启 API 写入的自定义属性。属性类型为单选时，参数值需匹配选项 code。':
    'Select a custom attribute with API writes enabled. For single-select attributes, the parameter value must match an option code.',
  '模板支持 {value} 占位（仅允许字母/数字/下划线/连字符 1~40 位）；无占位时使用模板字面量。':
    'The template supports a {value} placeholder with 1-40 letters, numbers, underscores, or hyphens. Without a placeholder, the literal template is used.',
  接入指导: 'Integration guide',
  独立页接入指导: 'Standalone page integration guide',
  '独立页可直接作为链接、按钮跳转地址或二维码落地页使用。':
    'The standalone page can be used directly as a link, button target, or QR code landing page.',
  基本用法: 'Basic usage',
  带参数打开: 'Open with parameters',
  'URL 参数会按“自定义传参”里的映射规则进入访客资料。':
    'URL parameters are written into visitor data according to the mapping rules in Custom parameters.',
  小部件接入指导: 'Widget integration guide',
  网站嵌入接入指导: 'Website embed integration guide',
  聊天链接接入指导: 'Chat link integration guide',
  '默认复制安装代码即可；需要传入访客或业务上下文时，在安装代码后增加配置标签。':
    'Copying the install snippet is usually enough. Add a configuration tag after it when passing visitor or business context.',
  '将安装代码添加到你的网站中；PC 端显示浮窗，移动端可自动铺满屏幕。':
    'Add the install snippet to your website. Desktop visitors see a floating window, and mobile visitors can get a full-screen chat.',
  '聊天链接可直接作为链接、按钮跳转地址或二维码落地页使用。':
    'Use the chat link directly as a link, button target, or QR code landing page.',
  '选择“自定义入口”后，默认气泡不会显示；你可以用 HelmDesk.show() 或 data-helmdesk-open 打开聊天。':
    'After choosing Custom entry, the default bubble is hidden. Open chat with HelmDesk.show() or data-helmdesk-open.',
  传入额外参数: 'Pass extra parameters',
  嵌入域名白名单: 'Embed domain allowlist',
  '每行一个允许加载网站嵌入代码的域名；留空表示不限制。':
    'Enter one domain per line that may load the website embed snippet. Leave empty to allow any domain.',
  显示未读角标: 'Show unread badge',
  '入口右上角小红点，提示访客有新消息。':
    'A small red dot on the entry indicates new messages.',
  显示提示弹窗: 'Show preview popup',
  '在入口附近弹出新消息预览，点击展开聊天。打扰较强，默认关闭。':
    'Show a new message preview near the entry. Clicking opens the chat. More intrusive, so it is off by default.',
  请输入渠道名称: 'Enter channel name',
  状态: 'Status',
  请选择状态: 'Select a status',
  标题栏图标: 'Title bar icon',
  清空: 'Clear',
  入口: 'Entry',
  操作: 'Actions',
  更多操作: 'More actions',
  取消: 'Cancel',
  '创建中...': 'Creating...',
  状态与操作: 'Status & actions',
  配置: 'Configure',
  已删除的渠道: 'Deleted channels',
  渠道回收站: 'Channel recycle bin',
  查看已删除的渠道并可恢复: 'View deleted channels and restore them',
  '确认恢复渠道？': 'Restore channel?',
  '恢复后将重新出现在网站渠道列表中。':
    'After restore, it will appear in the web channel list again.',
  暂无已删除的渠道: 'No deleted channels',
  接待方案版本: 'Reception plan version',
  未部署接待方案: 'No reception plan deployed',
  当前部署的接待方案版本已失效:
    'The deployed reception plan version is no longer usable',
  '不影响保存其他配置，但在切换到可用版本之前，此渠道无法新建 AI 接待会话。':
    "You can still save other settings, but the channel can't open new AI reception sessions until you pick a usable version.",
  接待方案版本当前不可用: 'The reception plan version is not usable',
  当前部署的接待方案版本: 'Currently deployed reception plan version',
  '当前接待方案版本均不可用（已归档或默认接待模型失效），请先调整。':
    'All reception plan versions are unusable (archived or default reception model invalid). Please adjust first.',
  管理接待方案: 'Manage reception plans',
  去创建接待方案: 'Create reception plan',
  启用渠道: 'Activate channel',
  '基础信息、对外展示和转人工统一在这里完成。':
    'Basic info, presentation, and handoff are configured together here.',
  渠道基础: 'Channel basics',
  '名称、图标、接待方案和访客看到的欢迎语。':
    'Name, icon, reception plan, and the greeting visitors see.',
  '设置渠道名称、接待方案版本，以及这个渠道的补充说明。':
    'Set the channel name, reception plan version, and extra instructions for this channel.',
  '例如：官网主站、帮助中心': 'For example: Main site, Help center',
  渠道描述: 'Channel description',
  'AI 回复引用访客消息': 'AI replies quote visitor messages',
  '开启后，AI 回复会引用触发本轮回复的访客消息。':
    'When enabled, AI replies quote the visitor message that triggered the turn.',
  转人工成功提示语: 'Human handoff accepted notice',
  无法转人工提示语: 'Unavailable handoff notice',
  'AI 不可用兜底提示语': 'AI unavailable fallback notice',
  '已为您转接人工客服，请稍等。':
    'I have connected you with a human agent. Please wait a moment.',
  '当前暂无法转接人工，我会继续为您处理。':
    'I cannot connect you with a human agent right now. I will keep helping you.',
  '很抱歉，AI 助手暂时无法为您服务，正在为您转接人工客服，请稍候。':
    'We apologize, but the AI assistant is temporarily unavailable. We are connecting you with a human teammate. Please wait a moment.',
  欢迎语: 'Greeting message',
  渠道说明: 'Channel instructions',
  '描述这个入口面向的访客、业务场景和回复偏好。':
    'Describe the audience, scenarios, and reply preferences for this entry.',
  接入配置: 'Entry settings',
  接入方式: 'Entry methods',
  '控制 widget 与 standalone 出口，以及入口上下文配置。':
    'Control the widget and standalone entry points plus context settings.',
  '控制网站嵌入、聊天链接和访客上下文参数。':
    'Control website embed, chat links, and visitor context parameters.',
  站点名称: 'Site name',
  '例如：HelmDesk 官网': 'For example: HelmDesk website',
  未指定: 'Not set',
  页面地址: 'Page URL',
  网站嵌入代码: 'Website embed snippet',
  聊天链接: 'Chat link',
  聊天链接二维码: 'Chat link QR code',
  网站链接: 'Web link',
  渠道链接: 'Channel link',
  二维码: 'QR code',
  生成: 'Generate',
  '生成中...': 'Generating...',
  复制安装代码: 'Copy install snippet',
  请选择: 'Please select',
  '支持的 Query 参数': 'Supported query parameters',
  '系统固定白名单，仅用于说明当前可传入哪些入口参数。':
    'This is a fixed system whitelist and is shown only to document supported entry parameters.',
  悬浮组件: 'Website embed',
  小部件: 'Website embed',
  Widget: 'Website embed',
  独立页: 'Chat link',
  独立页面: 'Chat link',
  Standalone: 'Chat link',
  网站嵌入: 'Website embed',
  均未启用: 'No entry enabled',
  'Standalone 地址': 'Standalone page URL',
  查看: 'View',
  启用: 'Enable',
  停用: 'Disable',
  暂无网站渠道: 'No web channels yet',
  最近使用网站: 'Recently used site',
  尚未使用: 'Not in use yet',
  最近一次加载: 'Last loaded on',
  首次嵌入: 'First embed',
  '把 AI 放到你的官网：访客可以通过悬浮组件或独立页面和它聊天。':
    'Put AI on your website so visitors can chat through a widget or a standalone page.',
  '把 AI 放到你的官网：访客可通过网站嵌入代码或聊天链接和它聊天。':
    'Put AI on your website so visitors can chat through the website embed snippet or a chat link.',
  '确认删除渠道？': 'Delete this channel?',
  '删除后该渠道会被移到已删除列表，可随时恢复；对应的访客入口会暂时不可用。':
    'After deletion, this channel moves to the deleted list and can be restored later. Its visitor entry points will be unavailable for now.',
  暂无说明: 'No details',
  '查看渠道接入方式、品牌配置和人工兜底信息。':
    'Review the entry method, branding, and human handoff settings for this channel.',
  基本概览: 'Overview',
  '当前渠道的状态、接待方案部署和入口开关。':
    'See the current status, reception plan deployment, and entry toggles for this channel.',
  未设置: 'Not configured',
  '用于快速确认当前配置是否已同步。':
    'Useful for quickly checking whether the latest configuration has been synced.',
  更新时间: 'Updated at',
  已复制: 'Copied',
  页面二维码: 'Page QR code',
  页面地址二维码: 'Page URL QR code',
  安装代码: 'Install snippet',
  保存接入方式: 'Save entry methods',
  保存入口与设备: 'Save entry & device',
  页面样式: 'Page style',
  '小部件的页面样式配置将在后续版本提供。':
    'Widget page style settings will be available in a future version.',
  页面标题: 'Page title',
  页面副标题: 'Page subtitle',
  页面图标: 'Page icon',
  顶部导航: 'Top navigation',
  通用: 'General',
  猜你想问: 'Suggested questions',
  '一个可以直接分享给访客的对话页面。':
    'A conversation page that can be shared directly with visitors.',
  '嵌入到官网任意页面的聊天入口。':
    'A chat entry that can be embedded on any website page.',
  复制代码: 'Copy snippet',
  '以下为系统级白名单，渠道侧仅只读展示。':
    'Below is the system-level whitelist, shown here in read-only mode.',

  // 渠道详情页
  基本信息: 'Basics',
  接待配置: 'Reception settings',
  '渠道的内部名称和部署的接待方案版本。':
    'The internal channel name and deployed reception plan version.',
  访客界面: 'Visitor interface',
  主题颜色: 'Theme color',
  自定义颜色: 'Custom color',
  首页模式: 'Home screen',
  '开启后访客先看到欢迎屏，再进入聊天':
    'Visitors see a welcome screen first, then enter the chat',
  首页欢迎语: 'Home welcome message',
  实时预览: 'Live preview',
  展开聊天: 'Expand chat',
  收起聊天: 'Collapse chat',
  入口设置: 'Entry',
  入口与设备: 'Entry & device',
  入口模式: 'Entry mode',
  入口收起样式: 'Collapsed entry style',
  入口位置: 'Entry position',
  聊天窗位置: 'Chat window position',
  入口样式: 'Entry style',
  入口图标大小: 'Entry icon size',
  入口底部间距: 'Entry bottom offset',
  默认图标: 'Default icon',
  选中图标: 'Selected icon',
  '不上传则入口使用系统默认图标。':
    'Leave empty to use the system default icon.',
  '展开聊天后入口显示的图标，需与默认图标成对上传。':
    'Shown on the entry while the chat is open; upload it together with the default icon.',
  移动端展开后铺满屏幕: 'Open full screen on mobile',
  '开启后，小部件在手机浏览器中打开聊天时会接管整个屏幕，避免键盘和页面滚动挤压聊天区。':
    'When enabled, the widget takes over the full phone screen when opened, avoiding keyboard and page-scroll pressure on the chat area.',
  '自定义入口会隐藏 HelmDesk 默认气泡，由你网站上的按钮或脚本主动打开聊天窗口。':
    'Custom entry hides the HelmDesk default bubble. Your website button or script opens the chat window instead.',
  '也可以在你的点击事件中调用 HelmDesk.show()；多渠道页面可使用 HelmDesk.channels[code].show()。':
    'You can also call HelmDesk.show() in your click handler. For multi-channel pages, use HelmDesk.channels[code].show().',
  联系客服: 'Contact support',
  客户自有按钮: 'Custom site button',
  'PC 端': 'Desktop',
  移动端: 'Mobile',
  人工接管: 'Human handoff',
  '当 AI 无法解决问题时，是否转给团队成员接待。':
    'Decide whether to hand over to a teammate when the AI cannot resolve an issue.',
  复制: 'Copy',
  展示标题栏: 'Show title bar',
  气泡风格: 'Bubble style',
  气泡位置: 'Bubble position',
  气泡颜色: 'Bubble color',
  气泡本身颜色: 'Bubble fill color',
  图标颜色: 'Icon color',
  气泡宽度: 'Bubble width',
  气泡高度: 'Bubble height',
  横向边距: 'Horizontal offset',
  纵向边距: 'Vertical offset',
  整体风格: 'Overall style',
  发送按钮颜色: 'Send button color',
  聊天页面背景色: 'Chat page background',
  客服气泡颜色: 'Service bubble color',
  客服气泡文字颜色: 'Service bubble text color',
  访客气泡颜色: 'Visitor bubble color',
  访客气泡文字颜色: 'Visitor bubble text color',
  输入框提示内容: 'Input placeholder',
  展示猜你想问: 'Show suggested questions',
  '访客点击问题后会直接发送。':
    'When visitors click a question, it is sent directly.',
  问题列表: 'Question list',
  添加问题: 'Add question',
  '最多展示 6 个问题，空白项不会保存。':
    'Show up to 6 questions. Blank items will not be saved.',
  预览: 'Preview',

  // Integrations
  集成: 'Integrations',
  添加: 'Add',
  '接入外部系统的工具与数据，供 AI 与人工接待调用':
    'Connect external systems’ tools and data for both AI and human reception.',
  暂无集成: 'No integrations',
  新增集成: 'Add integration',
  添加集成: 'Add integration',
  编辑集成: 'Edit integration',
  '调整集成的连接配置与认证信息。':
    'Adjust the integration connection and authentication settings.',
  该集成暂无工具: 'No tools on this integration.',
  连接配置: 'Connection settings',
  已配置: 'Configured',
  集成类型: 'Integration type',
  传输协议: 'Transport',
  服务地址: 'Service URL',
  'MCP 端点 URL': 'MCP endpoint URL',
  '你的业务系统 base_url，需实现 /helmdesk/tools 与 /helmdesk/tools/{name}/invoke 等端点':
    'Your business system base_url, which must implement endpoints such as /helmdesk/tools and /helmdesk/tools/{name}/invoke.',
  端点地址: 'Endpoint URL',
  认证方式: 'Authentication',
  持有者令牌: 'Bearer token',
  '认证 Header 名': 'Auth header name',
  '认证 Header 值': 'Auth header value',
  '超时（秒）': 'Timeout (seconds)',
  工具数: 'Tools',
  最后同步时间: 'Last synced',
  不认证: 'No authentication',
  自定义请求头: 'Custom header',
  测试连接: 'Test connection',
  同步: 'Sync',
  同步中: 'Syncing',
  重新检查: 'Check again',
  '同步状态更新超时，请手动重新检查。':
    'Sync status timed out. Check again manually.',
  同步成功: 'Synced',
  同步失败: 'Sync failed',
  重新同步工具: 'Re-sync tools',
  已下线: 'Removed',
  远端未提供描述: 'No description provided by remote.',
  输入模式: 'Input schema',
  工具标注: 'Annotations',
  '删除集成 “{name}”？': 'Delete integration “{name}”?',
  '删除后将同时移除已缓存的 {count} 个工具记录。':
    'This will also delete {count} cached tool records.',
  文档预览: 'Document preview',
  知识库文档预览: 'Knowledge document preview',
  新窗口打开: 'Open in new window',
  文档预览加载失败: 'Failed to load document preview',

  // 知识库召回测试
  RAPTOR: 'RAPTOR',
  查询内容: 'Query',
  检索模式: 'Search mode',
  检索: 'Search',
  '共命中 {count} 条': '{count} hits',
  检索路径: 'Retrievers',
  全文: 'Full-text',
  向量: 'Vector',
  已重排: 'Reranked',
  重排: 'Rerank',
  '嵌入失败，已回退全文': 'Embedding failed; fell back to full-text',
  语义命中: 'Semantic hits',
  未命中任何内容: 'No matches',
  未知来源: 'Unknown source',
  得分: 'Score',
  字面命中: 'Literal hits',
  '第 {line} 行': 'Line {line}',
  '检索失败，请稍后再试': 'Search failed, please try again',

  // 补充：缺失的英文文案
  编辑服务场景: 'Edit service scenario',
  新建服务场景: 'New service scenario',
  '为接待方案添加一个服务场景，定义任务处理器的指令。':
    'Add a service scenario to the reception plan and define the task handler instructions.',
  保存场景: 'Save scenario',
  场景名称: 'Scenario name',
  场景简介: 'Scenario description',
  场景指令: 'Scenario instructions',
  '管理应用的接待方案配置，保存即生效。':
    'Manage reception plan configuration for this app. Changes take effect on save.',
  全部工具: 'All tools',
  取消关联: 'Unlink',
  暂未关联知识库: 'No linked knowledge bases',
  授权集成: 'Authorize integration',
  取消授权: 'Revoke authorization',
  暂未授权集成: 'No authorized integrations',
  '勾选要授权给此方案的集成；展开后可进一步勾选工具白名单，不勾选则放行该集成全部已启用工具。':
    'Select the integrations to authorize for this plan. Expand one to choose a tool allowlist; leaving it unchecked allows all enabled tools of that integration.',
  暂无可用集成: 'No available integrations',
  '工具白名单（不勾选则放行全部 {count} 个已启用工具）':
    'Tool allowlist (leave unchecked to allow all {count} enabled tools)',
  该集成当前无已启用工具: 'This integration has no enabled tools',
  '确认取消关联？': 'Confirm unlink?',
  '处理中...': 'Processing...',
  '确认取消授权？': 'Confirm revoke authorization?',
  '查看已删除的接待方案并可恢复。':
    'View deleted reception plans and restore them.',
  'AI 识别说明': 'AI recognition note',
  '描述这个标签什么时候该打，AI 会据此识别。例：客户明确要求退款、退货或退费时':
    'Describe when this tag should apply; the AI uses it to recognize cases. For example: when the customer explicitly requests a refund, return, or chargeback.',
  按维度分组管理会话标签与联系人标签:
    'Manage conversation tags and contact tags grouped by dimension',
  新建标签组: 'New tag group',
  删除组: 'Delete group',
  该组暂无标签: 'No tags in this group',
  暂无标签组: 'No tag groups',
  组名称: 'Group name',
  适用维度: 'Dimension',
  '创建后不可更改；决定组内标签作用于会话还是联系人':
    'Cannot be changed after creation; determines whether tags in the group apply to conversations or contacts',
  所属标签组: 'Tag group',
  '确认删除标签组？': 'Delete this tag group?',
  '仅当组内没有标签时才能删除。':
    'A group can only be deleted when it has no tags.',
  维度: 'Dimension',
  '保存常用回复，在收件箱回复客户时可以直接使用，也可以与同事共享。':
    'Save common replies and use them while replying to customers in the inbox. You can also share them with teammates.',
  添加快捷回复: 'Add quick reply',
  筛选条件: 'Filters',
  使用范围: 'Availability',
  快捷指令: 'Shortcut',
  '删除这个快捷回复？': 'Delete this quick reply?',
  '删除后不能再使用，已经发送的消息不会受影响。':
    'It can no longer be used after deletion. Messages already sent will not be affected.',
  编辑快捷回复: 'Edit quick reply',
  示例会话主题: 'Sample conversation subject',
  客服小美: 'Agent Amy',
  我的应用: 'My app',
  保存修改: 'Save changes',
  '快捷指令（可选）': 'Shortcut (optional)',
  '只填写 / 后面的内容，例如 refund。回复时输入 /refund 即可调用。':
    'Enter only the text after /. For example, enter refund, then type /refund to use this reply.',
  回复内容: 'Reply content',
  插入自动内容: 'Insert auto-filled content',
  示例效果: 'Example',
  接待方案当前不可用: 'The reception plan is currently unavailable',
  当前绑定的接待方案: 'Currently bound reception plan',
  当前绑定的接待方案已失效: 'The bound reception plan is no longer usable',
  '不影响保存其他配置，但在切换到可用方案之前，此渠道无法新建 AI 接待会话。':
    'You can still save other settings, but the channel cannot open new AI reception sessions until you switch to a usable plan.',
  'user_token 是你后端用「自定义传参」里的签名密钥签发的 HS256 JWT（sub 为用户 ID，必填，可选 name / email / exp），作为可信身份接入、防止伪造；visitor / params 为明文，仅适合来源、活动等非敏感信息。':
    'user_token is an HS256 JWT signed by your backend with the signing secret from Custom parameters (sub is the user ID and required; name / email / exp are optional). It provides a trusted identity and prevents forgery. visitor / params are plaintext and only suitable for non-sensitive context such as source or campaign.',
  自定义入口按钮: 'Custom entry button',
  '每行一个允许加载网站嵌入代码的域名；留空或填写 * 表示不限制域名。':
    'Enter one domain per line that may load the website embed snippet. Leave empty or use * to allow any domain.',
  '登录用户身份（签名）': 'Signed user identity',
  '在「自定义传参」里设置签名密钥后，由你的后端用该密钥签发 HS256 JWT：sub 为你系统的用户 ID（必填），可选 name / email / exp。验签通过后访客以该身份接入，同一用户跨设备复用同一联系人。token 请勿写入日志或公开分享。':
    'After setting the signing secret in Custom parameters, your backend signs an HS256 JWT with it: sub is the user ID in your system (required), with optional name / email / exp. Once verified, the visitor enters with that identity, and the same user reuses one contact across devices. Do not write the token to logs or share it publicly.',
  'URL 参数会按“自定义传参”里的映射规则进入访客资料，适合来源、活动等非敏感信息；敏感身份请走上面的签名方式。':
    'URL parameters are written into visitor data according to the mapping rules in Custom parameters, which suits non-sensitive context such as source or campaign. For sensitive identity, use the signing method above.',
  添加自定义参数: 'Add custom parameter',
  未绑定接待方案: 'No reception plan bound',
  '当前接待方案均不可用（默认接待模型失效或未配置），请先调整。':
    'All reception plans are currently unavailable (the default reception model is invalid or not configured). Please adjust first.',
  '渠道尚未注册 Webhook，注册成功后 Telegram 才会把访客消息推送过来。':
    'This channel has not registered a Webhook yet. Telegram only pushes visitor messages after registration succeeds.',
  'Webhook 由业务网关托管': 'Webhook managed by business gateway',
  '开启后 webhook 归业务网关所有，消息由网关转发进来，本系统不再直连注册。':
    'When enabled, the webhook belongs to the business gateway. Messages are forwarded in by the gateway and this system no longer registers directly.',
  'Webhook 已由业务网关托管：访客消息经网关转发进入本系统，请勿在此注册，否则会抢占网关的 webhook 导致业务事件丢失。':
    'The webhook is managed by a business gateway: visitor messages are forwarded in by the gateway. Do not register here, or you will take over the webhook and the gateway will lose business events.',
  'Webhook 密钥': 'Webhook secret',
  '网关转发访客消息时，须以此密钥作为 X-Telegram-Bot-Api-Secret-Token 请求头，本系统据此鉴权入站请求。':
    'When forwarding visitor messages, the gateway must send this secret in the X-Telegram-Bot-Api-Secret-Token header; inbound requests are authenticated against it.',
  注册时间: 'Registered at',
  'Webhook 地址': 'Webhook URL',
  '重新注册 Webhook': 'Re-register',
  '注册 Webhook': 'Register',
  '更换 Token 后保存，会重新校验并注册 webhook。':
    'Saving after changing the token re-validates it and re-registers the webhook.',
  默认访客语言: 'Default visitor language',
  'Telegram 渠道': 'Telegram channels',
  '接入 Telegram Bot：访客在 Telegram 上即可与 AI 客服对话。':
    'Connect a Telegram Bot so visitors can chat with the AI agent on Telegram.',
  机器人: 'Bot',
  '暂无 Telegram 渠道': 'No Telegram channels yet',
  '删除后将从 Telegram 撤销 webhook，访客将无法再通过该 Bot 联系客服。':
    'After deletion, the webhook is revoked from Telegram and visitors can no longer reach support through this Bot.',
  '创建 Telegram 渠道': 'Create Telegram channel',
  '接入一个 Telegram Bot，访客在 Telegram 上即可与客服对话。':
    'Connect a Telegram Bot so visitors can chat with support on Telegram.',
  '当前接待方案均不可用（已归档或默认接待模型失效），请先调整。':
    'All reception plans are currently unavailable (archived or the default reception model is invalid). Please adjust first.',
  '当前接待方案均不可用，请先调整接待方案。':
    'No reception plans are currently available. Please adjust the reception plans first.',
  '先创建渠道，下一步再完成 Telegram 接入配置。':
    'Create the channel first, then configure the Telegram connection in the next step.',
  'Bot Token': 'Bot Token',
  'Telegram 配置': 'Telegram settings',
  'Telegram 接入状态': 'Telegram connection status',
  'Telegram Bot 已配置，可以接收访客消息。':
    'The Telegram Bot is configured and can receive visitor messages.',
  '请在左侧填写并保存 Telegram 配置。':
    'Fill in and save the Telegram settings on the left.',
  'Webhook URL': 'Webhook URL',
  重新注册: 'Re-register',
  '当前由业务网关托管。': 'Currently managed by the business gateway.',
  '注册后 Telegram 才会推送访客消息。':
    'Telegram will deliver visitor messages after the webhook is registered.',
  检测: 'Check',
  '请核对 Bot Token 格式后再检测。':
    'Please verify the bot token format before checking.',
  'Telegram 渠道回收站': 'Telegram channel recycle bin',
  '查看已删除的 Telegram 渠道并可恢复':
    'View deleted Telegram channels and restore them',
  '恢复后将重新向 Telegram 注册 webhook 并出现在渠道列表中。':
    'After restore, the webhook is re-registered with Telegram and the channel appears in the channel list again.',

  // WeChat Official Account channels
  微信公众号: 'WeChat Official Account',
  微信公众号渠道: 'WeChat Official Account channels',
  创建微信公众号渠道: 'Create WeChat Official Account channel',
  '先创建渠道，下一步再完成微信公众号接入配置。':
    'Create the channel first, then configure the WeChat Official Account integration.',
  '接入微信公众号原生消息，访客可直接在公众号内咨询。':
    'Connect native WeChat Official Account messaging so visitors can contact support directly in WeChat.',
  暂无微信公众号渠道: 'No WeChat Official Account channels yet',
  '删除后公众号回调将不再进入当前应用。':
    'After deletion, callbacks from this official account will no longer reach the current app.',
  微信公众号渠道回收站: 'WeChat Official Account channel recycle bin',
  '已暂停的公众号渠道可在这里恢复。':
    'Restore paused official account channels here.',
  返回列表: 'Back to list',
  回收站为空: 'The recycle bin is empty',
  微信配置: 'WeChat configuration',
  配置微信公众号凭证与原生消息接入:
    'Configure WeChat Official Account credentials and native messaging',
  默认接待语言: 'Default reception language',
  翻译语境提示: 'Translation context hint',
  '例如：本渠道访客常用英语夹杂罗马音印地语（Hinglish），如 peelij=please、nahi=没有。':
    'For example: visitors on this channel often mix English with romanized Hindi (Hinglish), such as peelij=please and nahi=no.',
  '当前没有可部署的接待方案，请先调整接待方案。':
    'No deployable reception plan is available. Update a reception plan first.',
  '公众号 AppID': 'Official Account AppID',
  '公众号 AppSecret': 'Official Account AppSecret',
  '消息校验 Token': 'Message verification token',
  消息加解密模式: 'Message encryption mode',
  明文模式: 'Plaintext mode',
  安全模式: 'Secure mode',
  EncodingAESKey: 'EncodingAESKey',
  '安全模式需要填写 43 位 EncodingAESKey。':
    'Secure mode requires a 43-character EncodingAESKey.',
  '明文模式不使用 EncodingAESKey。':
    'Plaintext mode does not use an EncodingAESKey.',
  微信公众号接入状态: 'WeChat Official Account connection',
  '微信凭证已保存，可将配置填入微信开发者平台。':
    'WeChat credentials are saved. You can now enter the configuration in the WeChat developer platform.',
  '请在左侧填写并保存微信凭证。':
    'Enter and save the WeChat credentials on the left.',
  '请将此 URL、Token 和 EncodingAESKey 一起填入微信公众号开发者配置。':
    'Enter this URL, Token, and EncodingAESKey in the WeChat Official Account developer configuration.',
  '复制失败，请手动复制': 'Copy failed. Please copy manually.',
  AppID: 'AppID',
  消息模式: 'Message mode',
  '渠道 Code': 'Channel code',
  '正在上传，请稍候...': 'Uploading, please wait...',
  翻译供应商: 'Translation providers',
  新增翻译供应商: 'Add translation provider',
  编辑翻译供应商: 'Edit translation provider',
  暂无翻译供应商: 'No translation providers',
  翻译测试失败: 'Translation test failed',
  '请求失败，请稍后再试': 'Request failed. Please try again later.',
  '确认删除该翻译供应商？': 'Delete this translation provider?',
  '删除后该供应商立即移出翻译轮询池，且无法恢复。':
    'The provider will be removed from translation immediately and cannot be restored.',
  '仅启用的供应商进入运行时翻译轮询池。':
    'Only enabled providers are used for translation.',
  '统一管理机器翻译与 AI 翻译凭据；系统按渠道策略优先选择，失败时自动轮询。':
    'Manage machine and AI translation credentials in one place. Providers are prioritized by channel policy with automatic failover.',
  '调整翻译供应商的名称、凭据和启用状态。':
    'Update the translation provider name, credentials, and status.',
  '配置翻译服务凭据；保存后用于收件箱自动翻译。':
    'Configure translation service credentials for automatic inbox translation.',
  '悬浮气泡颜色跟随访客界面的主题颜色。':
    'The floating bubble uses the visitor interface theme color.',
  '该集成的外部用户 id 所属命名空间，用于与联系人 external_id 精确关联':
    'Namespace for external user IDs from this integration, used to match contact external_id identities.',
  '选择从哪个渠道获取该客户的外部标识；留空则业务系统仅按邮箱匹配。':
    'Select the channel that provides the external customer ID, or leave empty to match by email only.',
  '不关联（按邮箱匹配）': 'No association (match by email)',
  '用于把该集成的外部用户与联系人 external_id 身份精确关联；留空则不按 external_id 匹配。仅允许小写字母、数字和下划线。':
    'Precisely matches external users from this integration to contact external_id identities. Leave empty to disable external_id matching. Use lowercase letters, numbers, and underscores only.',
  身份命名空间: 'Identity namespace',

  // 常规设置页面
  Logo: 'Logo',
  '修改系统名称、Logo 和注册方式。':
    'Update the system name, logo, and sign-up settings.',
  允许自行注册: 'Allow self-sign-up',
  '开启后，任何人都可以在登录页创建后台账号。':
    'When enabled, anyone can create an admin account from the sign-in page.',

  // 客服页面
  创建账号: 'Create account',
  接待昵称: 'Customer-facing name',
  添加和管理客服: 'Add and manage teammates',
  添加客服: 'Add teammate',
  '移除后，这名客服将无法进入系统后台，但账号仍会保留。':
    'After removal, this agent will no longer be able to access the admin panel, but the account will remain.',
  移除客服: 'Remove teammate',
  '移除这名客服？': 'Remove this teammate?',
  '输入对方邮箱，我们会发送邀请邮件。对方设置密码后即可加入系统。':
    "Enter their email address. We'll send an invitation, and they can join after setting a password.",
  还没有客服: 'No teammates yet',
  '选择这名客服可以使用的功能。': 'Choose the features this agent can use.',
  邀请新客服: 'Invite new teammate',
  重新发送: 'Resend',

  // 集成页面
  'MCP 服务地址': 'MCP service URL',
  '修改外部系统的连接信息。':
    'Change the external system’s connection details.',
  '删除后，其中的 {count} 个工具也会一并移除。':
    'Deleting this integration also removes its {count} tools.',
  '填写业务系统的基础地址。该地址需提供 /helmdesk/tools 和 /helmdesk/tools/{name}/invoke。':
    'Enter your business system’s base URL. It must provide /helmdesk/tools and /helmdesk/tools/{name}/invoke.',
  无需验证: 'No verification',
  '暂时没有获取到更新结果，请重新检查。':
    'No update result was received. Check again.',
  更新中: 'Updating',
  更新工具: 'Update tools',
  '最长等待时间（秒）': 'Maximum wait (seconds)',
  访问令牌: 'Access token',
  请求头内容: 'Request header value',
  请求头名称: 'Request header name',
  还没有可用工具: 'No tools available yet',
  还没有添加集成: 'No integrations added yet',
  '连接外部系统，让 AI 和客服使用其中的工具和数据。':
    'Connect external systems so AI and teammates can use their tools and data.',
  集成名称: 'Integration name',
  验证方式: 'Verification',

  // 接待方案页面
  'AI 先接待': 'AI first',
  'AI 暂时不可用时的提示': 'Message when AI is unavailable',
  不提供人工服务: 'No human support',
  全部可用工具: 'All available tools',
  '删除后会移到回收站，可以稍后恢复。正在使用的方案无法删除。':
    'It will move to the recycle bin and can be restored later. Plans in use cannot be deleted.',
  '删除这个接待方案？': 'Delete this reception plan?',
  只在指定时间提供人工服务: 'Only offer human support during set hours',
  '可用工具（未选择时使用全部 {count} 个工具）':
    'Available tools (leave all unselected to use all {count} tools)',
  回复时引用访客消息: "Quote the visitor's message in replies",
  '回复要友好、简短、准确。先弄清访客的问题，再给出明确答复；不确定时如实说明，并询问必要信息。':
    "Be friendly, brief, and accurate. Understand the visitor's question before answering clearly. If you're unsure, say so and ask for the information you need.",
  回复语气: 'Reply tone',
  回收站里没有接待方案: 'No reception plans in the recycle bin',
  '填写方案名称和客服信息，添加后可继续设置接待方式。':
    'Enter the plan name and agent details. You can set the reception mode after adding it.',
  '填写方案名称和访客看到的客服信息。':
    'Enter the plan name and the agent details visitors will see.',
  '多久没有消息后结束会话（分钟）':
    'Time without messages before ending (minutes)',
  完成选择: 'Done',
  客服先接待: 'Agent first',
  '客服多久未回复后转由 AI 接待（分钟）':
    'Agent reply timeout before switching to AI (minutes)',
  '客服长时间未回复时转由 AI 接待': 'Switch to AI if the agent has not replied',
  '当前不在人工服务时间，AI 会先接待，客服会在服务时间继续跟进。':
    'Outside service hours, AI will respond first and an agent will follow up during service hours.',
  当前没有可用工具: 'No tools available',
  '很抱歉，AI 暂时无法回复，正在为您转接客服，请稍候。':
    'Sorry, AI cannot reply right now. We are connecting you to a support agent. Please wait.',
  '恢复后会重新显示在接待方案列表中。':
    'It will appear in the reception plan list again.',
  '恢复这个接待方案？': 'Restore this reception plan?',
  接待要求: 'Reception requirements',
  '无人接待时转由 AI 接待': 'Switch to AI when no agent is available',
  暂时无法转接客服时的提示: 'Message when no agent is available',
  有客服在线时优先接待重点客户:
    'Prioritize priority customers when an agent is online',
  '查看已删除的接待方案，可以随时恢复。':
    'View deleted reception plans and restore them anytime.',
  '正在为您转接客服，请稍等。':
    'We are connecting you to a support agent. Please wait.',
  正在转接客服时的提示: 'Message while connecting to an agent',
  每周服务时间: 'Weekly service hours',
  '目前无法转接客服，我会继续为您处理。':
    'No support agent is available right now. I will keep helping you.',
  '移除并保存后，接待时将不再使用这个知识库。':
    'After you remove it and save, this plan will no longer use this knowledge base.',
  '移除并保存后，接待时将不再使用这个集成。':
    'After you remove it and save, this plan will no longer use this integration.',
  移除知识库: 'Remove knowledge base',
  '移除这个知识库？': 'Remove this knowledge base?',
  '移除这个集成？': 'Remove this integration?',
  移除集成: 'Remove integration',
  '等待多久后转由 AI 接待（分钟）': 'Wait before switching to AI (minutes)',
  '设置 AI 和客服如何接待访客，保存后生效。':
    'Set how AI and agents receive visitors. Changes take effect after you save.',
  '设置客服每周提供人工服务的时间。':
    'Set the weekly hours when agents are available.',
  '设置由 AI 还是客服先接待，以及何时切换或结束会话。':
    'Choose whether AI or an agent responds first, and when to switch or end the conversation.',
  '设置这个方案如何接待访客。': 'Set how this plan receives visitors.',
  还没有接待方案: 'No reception plans yet',
  还没有选择知识库: 'No knowledge bases selected',
  还没有选择集成: 'No integrations selected',
  '选择接待时可以使用的知识库。':
    'Choose the knowledge bases this plan can use.',
  '选择接待时可以使用的集成和工具。':
    'Choose the integrations and tools this plan can use.',
  '选择接待时需要使用的知识库，保存方案后生效。':
    'Choose the knowledge bases this plan needs. Changes take effect after you save.',
  '选择接待时需要使用的集成和工具，保存方案后生效。':
    'Choose the integrations and tools this plan needs. Changes take effect after you save.',
  选择知识库: 'Select knowledge bases',
  选择集成: 'Select integrations',
  重点客户有风险时转人工: 'Hand off risky issues for priority customers',
  重点客户谨慎回复: 'Reply carefully to priority customers',
  长时间无消息时结束会话: 'End conversations after no messages',
  集成已不可用: 'Integration unavailable',
  非人工服务时间的提示: 'Message outside human service hours',

  // 知识库页面
  'Word、PDF、TXT、Markdown 和 HTML': 'Word, PDF, TXT, Markdown, and HTML',
  '一次最多上传 {count} 个文件，其余文件未上传。':
    'You can upload up to {count} files at a time. The remaining files were not uploaded.',
  不放入其他分组: 'Do not place in another group',
  '以下文件无法上传：{names}。仅支持 {types}。':
    'These files cannot be uploaded: {names}. Supported types: {types}.',
  '以下文件超过 {size}，无法上传：{names}。':
    'These files are over {size} and cannot be uploaded: {names}.',
  '修改知识库的名称、用途说明和图标。':
    'Change the name, description, and icon.',
  '共 {count} 个文件': '{count} files',
  '共显示 {count} 条结果': 'Showing {count} results',
  '其他问法 {number}': 'Alternative wording {number}',
  '其他问法（选填）': 'Other ways to ask (optional)',
  内容: 'Content',
  '内容加载失败，请重试。': 'Could not load the content. Try again.',
  分组已不存在: 'Group no longer exists',
  '删除后无法恢复，其中的所有内容和分组也会一并删除。':
    'This cannot be undone. All content and groups in it will also be deleted.',
  '删除后无法恢复，其他问法和全部答案也会一并删除。':
    'This cannot be undone. Its alternative wordings and all answers will also be deleted.',
  '删除后无法恢复，知识库将不再使用这份文档。':
    'This cannot be undone. The knowledge base will no longer use this document.',
  '删除第 {number} 个答案': 'Delete answer {number}',
  '删除第 {number} 个问法': 'Delete alternative wording {number}',
  '删除这个分组？': 'Delete this group?',
  '删除这个文档？': 'Delete this document?',
  '删除这个知识库？': 'Delete this knowledge base?',
  '删除这条问答？': 'Delete this Q&A?',
  '只能删除空分组，请先移走其中的内容，并移走或删除子分组。':
    'Only empty groups can be deleted. Move its content and move or delete its child groups first.',
  在新窗口打开: 'Open in a new window',
  填写内容: 'Add content',
  填写问答: 'Add Q&A',
  开始测试: 'Run test',
  按关键词找到的内容: 'Keyword matches',
  按意思找到的内容: 'Meaning-based matches',
  搜索文件名: 'Search file names',
  搜索问题或答案: 'Search questions or answers',
  '支持 Word、PDF、TXT、Markdown 和 HTML；单个文件不超过 20 MB，一次最多 20 个。':
    'Supports Word, PDF, TXT, Markdown, and HTML. Each file can be up to 20 MB, with up to 20 files at a time.',
  '支持 {types}；单个文件不超过 {size}，一次最多上传 {count} 个。':
    'Supports {types}. Each file can be up to {size}, with up to {count} files at a time.',
  放在: 'Place in',
  '文档加载失败，请重试。': 'Could not load the document. Try again.',
  '最多可创建两级分组。': 'You can create up to two group levels.',
  '最多可添加 {count} 个其他问法。':
    'You can add up to {count} alternative wordings.',
  '最多可添加 {count} 个答案。': 'You can add up to {count} answers.',
  未标明来源: 'Source not specified',
  查找方式: 'Search method',
  查看文档内容: 'View document content',
  '正在上传 {name}': 'Uploading {name}',
  '正在上传…': 'Uploading…',
  '正在加载…': 'Loading…',
  没有找到相关内容: 'No related content found',
  没有找到相关文档: 'No matching documents',
  没有找到相关问答: 'No matching Q&A',
  '测试失败，请稍后再试。': 'Test failed. Try again later.',
  测试知识库: 'Test knowledge base',
  添加内容: 'Add content',
  添加分组: 'Add group',
  '添加后即可上传文档或填写问答。':
    'Once added, you can upload documents or add Q&A.',
  添加知识库: 'Add knowledge base',
  添加答案: 'Add answer',
  添加问法: 'Add alternative wording',
  直接填写: 'Enter directly',
  知识库图标: 'Knowledge base icon',
  知识库类型: 'Knowledge base type',
  确认移动: 'Move',
  移动到其他分组: 'Move to another group',
  '答案 {number}': 'Answer {number}',
  '管理知识库中的文档、问答和分组':
    'Manage documents, Q&A, and groups in your knowledge bases',
  '管理知识库中的文档。': 'Manage documents in this knowledge base.',
  '管理知识库中的问题和答案。':
    'Manage questions and answers in this knowledge base.',
  编辑内容: 'Edit content',
  请先添加一个知识库: 'Add a knowledge base first',
  请选择一个知识库: 'Choose a knowledge base',
  请选择其他分组: 'Choose another group',
  请选择分组: 'Choose a group',
  '输入客户可能提出的问题，查看知识库能找到哪些内容。':
    'Enter a question a customer might ask to see what this knowledge base finds.',
  输入问题或关键词: 'Enter a question or keyword',
  还没有其他问法: 'No alternative wordings yet',
  还没有文档: 'No documents yet',
  还没有知识库: 'No knowledge bases yet',
  还没有问答: 'No Q&A yet',
  '这个分组中还有子分组，请先移动或删除它们。':
    'This group still has child groups. Move or delete them first.',
  选择分组: 'Choose a group',
  '选择文件，或拖到这里': 'Choose files or drag them here',
  '重复文件未再次上传：{names}。':
    'Duplicate files were not added again: {names}.',
  重新上传失败文件: 'Retry failed files',
  重新加载: 'Reload',
  重新处理: 'Process again',
  问题或关键词: 'Question or keyword',

  // 渠道页面
  AppSecret: 'AppSecret',
  Token: 'Token',
  'Token 验证通过，保存后生效。': 'Token verified. Save to apply.',
  主题色: 'Theme color',
  '二维码生成失败，请重试。': 'Could not generate the QR code. Try again.',
  '传入访客信息（开发人员）': 'Pass visitor data (developers)',
  使用方式: 'Ways to use',
  使用网站自己的按钮: "Use your website's button",
  '使用网站自己的按钮后，系统不会再显示默认聊天按钮。':
    "When you use your website's button, the default chat button will be hidden.",
  '例如：访客常用中英混合表达，产品名称请保留英文。':
    'For example: visitors often mix Chinese and English; keep product names in English.',
  '例如：访客经常混用中文和英文，产品名称通常使用英文。':
    'For example: visitors often mix Chinese and English, and product names are usually in English.',
  保存位置: 'Save location',
  保存到: 'Save to',
  '允许使用的网站（选填）': 'Allowed websites (optional)',
  '先填写基本信息，添加后再填写微信公众号开发者配置。':
    'Enter the basic details first. After adding the channel, configure the WeChat Official Account developer settings.',
  '先填写基本信息，添加后再设置网站入口。':
    'Enter the basic details first. After adding the channel, set up the website entry.',
  '先填写基本信息，添加后再连接 Telegram 机器人。':
    'Enter the basic details first, then connect the Telegram bot after adding the channel.',
  先显示欢迎页: 'Show welcome screen first',
  公众号接入: 'Official Account connection',
  '其他内容仍可保存，但访客暂时无法发起新的咨询。请选择可用的接待方案。':
    'You can still save other settings, but visitors cannot start new conversations until you select an available reception plan.',
  具体位置: 'Specific location',
  '内容尚未保存，确定离开吗？未保存的修改会丢失。':
    'You have unsaved changes. Leave and discard them?',
  '删除后会移到回收站，不会再开始新的会话；进行中的会话仍可继续。':
    'The channel will move to the recycle bin and stop new conversations. Ongoing conversations can continue.',
  '删除后会移到回收站，公众号的新消息将无法进入当前应用。恢复后可继续使用。':
    'After deletion, new messages from the Official Account will no longer enter this app. You can restore it later.',
  '删除后会移到回收站，访客将暂时无法通过这个入口发起会话。恢复后可继续使用。':
    'The channel will move to the recycle bin, and visitors will temporarily be unable to start conversations through this entry. Restore it to use it again.',
  删除规则: 'Delete rule',
  '删除这个 Telegram 渠道？': 'Delete this Telegram channel?',
  '删除这个微信公众号渠道？': 'Delete this WeChat Official Account channel?',
  '删除这个网站渠道？': 'Delete this web channel?',
  删除问题: 'Delete question',
  '副标题（选填）': 'Subtitle (optional)',
  '只有当 Telegram 消息先进入你自己的系统，再转发到 HelmDesk 时才开启。一般情况请保持关闭。':
    'Turn this on only when Telegram messages first enter your own system and are then forwarded to HelmDesk. Otherwise, leave it off.',
  '只有当 Telegram 消息先进入你自己的系统，再转发到 HelmDesk 时才开启。一般情况请保持关闭，保存后生效。':
    'Turn this on only when Telegram messages first enter your own system and are then forwarded to HelmDesk. Otherwise, leave it off. Save to apply.',
  '可以直接把聊天链接发给访客，也可以放在网站按钮或二维码中。':
    'Send the chat link directly to visitors, or use it in a website button or QR code.',
  '回收站里没有 Telegram 渠道': 'No Telegram channels in the recycle bin',
  回收站里没有微信公众号渠道:
    'No WeChat Official Account channels in the recycle bin',
  回收站里没有网站渠道: 'No web channels in the recycle bin',
  '在 Telegram 的 @BotFather 中创建机器人并复制 Token。请勿分享给他人。已设置时留空即可保留。':
    'Create a bot with @BotFather in Telegram and copy its token. Do not share it. Leave this blank to keep the current token.',
  在手机上全屏显示: 'Show full screen on mobile',
  '填写并保存公众号配置，然后到微信公众平台启用服务器配置。':
    'Enter and save the Official Account settings, then enable the server configuration in the WeChat Official Platform.',
  '填写机器人 Token 并保存，系统会自动连接。':
    'Enter the bot token and save. The system will connect it automatically.',
  '复制失败，请手动复制。': 'Copy failed. Copy it manually.',
  如何使用聊天链接: 'How to use the chat link',
  如何添加到网站: 'How to add it to your website',
  '如需识别已登录访客或记录活动来源，请让开发人员按上面的示例接入。普通参数只适合来源、活动等非敏感信息。':
    'To identify signed-in visitors or record campaign sources, ask a developer to follow the example above. Regular parameters are only suitable for non-sensitive data such as sources and campaigns.',
  客服显示方式: 'How the service identity appears',
  '将这个地址与左侧填写的 Token、EncodingAESKey 填入微信公众平台的服务器配置。':
    'Enter this address together with the Token and EncodingAESKey shown on the left in the server configuration on the WeChat Official Platform.',
  '已删除的 Telegram 渠道可以在这里恢复。':
    'Restore deleted Telegram channels here.',
  '已删除的微信公众号渠道可以在这里恢复。':
    'Deleted WeChat Official Account channels can be restored here.',
  已有内容时: 'If content already exists',
  已设置: 'Set',
  '已设置，留空表示不更换': 'Set. Leave blank to keep it.',
  已连接: 'Connected',
  常见问题: 'Common questions',
  '开启后，访客在手机上打开聊天时会全屏显示，更方便输入和查看消息。':
    'When enabled, chat opens full screen on mobile so visitors can type and read messages more easily.',
  当前接待方案: 'Current reception plan',
  当前接待方案不可用: 'The current reception plan is unavailable',
  '当前没有可用的接待方案。': 'No reception plans are currently available.',
  '当前设置为由外部系统转发消息。':
    'Messages are currently forwarded through an external system.',
  '当前通过外部系统接收消息。请勿在这里重新连接 Telegram，否则外部系统将无法继续接收消息。':
    'Messages currently arrive through an external system. Do not reconnect Telegram here, or that system will stop receiving messages.',
  微信公众号连接状态: 'WeChat Official Account connection status',
  '恢复后会重新出现在 Telegram 渠道列表中。':
    'It will reappear in the Telegram channel list after restoration.',
  '恢复后会重新显示在微信公众号渠道列表中。':
    'It will appear in the WeChat Official Account channel list again.',
  '恢复后会重新显示在网站渠道列表中。':
    'It will reappear in the web channel list after restoration.',
  '恢复这个 Telegram 渠道？': 'Restore this Telegram channel?',
  '恢复这个微信公众号渠道？': 'Restore this WeChat Official Account channel?',
  '恢复这个网站渠道？': 'Restore this web channel?',
  '把安装代码添加到网站中，访客就能通过聊天入口发起咨询。':
    'Add the installation code to your website so visitors can start conversations through the chat entry.',
  '把网站传来的信息自动填写到联系人资料、自定义字段或标签中。只适合来源、活动等非敏感信息。':
    'Use data from your website to fill contact details, custom fields, or tags automatically. Only use this for non-sensitive data such as sources and campaigns.',
  按钮位置: 'Button position',
  按钮大小: 'Button size',
  按钮样式: 'Button style',
  接待方案不可用: 'Reception plan unavailable',
  '明文模式不需要填写 EncodingAESKey。':
    'EncodingAESKey is not required in plaintext mode.',
  显示常见问题: 'Show common questions',
  显示新消息预览: 'Show new message previews',
  显示未读提醒: 'Show unread indicator',
  显示标题栏: 'Show header',
  更新二维码: 'Update QR code',
  '最多添加 6 个，访客点击后会直接发送。':
    'Add up to 6 questions. A visitor sends one by selecting it.',
  最近接入网站: 'Most recently connected website',
  '有新消息时，在聊天按钮右上角显示提醒。':
    'Show an indicator on the chat button when a new message arrives.',
  '有新消息时，在聊天按钮旁显示消息预览，访客点击即可打开聊天。':
    'Show a message preview beside the chat button. Visitors can select it to open the chat.',
  '服务器地址（URL）': 'Server URL',
  未连接: 'Not connected',
  未连接机器人: 'Bot not connected',
  未选择接待方案: 'No reception plan selected',
  '机器人 Token（Bot Token）': 'Bot token',
  '机器人尚未连接，请点击连接后重试。':
    'The bot is not connected. Select Connect to try again.',
  '机器人已连接，访客消息会进入收件箱。':
    'The bot is connected. Visitor messages will appear in the inbox.',
  机器人接入: 'Bot connection',
  机器人连接状态: 'Bot connection status',
  查看使用说明: 'View instructions',
  '查看已删除的网站渠道，可以随时恢复。':
    'View deleted web channels and restore them at any time.',
  查看接待方案: 'View reception plans',
  标签名称: 'Tag name',
  '标题图标（选填）': 'Header icon (optional)',
  '欢迎语（选填）': 'Greeting (optional)',
  欢迎页文案: 'Welcome screen message',
  '每行填写一个域名。留空或填写 * 表示所有网站都可以使用。':
    'Enter one domain per line. Leave blank or enter * to allow all websites.',
  消息加密方式: 'Message encryption',
  '消息接收地址（Webhook URL）': 'Message receiving URL (Webhook URL)',
  消息转发密钥: 'Message forwarding secret',
  '添加 Telegram 渠道': 'Add Telegram channel',
  '添加中...': 'Adding...',
  添加传入规则: 'Add incoming rule',
  添加微信公众号渠道: 'Add WeChat Official Account channel',
  添加渠道: 'Add channel',
  添加网站渠道: 'Add web channel',
  渠道编号: 'Channel ID',
  '用 {value} 代表传入的内容，例如“活动-{value}”。传入内容只能包含字母、数字、下划线或短横线，最多 40 个字符。':
    'Use {value} for the incoming value, for example, “campaign-{value}”. The value can contain letters, numbers, underscores, or hyphens, up to 40 characters.',
  '用途说明（选填）': 'Description (optional)',
  由外部系统转发消息: 'Receive messages through an external system',
  网站参数: 'Website parameters',
  '网站可以用密钥安全地识别已登录访客，避免他人冒用身份。此功能需要开发人员接入。':
    'Your website can use a secret to identify signed-in visitors securely and prevent impersonation. A developer must set this up.',
  网站渠道回收站: 'Web channel recycle bin',
  '访客消息优先使用 AI 增强翻译':
    'Prefer AI-enhanced translation for visitor messages',
  '适合多语言混写、罗马音或俚语场景。机器翻译通常更快、更稳定。':
    'Useful for mixed languages, romanization, or slang. Machine translation is usually faster and more consistent.',
  'AI 翻译补充说明（选填）': 'AI translation notes (optional)',
  聊天入口: 'Chat entry',
  聊天展开时的图标: 'Icon when chat is open',
  聊天按钮: 'Chat button',
  聊天收起时的图标: 'Icon when chat is closed',
  聊天标题: 'Chat title',
  聊天界面: 'Chat interface',
  '让访客通过网站嵌入或聊天链接发起咨询。':
    'Let visitors start conversations through website embeds or chat links.',
  '设置渠道信息、接待方案和 Telegram 机器人。':
    'Set the channel details, reception plan, and Telegram bot.',
  '设置渠道信息和微信公众号开发者配置。':
    'Set the channel details and WeChat Official Account developer settings.',
  '设置网站传来的信息要保存到哪里，保存页面后生效。':
    'Choose where to save data from your website. Save the page to apply.',
  '设置访客如何进入聊天，以及聊天页面显示什么。':
    'Set how visitors enter chat and what appears on the chat screen.',
  访客信息: 'Visitor data',
  访客默认语言: 'Default visitor language',
  识别已登录访客: 'Identify signed-in visitors',
  '识别已登录访客（开发人员）': 'Identify signed-in visitors (developers)',
  '请先保存或放弃访客信息中的修改。':
    'Save or discard your visitor data changes first.',
  '请将以下信息填写到负责转发 Telegram 消息的外部系统中。':
    'Enter the following details in the external system that forwards Telegram messages.',
  '请把上面的代码交给开发人员，用网站按钮打开聊天。':
    "Give the code above to a developer so your website's button can open the chat.",
  '请核对机器人 Token 格式后再验证。':
    'Check the bot token format, then verify it again.',
  请选择接待方案: 'Select a reception plan',
  '距页面底部（像素）': 'Distance from bottom (pixels)',
  '输入框提示语（选填）': 'Input placeholder (optional)',
  还未生成密钥: 'No secret generated yet',
  '还没有 Telegram 渠道': 'No Telegram channels yet',
  还没有传入规则: 'No incoming rules yet',
  还没有微信公众号渠道: 'No WeChat Official Account channels yet',
  还没有网站渠道: 'No web channels yet',
  '这里只显示允许外部填写的自定义字段。单选字段的传入内容需要和已有选项一致。':
    'Only custom fields that allow external input are shown. For single-select fields, the incoming value must match an existing option.',
  连接: 'Connect',
  '连接 Telegram 机器人，让访客可以在 Telegram 中发起咨询。':
    'Connect a Telegram bot so visitors can start conversations in Telegram.',
  '连接后才能接收 Telegram 消息。':
    'Connect the bot to receive Telegram messages.',
  '连接微信公众号，让关注者可以直接在公众号中发起咨询。':
    'Connect a WeChat Official Account so followers can start conversations directly.',
  '选择“使用网站自己的按钮”后，系统不会显示默认聊天按钮。请让开发人员使用上面的代码打开聊天。':
    "After selecting “Use your website's button,” the default chat button will be hidden. Ask a developer to use the code above to open chat.",
  '配置信息已保存，可以到微信公众平台启用服务器配置。':
    'The settings have been saved. You can now enable the server configuration in the WeChat Official Platform.',
  重新连接: 'Reconnect',
  '重置后，网站将无法继续使用旧密钥识别访客。请确认开发人员已准备好更新。':
    'After resetting, your website can no longer use the old secret to identify visitors. Make sure a developer is ready to update it.',
  '重置这个密钥？': 'Reset this secret?',
  '链接中的参数会按照“访客信息”设置填写联系人资料，只适合来源、活动等非敏感信息。':
    'Parameters in the link fill contact details according to the Visitor data settings. Only use them for non-sensitive data such as sources and campaigns.',
  '附加参数只填写 ? 后面的内容。':
    'For extra parameters, enter only the part after ?.',
  '附加参数（选填）': 'Extra parameters (optional)',
  附带访客信息: 'Include visitor data',
  '需要和聊天收起时的图标一起上传。':
    'Upload this together with the icon used when chat is closed.',
  '需要把网站登录用户识别为同一位访客时，请让开发人员使用“访客信息”中的密钥接入。密钥和生成的身份信息不要公开分享。':
    'To recognize a signed-in website user as the same visitor, ask a developer to use the secret in Visitor data. Do not share the secret or generated identity data publicly.',
  '验证 Token': 'Verify token',

  // 通用页面
  请确认翻译内容后再发送: 'Confirm the translation before sending',
} as const;
