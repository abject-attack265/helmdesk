/**
 * 文件说明：英文语言包，页面通过 useI18n 按中文 key 读取英文文案。
 */
// 应用 AI 设置（英文）
export default {
  // AI 设置
  'AI 模型': 'AI Models',
  'AI 调用日志': 'AI call logs',
  联系人历史会话上下文: 'Contact conversation history',
  // AI 模型管理页
  'AI 模型管理': 'AI Models',
  '管理所有供应商下的模型；同类型内用上移/下移排主备，运行时按顺序取用、失败自动降级。':
    'Manage models across all providers; reorder within a type to set primary/backup. The runtime picks them in order and falls back on failure.',
  维: 'dim',
  '暂无模型，点击右上角添加。': 'No models yet. Add one from the top right.',
  '确认删除模型？': 'Delete this model?',
  '删除后该模型立即移出当前应用取用池，且无法恢复。':
    'Once deleted, the model is removed from this app pool and cannot be restored.',
  '模型只声明能力与用途，凭据由所属供应商管理。':
    'A model declares capability and purposes; credentials are managed by its provider.',
  供应商: 'Provider',
  选择供应商: 'Select provider',
  '从常见模型选（可选）': 'Pick from common models (optional)',
  '一键带出 model_id 与名称': 'Fill model_id and name in one click',
  '运行时按用途从当前应用模型池取用该模型，可多选。':
    'The runtime picks this model by purpose from this app pool; multiple allowed.',
  '嵌入模型的检索选型在「知识库引擎」页 pin。':
    'The retrieval embedding model is pinned on the Knowledge Engine page.',
  新增模型: 'Add Model',
  '选择供应商与用途，录入参与当前应用调度的模型':
    'Pick a provider and purpose, then register a model into this app scheduling pool',
  '调整模型的显示名称、用途、权重与启用状态':
    "Adjust the model's display name, purpose, weight, and active status",
  常见模型: 'Common models',
  选择以快速填充: 'Select to autofill',
  默认维度: 'Default dimensions',
  '管理所有供应商下的模型，同类型内排序定主备。':
    'Manage models across all providers; reorder within a type to set primary/backup.',
  // AI 供应商页
  'AI 供应商': 'AI Providers',
  '当前应用的 AI 服务凭据，供本应用模型使用。':
    'AI service credentials for this app, used by its models.',
  '新增 AI 供应商': 'Add AI Provider',
  品牌: 'Brand',
  '确认删除该 AI 供应商？': 'Delete this AI provider?',
  '删除后该供应商及其下所有模型立即移出当前应用取用池，且无法恢复。':
    'Once deleted, the provider and all its models are removed from this app pool and cannot be restored.',
  '暂无 AI 供应商': 'No AI providers yet',
  '选择品牌并填写凭据；保存后到「AI 模型管理」页添加该供应商下的模型。':
    'Pick a brand and fill in credentials; after saving, add models for it on the AI Models page.',
  '编辑 AI 供应商': 'Edit AI Provider',
  '调整 AI 供应商的名称与凭据。':
    'Adjust the AI provider name and credentials.',
  '连接测试成功！': 'Connection test succeeded!',
  连接测试失败: 'Connection test failed',
  // 系统设置页面
  管理系统的配置和设置: 'Manage system configurations and settings',
  用户管理: 'Users',
  '管理系统中所有可登录的账号；将其加入应用后即可作为客服参与会话。':
    'Manage all login accounts in the system. Add them to a app so they can serve as customer service.',
  新增用户: 'New user',
  编辑用户: 'Edit user',
  暂无用户: 'No users',
  全部类型: 'All types',
  '查看系统整体运行概览。': 'View an overview of the whole system.',
  存储设置: 'Storage Settings',
  本地磁盘: 'Local Disk',
  当前服务器: 'Current Server',
  应用本地文件系统: 'Application Local Filesystem',
  '本地 public 目录': 'Local public directory',
  'AI 设置': 'AI Settings',
  邮箱服务器: 'Mail Server',
  '配置系统事务邮件使用的发送驱动和发件身份。':
    'Configure the mail driver and sender identity for system emails.',
  启用系统邮件: 'Enable system mail',
  发件邮箱: 'From address',
  发件人名称: 'From name',
  邮件驱动: 'Mail driver',
  'SMTP 主机': 'SMTP host',
  'SMTP 端口': 'SMTP port',
  加密方式: 'Encryption',
  无: 'None',
  '超时时间（秒）': 'Timeout (seconds)',
  用户名: 'Username',
  密码: 'Password',
  取消清除: 'Cancel clear',
  清除: 'Clear',
  'EHLO 域名': 'EHLO domain',
  'Sendmail 路径': 'Sendmail path',
  'Mailgun 域名': 'Mailgun domain',
  'Mailgun 密钥': 'Mailgun secret',
  'Mailgun 接口地址': 'Mailgun endpoint',
  请求协议: 'Request scheme',
  'Postmark Token': 'Postmark token',
  消息流: 'Message stream',
  'Resend API Key': 'Resend API key',
  'Access Key ID': 'Access Key ID',
  'Secret Access Key': 'Secret Access Key',
  区域: 'Region',
  'Session Token': 'Session Token',
  测试收件邮箱: 'Test recipient email',
  测试: 'Test',
  发送测试邮件: 'Send test email',
  '上传中...': 'Uploading...',
  未设置: 'Not set',

  // 存储设置页面
  '配置对象存储服务，支持 Amazon S3 和阿里云 OSS 等兼容服务':
    'Configure object storage services, supports Amazon S3, Alibaba Cloud OSS and compatible services',
  启用对象存储: 'Enable Object Storage',
  '启用后，文件将上传到配置的对象存储服务':
    'When enabled, files will be uploaded to the configured object storage service',
  当前使用的存储配置: 'Current storage profile',
  请选择存储配置: 'Select a storage profile',
  存储配置管理: 'Storage Profile Management',
  '支持创建多个存储配置，并选择其中一个作为当前上传目标':
    'Create multiple storage profiles and choose one as the current upload target',
  '选择当前生效的文件存储目标；在操作列中切换后立即生效。':
    'Choose the active file storage target. Use the action column to switch and apply changes immediately.',
  新增配置: 'New profile',
  新增存储配置: 'New storage profile',
  编辑存储配置: 'Edit storage profile',
  '填写对象存储凭据；可先点检测连接确认无误后再保存。':
    'Fill in the object storage credentials. You can run a connection test before saving.',
  '可更新配置名称、访问凭据和自定义域名。':
    'You can update the profile name, access credentials, and custom domain.',
  收起: 'Collapse',
  配置名称: 'Profile name',
  存储提供商: 'Storage Provider',
  查看文档: 'View docs',
  'Access Key / Access Key ID': 'Access Key / Access Key ID',
  'Secret Key / Access Key Secret': 'Secret Key / Access Key Secret',
  '留空表示不修改（首次启用必须填写）':
    'Leave blank to keep unchanged (required on first enable)',
  Bucket: 'Bucket',
  'Bucket 名称': 'Bucket Name',
  '区域 (Region)': 'Region',
  'Endpoint 地址': 'Endpoint URL',
  '使用外网 Endpoint': 'Use public Endpoint',
  '使用内网 Endpoint': 'Use internal Endpoint',
  '如果服务器和对象存储在同一区域，建议使用内网 Endpoint 以提高速度并节省流量费用':
    'If the server and the object storage are in the same region, using the internal Endpoint is recommended for better performance and lower traffic costs',
  '自定义域名 (可选)': 'Custom Domain (Optional)',
  '如果配置了 CDN 或自定义域名，请在此填写，用于生成文件访问 URL':
    'If you have configured CDN or custom domain, enter it here for generating file access URLs',
  检测连接: 'Test connection',
  清除凭据: 'Clear credentials',
  显示: 'Show',
  隐藏: 'Hide',
  请先保存当前配置后再启用: 'Save the current configuration before enabling',
  '请先填写 API Key 后再启用': 'Enter the API Key before enabling',
  '请先填写 Base URI 后再启用': 'Enter the Base URI before enabling',
  '请先填写 Endpoint 后再启用': 'Enter the Endpoint before enabling',
  请先填写服务地址后再启用: 'Enter the service URL before enabling',
  请先完成必填配置后再启用:
    'Complete the required configuration before enabling',
  启用前至少需要一个启用中的大语言模型:
    'At least one active LLM model is required before enabling',
  '供应商启用时，至少保留一个启用中的大语言模型':
    'Keep at least one active LLM model while the provider is enabled',
  '确认删除供应商？': 'Confirm delete provider?',
  '确定要删除该供应商吗？所有关联模型都会一起删除。':
    'Delete this provider and all of its related models?',
  '确定要删除这个模型吗？删除后无法恢复。':
    'Delete this model? This action cannot be undone.',

  // 存储设置交互
  设为当前: 'Set as active',
  使用中: 'In Use',
  切换: 'Switch',
  'Access Key': 'Access Key',
  '选择当前生效的文件存储目标；点击行首单选切换，立即生效。':
    'Pick where uploaded files are stored. Click a row to switch — changes apply immediately.',
  在用: 'Active',
  本地存储: 'Local storage',
  '文件保存到服务器本地的 public 磁盘目录。':
    'Files are saved to the server\u2019s local public disk.',
  '确认删除存储配置？': 'Delete this storage profile?',
  '删除后该配置不再可用；如果有附件还引用着该配置，删除会被拒绝。':
    'Once deleted the profile cannot be used. Deletion is blocked if any attachments still reference it.',
  '填写对象存储凭据，建议先点检测连接确认无误后再创建。':
    'Fill in your object storage credentials. Run a connection test first to confirm everything works before creating.',
  基础信息: 'Basics',
  '区域与 Endpoint': 'Region & Endpoint',
  访问凭据: 'Access credentials',
  可选项: 'Optional',
  '查看 {provider} 接入文档': 'View {provider} setup docs',
  '编辑：{name}': 'Edit: {name}',
  编辑配置: 'Edit profile',
  '存储提供商不可修改；其余字段均可调整，凭据留空表示保持不变。':
    'The storage provider cannot be changed. Other fields can be updated; leave credentials blank to keep them unchanged.',
  已创建后不可修改: 'Locked after creation',
  '凭据留空表示保持不变；只在需要轮换时填写。':
    'Leave credentials blank to keep them unchanged. Fill them in only when rotating keys.',

  // AI 运行时
  'AI Runtime': 'AI Runtime',
  '统一管理 AI 供应商、模型容量和系统默认值。':
    'Manage AI providers, model capacity, and system defaults in one place',
  供应商与模型: 'Providers & Models',
  '管理连接凭据、可用模型，以及每个模型自己的容量限制。':
    'Manage connection credentials, available models, and per-model capacity limits',
  系统默认: 'System Defaults',
  '设置系统默认模型、全局并发和过载提示。':
    'Set the default model, global concurrency, and overload message',
  '配置 AI 推理运行时的全局参数':
    'Configure global AI inference runtime parameters',
  默认大语言模型: 'Default LLM Model',
  请选择默认模型: 'Select default model',
  全局最大并发: 'Global Max Concurrency',
  '默认请求超时 (ms)': 'Default Request Timeout (ms)',
  默认重试次数: 'Default Max Retries',
  '默认 429 重试': 'Default 429 Retry',
  '默认启用限流后重试（429）': 'Retry on rate limiting by default (429)',
  '对 429 Too Many Requests 状态码执行重试':
    'Retry on 429 Too Many Requests status code',
  '默认 5xx 重试': 'Default 5xx Retry',
  '默认启用服务异常后重试（5xx）': 'Retry on server errors by default (5xx)',
  '对 5xx 服务器错误状态码执行重试': 'Retry on 5xx server error status codes',
  '默认冷却时间 (秒)': 'Default Cooldown (seconds)',
  过载提示文案: 'Overload Message',
  '未设置时运行时将使用默认 i18n 文案':
    'Runtime will use default i18n message when not set',
  '当前默认模型已失效，请重新选择。':
    'The current default model is invalid. Please select a new one.',
  未设置或已失效: 'Not set or invalid',
  默认模型: 'Default Model',
  这里决定系统在未指定自定义模型时默认使用哪个大语言模型:
    'Choose which LLM the system should use when no custom model is specified',
  '应用选择继承系统默认时，会使用这里选中的模型。':
    'Instances using system defaults will use the model selected here',
  并发控制: 'Concurrency Control',
  '控制系统同一时间最多处理多少 AI 请求。':
    'Control how many AI requests the system may handle at the same time',
  '同一时间允许执行的 AI 请求数。':
    'How many AI requests may run at the same time',
  '用于 AI 运行时过载保护时向用户展示的提示文案。':
    'Message shown to users when AI runtime overload protection is triggered',
  '例如：当前 AI 请求较多，请稍后再试。':
    'For example: AI is busy right now. Please try again shortly.',
  '当前没有可用的启用模型，请先在供应商与模型中启用至少一个大语言模型':
    'There are no enabled models available yet. Enable at least one LLM in Providers & Models first.',
  连接与凭据: 'Connection & Credentials',
  模型: 'Models',
  '选择此供应商下可用的模型，并为每个模型单独设置并发、RPM 和 TPM 等容量限制。':
    'Choose the models available under this provider and set per-model concurrency, RPM, and TPM limits',
  暂无供应商: 'No providers',
  内置: 'Built-in',
  添加模型: 'Add model',
  编辑模型: 'Edit model',
  暂无模型: 'No models',
  连接测试成功: 'Connection test succeeded',

  // 添加供应商对话框
  添加供应商: 'Add provider',
  协议: 'Protocol',

  // 模型表单对话框
  模型类型: 'Model type',
  显示名称: 'Display name',
  向量维度: 'Vector dimension',
  '留空 = 用模型默认维度': 'Leave blank = use model default',

  // 知识库引擎
  知识库引擎: 'Knowledge engine',
  '统一配置全局向量模型、索引方式与文档分块参数':
    'Configure the global embedding model, indexing methods, and document chunking',
  向量模型: 'Embedding model',
  不启用向量检索: 'Disable vector retrieval',
  '选中向量模型后即决定其 model_id 与维度。更换向量模型或维度会使各应用已建的向量索引失配，需到对应知识库重新索引后检索才准确。':
    'Selecting an embedding model fixes its model ID and dimension. Changing the embedding model or dimension invalidates existing vector indexes in every app; the affected knowledge bases must be re-indexed before retrieval is accurate again.',
  启用向量检索: 'Enable vector retrieval',
  '开启后知识库召回会结合向量相似度。':
    'When enabled, retrieval blends vector similarity into recall.',
  '启用 RAPTOR 摘要树': 'Enable RAPTOR summary tree',
  '构建多层摘要树，提升长文档的跨段召回。':
    'Builds a multi-level summary tree to improve cross-chunk recall for long documents.',
  分块策略: 'Chunking strategy',
  '单块最大 token 数': 'Max tokens per chunk',
  '相邻块重叠 token 数': 'Overlap tokens between chunks',

  // 补充：缺失的英文文案
  '选择品牌并填写凭据。': 'Pick a brand and fill in credentials.',
  当日用量: "Today's usage",
  本月用量: "This month's usage",
  管理: 'Manage',
  应用知识库引擎: 'Instance Knowledge Engine',
  '为「{name}」配置向量模型、索引方式与文档分块参数。':
    'Configure the embedding model, indexing methods, and document chunking for {name}.',
  请选择向量模型: 'Select an embedding model',
  '更换向量模型或维度会使该应用已建的向量索引失配，保存后会自动重建本应用索引。':
    'Changing the embedding model or dimensions invalidates this app’s existing vector index; saving will automatically rebuild the app index.',
  '如 1536 / 3072，需与所选模型的输出维度一致':
    'e.g. 1536 / 3072, must match the output dimensions of the selected model',
  '确认更改向量规格？': 'Change the vector specification?',
  '保存后会按新规格清空并重建该应用的向量索引，重建期间向量检索结果可能不完整。':
    'Saving will clear and rebuild this app’s vector index with the new specification; vector retrieval results may be incomplete during the rebuild.',
  '按用途管理模型，同用途内按权重加权随机选择，失败自动降级到其他模型。':
    'Manage models by purpose; within a purpose, selection is weighted-random by weight, falling back to other models on failure.',
  权重: 'Weight',
  该用途暂无模型: 'No models for this purpose yet',
  用途: 'Purpose',
  预设模型: 'Preset model',
  '取值 1-100，同用途内按权重加权随机选择，权重越大被选中的概率越高，失败时自动降级到其他模型。':
    '1-100. Within a purpose, selection is weighted-random by weight; a higher weight means a higher chance of being picked, with automatic fallback to other models on failure.',
  '选择一个预设模型以填充模型 ID。':
    'Select a preset model to fill in the model ID.',
} as const;
